<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\ContactSource;
use App\Enums\OrgRole;
use App\Messaging\Accounts\SmtpSender;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Flow;
use App\Models\FlowRun;
use App\Models\FlowStep;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Segment;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\ContactActivity;
use App\Services\ContactService;
use App\Services\FlowService;
use App\Services\Tracking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

/** Etapa B: automatizările (declanșatori, pași, ramuri, ieșiri, smart sending, ore de liniște). */
final class FlowTest extends TestCase
{
    use RefreshDatabase;

    private \ArrayObject $mails;

    protected function setUp(): void
    {
        parent::setUp();
        // luni, 10:00 ora României: în intervalul permis pentru SMS
        $this->travelTo(Carbon::parse('2026-10-05 10:00', 'Europe/Bucharest'));
        $this->mails = new \ArrayObject;
        $transport = new class($this->mails) extends AbstractTransport
        {
            public function __construct(private \ArrayObject $sink)
            {
                parent::__construct();
            }

            protected function doSend(SentMessage $message): void
            {
                $this->sink[] = $message->getOriginalMessage();
            }

            public function __toString(): string
            {
                return 'test://';
            }
        };
        $this->app->instance(SmtpSender::class, new SmtpSender(fn () => $transport));
        Http::fake(['secure.smslink.ro/*' => Http::response('MESSAGE;1;Message sent;555;1')]);
    }

    /** @return array{0: Organization, 1: User} */
    private function firm(string $name = 'Podreg'): array
    {
        $org = $this->makeOrganization($name);
        $owner = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $owner->id, 'role' => OrgRole::Owner]));
        $this->actingAs($owner);
        $this->put("/app/{$org->slug}/canale/email", ['host' => 'h', 'port' => '465', 'encryption' => 'ssl', 'username' => 'u', 'password' => 'p', 'from_email' => 'office@podreg.ro'])->assertSessionHasNoErrors();
        $this->put("/app/{$org->slug}/canale/sms", ['connection_id' => 'C', 'password' => 'p', 'ascii' => '1'])->assertSessionHasNoErrors();

        return [$org, $owner];
    }

    private function contact(Organization $org, string $first, array $consent = ['email'], array $custom = [], ?string $phone = null): Contact
    {
        return $this->tenant()->runAs($org, function () use ($first, $consent, $custom, $phone) {
            $c = app(ContactService::class)->create(array_filter(['first_name' => $first, 'email' => strtolower($first).'@ex.ro', 'phone' => $phone, 'custom_fields' => $custom ?: null]), ContactSource::Manual);
            foreach ($consent as $channel) {
                app(ConsentService::class)->record($c, Channel::from($channel), ConsentPurpose::Marketing, ConsentStatus::Granted, 'test');
            }

            return $c;
        });
    }

    private function flowFrom(Organization $org, string $template): Flow
    {
        $this->post("/app/{$org->slug}/automatizari", ['template' => $template])->assertRedirect();

        return $this->tenant()->runAs($org, fn () => Flow::query()->latest('id')->firstOrFail());
    }

    private function live(Organization $org, Flow $flow): void
    {
        $this->post("/app/{$org->slug}/automatizari/{$flow->id}/stare", ['status' => 'live'])->assertSessionHas('ok');
    }

    private function tick(int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->artisan('vitim:flows')->assertSuccessful();
        }
    }

    private function subjects(): array
    {
        return array_map(fn ($m) => $m->getSubject(), $this->mails->getArrayCopy());
    }

    public function test_welcome_series_runs_once_per_contact(): void
    {
        [$org] = $this->firm();
        $flow = $this->flowFrom($org, 'welcome');
        $this->get("/app/{$org->slug}/automatizari/{$flow->id}")->assertOk()->assertSee('Bun venit, {{prenume}}!')->assertSee('Așteaptă');
        $this->live($org, $flow);

        $ana = $this->contact($org, 'Ana'); // acordul nou = „s-a abonat” → intră în flux
        $this->tick();
        $this->assertSame(['Bun venit, Ana!'], $this->subjects());
        $this->travel(2)->days();
        $this->tick();
        $this->assertSame('De ce ne aleg clienții', $this->subjects()[1]);
        $this->travel(3)->days();
        $this->tick();
        $this->assertSame('Ana, un mic cadou pentru tine', $this->subjects()[2]);
        $this->tenant()->runAs($org, fn () => $this->assertSame('completed', FlowRun::query()->sole()->status));

        // o nouă abonare (după retragere) nu o bagă din nou: „o singură dată pe contact”
        $this->tenant()->runAs($org, function () use ($ana): void {
            app(ConsentService::class)->record($ana, Channel::Email, ConsentPurpose::Marketing, ConsentStatus::Revoked, 'test');
            app(ConsentService::class)->record($ana, Channel::Email, ConsentPurpose::Marketing, ConsentStatus::Granted, 'test');
        });
        $this->assertSame(1, $this->tenant()->runAs($org, fn () => FlowRun::query()->count()));
        $this->get("/app/{$org->slug}/automatizari/{$flow->id}")->assertSee('1 trimise')->assertSee('0% deschideri')->assertSee('a terminat');
    }

    public function test_lead_followup_exits_when_the_contact_orders(): void
    {
        [$org] = $this->firm();
        $flow = $this->flowFrom($org, 'lead_followup');
        $this->live($org, $flow);
        $ion = $this->contact($org, 'Ion', ['email', 'sms'], [], '0722000111');
        $this->tenant()->runAs($org, fn () => app(ContactActivity::class)->record($ion, 'lead_created'));
        $this->tick();
        $this->assertCount(0, $this->mails, 'așteaptă o zi');
        $this->travel(1441)->minutes();
        $this->tick();
        $this->assertSame(['Ai primit oferta, Ion?'], $this->subjects());
        $this->tenant()->runAs($org, fn () => app(ContactActivity::class)->record($ion, 'placed_order', [], 500.0));
        $this->travel(3)->days();
        $this->tick();
        Http::assertNothingSent();
        $this->tenant()->runAs($org, fn () => $this->assertSame('exited', FlowRun::query()->sole()->status));
    }

    public function test_abandoned_cart_branches_on_opens_and_respects_quiet_hours(): void
    {
        [$org] = $this->firm();
        $flow = $this->flowFrom($org, 'abandoned_cart');
        $this->live($org, $flow);
        $cart = ['items' => [['name' => 'Cabană 60 mp']], 'checkout_url' => 'https://podreg.ro/checkout', 'currency' => 'lei'];
        $ana = $this->contact($org, 'Ana', ['email', 'sms'], [], '0722000001');
        $bob = $this->contact($org, 'Bob', ['email', 'sms'], [], '0722000002');
        $this->tenant()->runAs($org, function () use ($ana, $bob, $cart): void {
            app(ContactActivity::class)->record($ana, 'started_checkout', $cart, 22500.0);
            app(ContactActivity::class)->record($bob, 'started_checkout', $cart, 22500.0);
        });
        $this->tick();
        $this->travel(61)->minutes();
        $this->tick();
        $this->assertSame(['Ana, ai uitat ceva în coș', 'Bob, ai uitat ceva în coș'], $this->subjects());
        $body = $this->mails[0]->getTextBody();
        $this->assertStringContainsString('Cabană 60 mp (22.500,00 lei)', $body);
        $this->assertStringContainsString('https://podreg.ro/checkout', $body);

        // Ana deschide emailul, Bob nu
        $this->tenant()->runAs($org, fn () => app(Tracking::class)->opened(CampaignRecipient::query()->where('contact_id', $ana->id)->sole()));
        // a doua zi la 21:00: emailul pleacă, SMS-ul așteaptă dimineața
        $this->travelTo(Carbon::parse('2026-10-06 21:00', 'Europe/Bucharest'));
        $this->tick();
        $this->assertSame('Încă te gândești?', $this->subjects()[2]);
        Http::assertNothingSent();
        $this->travelTo(Carbon::parse('2026-10-07 09:05', 'Europe/Bucharest'));
        $this->tick();
        Http::assertSent(fn ($r) => $r['to'] === '0722000002' && str_contains($r['message'], 'https://podreg.ro/checkout'));
        $this->tenant()->runAs($org, fn () => $this->assertSame(['completed'], FlowRun::query()->pluck('status')->unique()->values()->all()));
    }

    public function test_smart_sending_skips_and_paused_flows_wait(): void
    {
        [$org] = $this->firm();
        $flow = $this->flowFrom($org, 'welcome');
        $this->live($org, $flow);
        $this->contact($org, 'Ana');
        $this->tenant()->runAs($org, fn () => $flow->forceFill(['settings' => ['smart_sending_hours' => 16]])->save());
        $this->tick();
        $this->assertCount(1, $this->mails);
        // pauză: rularea rămâne pe loc
        $this->post("/app/{$org->slug}/automatizari/{$flow->id}/stare", ['status' => 'paused']);
        $this->travel(2)->days();
        $this->tick();
        $this->assertCount(1, $this->mails);
        // repornire; al doilea email se sare dacă a primit un email în ultimele 16 ore
        $ana = $this->tenant()->runAs($org, fn () => Contact::query()->sole());
        $this->tenant()->runAs($org, fn () => app(ContactActivity::class)->record($ana, 'email_sent', ['subject' => 'Altă campanie']));
        $this->post("/app/{$org->slug}/automatizari/{$flow->id}/stare", ['status' => 'live']);
        $this->tick();
        $this->assertCount(1, $this->mails);
        $this->tenant()->runAs($org, fn () => $this->assertStringContainsString('Smart sending', (string) CampaignRecipient::query()->where('status', 'excluded')->sole()->reason));
    }

    public function test_segment_and_birthday_triggers(): void
    {
        [$org] = $this->firm();
        $this->contact($org, 'Ana', ['email'], ['city' => 'Suceava']);
        $segment = $this->tenant()->runAs($org, fn () => Segment::create(['name' => 'Suceava', 'definition' => ['match' => 'all', 'conditions' => [['type' => 'property', 'field' => 'city', 'op' => 'equals', 'value' => 'Suceava']]]]));
        $flow = $this->flowFrom($org, 'welcome');
        $this->put("/app/{$org->slug}/automatizari/{$flow->id}", ['name' => 'Suceava nou', 'trigger_type' => 'segment', 'trigger_segment_id' => $segment->id, 'reentry' => 'never', 'smart_sending_hours' => 0])->assertSessionHasNoErrors();
        $this->live($org, $flow);
        $this->contact($org, 'Bob', ['email'], ['city' => 'Suceava']);
        $this->tick();
        $this->assertSame(['Bun venit, Bob!'], $this->subjects(), 'Ana era deja în segment la pornire');

        $bday = $this->flowFrom($org, 'birthday');
        $this->live($org, $bday);
        $this->contact($org, 'Cip', ['email'], ['birthday' => '1990-10-05']);
        $this->tick(2);
        $this->assertContains('La mulți ani, Cip! 🎉', $this->subjects());
        $this->assertCount(1, array_filter($this->subjects(), fn ($s) => str_starts_with($s, 'La mulți ani')), 'o singură dată pe zi');
    }

    public function test_builder_validation_permissions_and_isolation(): void
    {
        [$org, $owner] = $this->firm();
        $this->post("/app/{$org->slug}/automatizari", ['name' => 'Gol'])->assertRedirect();
        $flow = $this->tenant()->runAs($org, fn () => Flow::query()->sole());
        $this->post("/app/{$org->slug}/automatizari/{$flow->id}/stare", ['status' => 'live'])->assertSessionHasErrors('flow');
        $this->post("/app/{$org->slug}/automatizari/{$flow->id}/pasi", ['type' => 'email'])->assertRedirect();
        $step = $this->tenant()->runAs($org, fn () => FlowStep::query()->sole());
        $this->post("/app/{$org->slug}/automatizari/{$flow->id}/stare", ['status' => 'live'])->assertSessionHasErrors('flow');
        $this->put("/app/{$org->slug}/automatizari/{$flow->id}/pasi/{$step->id}", ['subject' => 'Salut', 'body' => 'Text'])->assertRedirect();
        $this->post("/app/{$org->slug}/automatizari/{$flow->id}/pasi", ['type' => 'condition', 'after' => $step->id]);
        $cond = $this->tenant()->runAs($org, fn () => FlowStep::query()->where('type', 'condition')->sole());
        $this->post("/app/{$org->slug}/automatizari/{$flow->id}/pasi", ['type' => 'sms', 'parent' => $cond->id, 'branch' => 'no']);
        $this->tenant()->runAs($org, fn () => $this->assertSame('no', FlowStep::query()->where('type', 'sms')->sole()->branch));
        $this->get("/app/{$org->slug}/automatizari/{$flow->id}")->assertOk()->assertSee('Condiție (da / nu)');
        $this->post("/app/{$org->slug}/automatizari/{$flow->id}/pasi", ['type' => 'nimic'])->assertSessionHasErrors('type');

        $operator = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $operator->id, 'role' => OrgRole::Agent]));
        $this->actingAs($operator);
        $this->get("/app/{$org->slug}/automatizari")->assertForbidden();

        $other = $this->makeOrganization('Alta');
        $stranger = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($other, fn () => Membership::create(['user_id' => $stranger->id, 'role' => OrgRole::Owner]));
        $this->actingAs($stranger);
        $this->get("/app/{$other->slug}/automatizari/{$flow->id}")->assertNotFound();
        $this->put("/app/{$other->slug}/automatizari/{$flow->id}/pasi/{$step->id}", ['subject' => 'x'])->assertNotFound();
        $this->post("/app/{$other->slug}/automatizari/{$flow->id}/stare", ['status' => 'paused'])->assertNotFound();
        $this->assertSame('Salut', $this->tenant()->runAs($org, fn () => FlowStep::query()->find($step->id)->conf('subject')));
        $this->assertTrue(app(FlowService::class) instanceof FlowService);
    }
}
