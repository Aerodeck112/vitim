<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\ContactSource;
use App\Enums\OrgRole;
use App\Messaging\Accounts\SmtpSender;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Segment;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\ContactActivity;
use App\Services\ContactService;
use App\Services\SegmentQuery;
use App\Services\SendTime;
use App\Services\Tracking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

final class InsightsTest extends TestCase
{
    use RefreshDatabase;

    /** @var \ArrayObject<int, Email> */
    private \ArrayObject $mails;

    protected function setUp(): void
    {
        parent::setUp();
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
    }

    /** @return array{0: Organization, 1: User} */
    private function firm(string $name = 'Podreg'): array
    {
        $org = $this->makeOrganization($name);
        $owner = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $owner->id, 'role' => OrgRole::Owner]));
        $this->actingAs($owner);
        $this->put("/app/{$org->slug}/canale/email", ['host' => 'mail.podreg.ro', 'port' => '465', 'encryption' => 'ssl', 'username' => 'office@podreg.ro',
            'password' => 'x', 'from_email' => 'office@podreg.ro', 'hourly_limit' => 1000])->assertSessionHasNoErrors();

        return [$org, $owner];
    }

    private function subscribers(Organization $org, int $n): void
    {
        $this->tenant()->runAs($org, function () use ($n): void {
            for ($i = 1; $i <= $n; $i++) {
                $c = app(ContactService::class)->create(['first_name' => 'C'.$i, 'email' => "c{$i}@ex.ro"], ContactSource::Manual);
                app(ConsentService::class)->record($c, Channel::Email, ConsentPurpose::Marketing, ConsentStatus::Granted, 'test');
            }
        });
    }

    public function test_ab_test_sends_the_winner_to_the_rest(): void
    {
        [$org] = $this->firm();
        $this->subscribers($org, 40);
        $this->post("/app/{$org->slug}/campanii", ['name' => 'AB', 'channel' => 'email']);
        $c = $this->tenant()->runAs($org, fn () => Campaign::query()->sole());
        $base = "/app/{$org->slug}/campanii/{$c->id}";
        $this->put($base, ['name' => 'AB', 'subject' => 'Subiect A', 'body' => 'Salut', 'ab_enabled' => '1'])->assertSessionHasErrors('subject_b');
        $this->put($base, ['name' => 'AB', 'subject' => 'Subiect A', 'body' => 'Salut', 'ab_enabled' => '1', 'subject_b' => 'Subiect B',
            'ab_percent' => 50, 'ab_metric' => 'open', 'ab_wait' => 2])->assertSessionHasNoErrors();
        $this->get($base)->assertOk()->assertSee('Test A/B activ: 50%');
        $this->post("{$base}/aprobare", ['confirm' => '1'])->assertSessionHas('ok');

        $this->tenant()->runAs($org, function () use ($c): void {
            $this->assertSame(['a' => 10, 'b' => 10, 'h' => 20], CampaignRecipient::query()->where('campaign_id', $c->id)->selectRaw('variant, count(*) as n')->groupBy('variant')->pluck('n', 'variant')->map(fn ($n) => (int) $n)->sortKeys()->all());
        });
        $this->artisan('vitim:campaigns');
        $subjects = array_count_values(array_map(fn ($m) => $m->getSubject(), $this->mails->getArrayCopy()));
        ksort($subjects);
        $this->assertSame(['Subiect A' => 10, 'Subiect B' => 10], $subjects, 'doar grupele de test, restul așteaptă');

        // B are mai multe deschideri
        $this->tenant()->runAs($org, function () use ($c): void {
            CampaignRecipient::query()->where('campaign_id', $c->id)->where('variant', 'b')->limit(6)->get()->each(fn ($r) => app(Tracking::class)->opened($r));
            CampaignRecipient::query()->where('campaign_id', $c->id)->where('variant', 'a')->limit(2)->get()->each(fn ($r) => app(Tracking::class)->opened($r));
        });
        $this->travel(1)->hours();
        $this->artisan('vitim:campaigns');
        $this->assertCount(20, $this->mails, 'încă în așteptare');
        $this->travel(61)->minutes();
        $this->artisan('vitim:campaigns');
        $subjects = array_count_values(array_map(fn ($m) => $m->getSubject(), $this->mails->getArrayCopy()));
        ksort($subjects);
        $this->assertSame(['Subiect A' => 10, 'Subiect B' => 30], $subjects);
        $this->tenant()->runAs($org, function () use ($c): void {
            $fresh = $c->fresh();
            $this->assertSame('b', $fresh->ab_winner);
            $this->assertSame('completed', $fresh->status);
        });
        $this->get($base)->assertOk()->assertSee('a câștigat varianta B')->assertSee('60%');
    }

    public function test_ab_needs_enough_recipients(): void
    {
        [$org] = $this->firm();
        $this->subscribers($org, 4);
        $this->post("/app/{$org->slug}/campanii", ['name' => 'AB', 'channel' => 'email']);
        $c = $this->tenant()->runAs($org, fn () => Campaign::query()->sole());
        $this->put("/app/{$org->slug}/campanii/{$c->id}", ['name' => 'AB', 'subject' => 'A', 'body' => 'x', 'ab_enabled' => '1', 'subject_b' => 'B'])->assertSessionHasNoErrors();
        $this->post("/app/{$org->slug}/campanii/{$c->id}/aprobare", ['confirm' => '1'])->assertSessionHasErrors('campaign');
        $this->tenant()->runAs($org, fn () => $this->assertSame(0, CampaignRecipient::query()->count()));
    }

    public function test_send_time_predictions_segments_and_analytics(): void
    {
        [$org] = $this->firm();
        $this->subscribers($org, 3);
        $this->travelTo(now()->setTimezone('Europe/Bucharest')->setTime(23, 0)->utc());
        $this->tenant()->runAs($org, function (): void {
            [$a, $b, $c] = Contact::query()->orderBy('id')->get()->all();
            $activity = app(ContactActivity::class);
            // deschideri: cele mai multe la 19:00 ora României
            for ($i = 0; $i < 40; $i++) {
                $activity->record($a, 'email_opened', [], null, null, null, null, now()->setTimezone('Europe/Bucharest')->subDays($i % 20 + 1)->setTime($i % 4 === 0 ? 8 : 19, 10)->utc());
            }
            // A: client fidel (comandă la ~30 de zile), B: o comandă acum 300 de zile, C: fără comenzi
            foreach ([120, 90, 60, 30, 5] as $d) {
                $activity->record($a, 'placed_order', [], 200, null, null, null, now()->subDays($d));
            }
            $activity->record($b, 'placed_order', [], 1000, null, null, null, now()->subDays(300));
        });
        $best = $this->tenant()->runAs($org, fn () => SendTime::best());
        $this->assertTrue($best['reliable']);
        $this->assertSame(19, $best['hour']);

        $this->artisan('vitim:predictions')->assertSuccessful();
        $this->tenant()->runAs($org, function (): void {
            [$a, $b, $c] = Contact::query()->orderBy('id')->get()->all();
            $this->assertSame('low', $a->churn_risk);
            $this->assertSame(now()->addDays(24)->toDateString(), $a->predicted_next_order_at->toDateString());
            $this->assertGreaterThan(2000, (float) $a->predicted_clv); // 1000 cheltuiți + ~12 comenzi pe an × 200
            $this->assertSame('high', $b->churn_risk);
            $this->assertNull($b->predicted_next_order_at);
            $this->assertNull($c->churn_risk);
            $high = SegmentQuery::normalize(['conditions' => [['type' => 'prediction', 'pick' => 'churn_high']]]);
            $this->assertSame([$b->id], SegmentQuery::apply(Contact::query(), $high)->pluck('id')->all());
            $clv = SegmentQuery::normalize(['conditions' => [['type' => 'prediction', 'pick' => 'clv_at_least', 'value' => 2000]]]);
            $this->assertSame([$a->id], SegmentQuery::apply(Contact::query(), $clv)->pluck('id')->all());
            $this->assertSame('risc de pierdere mare', SegmentQuery::describe($high));
        });
        $a = $this->tenant()->runAs($org, fn () => Contact::query()->orderBy('id')->first());
        $this->get("/app/{$org->slug}/contacte/{$a->id}")->assertOk()->assertSee('Predicții')->assertSee('Risc de pierdere');
        $this->post("/app/{$org->slug}/audienta/segmente", ['name' => 'Risc mare', 'definition' => ['match' => 'all', 'conditions' => [['type' => 'prediction', 'pick' => 'churn_high', 'value' => '']]]])->assertRedirect();
        $this->tenant()->runAs($org, fn () => $this->assertSame('churn_high', Segment::query()->sole()->definition['conditions'][0]['pick']));

        $this->get("/app/{$org->slug}/analiza?zile=365")->assertOk()->assertSee('Venituri pe zi')->assertSee('19:00')->assertSee('2.000 lei');
        $this->post("/app/{$org->slug}/campanii", ['name' => 'Seara', 'channel' => 'email']);
        $camp = $this->tenant()->runAs($org, fn () => Campaign::query()->sole());
        $this->get("/app/{$org->slug}/campanii/{$camp->id}")->assertOk()->assertSee('Ora recomandată');

        $operator = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $operator->id, 'role' => OrgRole::Agent]));
        $this->actingAs($operator);
        $this->get("/app/{$org->slug}/analiza")->assertForbidden();
        $other = $this->makeOrganization('Alta');
        $stranger = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($other, fn () => Membership::create(['user_id' => $stranger->id, 'role' => OrgRole::Owner]));
        $this->actingAs($stranger);
        $this->get("/app/{$other->slug}/analiza?zile=365")->assertOk()->assertDontSee('2.000 lei');
        $this->get("/app/{$other->slug}/contacte/{$a->id}")->assertNotFound();
    }
}
