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
use App\Models\ContactEvent;
use App\Models\ContactList;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Segment;
use App\Models\User;
use App\Services\CampaignService;
use App\Services\ConsentService;
use App\Services\ContactActivity;
use App\Services\ContactService;
use App\Services\ListService;
use App\Services\SegmentQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

/** Etapa A: urmărirea deschiderilor / click-urilor, cronologia contactului, liste și segmente. */
final class AudienceTest extends TestCase
{
    use RefreshDatabase;

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

        return [$org, $owner];
    }

    private function contact(Organization $org, string $first, ?string $email, array $consent = [], array $custom = []): Contact
    {
        return $this->tenant()->runAs($org, function () use ($first, $email, $consent, $custom) {
            $c = app(ContactService::class)->create(array_filter(['first_name' => $first, 'email' => $email, 'custom_fields' => $custom ?: null]), ContactSource::Manual);
            foreach ($consent as $channel) {
                app(ConsentService::class)->record($c, Channel::from($channel), ConsentPurpose::Marketing, ConsentStatus::Granted, 'test');
            }

            return $c;
        });
    }

    private function ids(Organization $org, array $definition): array
    {
        return $this->tenant()->runAs($org, fn () => SegmentQuery::apply(Contact::query(), SegmentQuery::normalize($definition))->orderBy('first_name')->pluck('first_name')->all());
    }

    public function test_segment_conditions(): void
    {
        [$org] = $this->firm();
        $ana = $this->contact($org, 'Ana', 'ana@ex.ro', ['email'], ['city' => 'Suceava']);
        $bob = $this->contact($org, 'Bob', 'bob@ex.ro', ['email', 'sms'], ['city' => 'Cluj-Napoca']);
        $cip = $this->contact($org, 'Cip', 'cip@ex.ro');
        $this->tenant()->runAs($org, function () use ($ana, $bob, $cip): void {
            app(ConsentService::class)->record($bob, Channel::Email, ConsentPurpose::Marketing, ConsentStatus::Revoked, 'test'); // ultimul cuvânt: retras
            $activity = app(ContactActivity::class);
            $activity->record($ana, 'email_opened');
            $activity->record($ana, 'email_opened');
            $activity->record($bob, 'email_opened', [], null, null, null, null, now()->subDays(60));
            $activity->record($cip, 'placed_order', [], 1500.0);
            $activity->record($cip, 'placed_order', [], 900.0);
            $list = ContactList::create(['name' => 'VIP']);
            app(ListService::class)->add($list, $bob);
        });
        $listId = $this->tenant()->runAs($org, fn () => ContactList::query()->sole()->id);

        $this->assertSame(['Ana'], $this->ids($org, ['conditions' => [['type' => 'consent', 'channel' => 'email']]]));
        $this->assertSame(['Bob', 'Cip'], $this->ids($org, ['conditions' => [['type' => 'consent', 'channel' => 'email', 'op' => 'not_granted']]]));
        $this->assertSame(['Ana'], $this->ids($org, ['conditions' => [['type' => 'event', 'event' => 'email_opened', 'op' => 'at_least', 'count' => 2]]]));
        $this->assertSame(['Ana'], $this->ids($org, ['conditions' => [['type' => 'event', 'event' => 'email_opened', 'days' => 30]]]));
        $this->assertSame(['Cip'], $this->ids($org, ['conditions' => [['type' => 'event', 'event' => 'email_opened', 'op' => 'zero']]]), 'zero');
        $this->assertSame(['Bob'], $this->ids($org, ['conditions' => [['type' => 'list', 'list_id' => $listId]]]));
        $this->assertSame(['Ana', 'Cip'], $this->ids($org, ['conditions' => [['type' => 'list', 'list_id' => $listId, 'op' => 'not_in']]]));
        $this->assertSame(['Ana'], $this->ids($org, ['conditions' => [['type' => 'property', 'field' => 'city', 'op' => 'contains', 'value' => 'suceava']]]), 'city');
        $this->assertSame(['Cip'], $this->ids($org, ['conditions' => [['type' => 'revenue', 'op' => 'at_least', 'value' => 2000]]]), 'revenue');
        $this->assertSame(['Ana', 'Cip'], $this->ids($org, ['match' => 'any', 'conditions' => [['type' => 'consent', 'channel' => 'email'], ['type' => 'revenue', 'value' => 100]]]));
        // condițiile necunoscute sunt eliminate la normalizare; o condiție invalidă nu lărgește segmentul
        $this->assertSame([], SegmentQuery::normalize(['conditions' => [['type' => 'sql', 'raw' => '1=1']]])['conditions']);
        $this->assertSame([], $this->tenant()->runAs($org, fn () => SegmentQuery::apply(Contact::query(), ['conditions' => [['type' => 'evil']]])->pluck('id')->all()));
    }

    public function test_campaign_to_segment_minus_list_with_open_and_click_tracking(): void
    {
        [$org, $owner] = $this->firm();
        $this->actingAs($owner);
        $this->put("/app/{$org->slug}/canale/email", ['host' => 'h', 'port' => '465', 'encryption' => 'ssl', 'username' => 'u', 'password' => 'p', 'from_email' => 'office@podreg.ro'])->assertSessionHasNoErrors();
        $ana = $this->contact($org, 'Ana', 'ana@ex.ro', ['email'], ['city' => 'Suceava']);
        $this->contact($org, 'Bob', 'bob@ex.ro', ['email'], ['city' => 'Suceava']);
        $this->contact($org, 'Cip', 'cip@ex.ro', ['email'], ['city' => 'Iași']);

        // segment din panou + listă de excludere
        $this->post("/app/{$org->slug}/audienta/segmente", ['name' => 'Suceava', 'definition' => ['match' => 'all', 'conditions' => [['type' => 'property', 'field' => 'city', 'op' => 'equals', 'value' => 'Suceava']]]])
            ->assertSessionHas('ok', fn ($m) => str_contains($m, '2 contacte'));
        $this->post("/app/{$org->slug}/audienta/liste", ['name' => 'Nu trimite'])->assertRedirect();
        [$segment, $list] = $this->tenant()->runAs($org, fn () => [Segment::query()->sole(), ContactList::query()->sole()]);
        $bob = $this->tenant()->runAs($org, fn () => Contact::query()->where('first_name', 'Bob')->sole());
        $this->post("/app/{$org->slug}/audienta/liste/{$list->id}/membri", ['action' => 'add', 'contact_id' => $bob->id])->assertSessionHas('ok');
        $this->get("/app/{$org->slug}/audienta")->assertOk()->assertSee('Suceava')->assertSee('Nu trimite');
        $this->get("/app/{$org->slug}/audienta/segmente/{$segment->id}")->assertOk()->assertSee('2 contacte');

        $this->post("/app/{$org->slug}/campanii", ['name' => 'Local', 'channel' => 'email']);
        $c = $this->tenant()->runAs($org, fn () => Campaign::query()->sole());
        $this->put("/app/{$org->slug}/campanii/{$c->id}", ['name' => 'Local', 'subject' => 'Salut', 'body' => 'Vezi https://podreg.ro/oferta',
            'include' => ["segment:{$segment->id}"], 'exclude' => ["list:{$list->id}"]])->assertSessionHasNoErrors();
        $this->post("/app/{$org->slug}/campanii/{$c->id}/aprobare", ['confirm' => '1'])->assertSessionHas('ok', fn ($m) => str_contains($m, 'către 1 destinatari'));
        $this->artisan('vitim:campaigns');
        $this->assertCount(1, $this->mails);
        $html = $this->mails[0]->getHtmlBody();

        // pixelul și linkul urmărit
        $recipient = $this->tenant()->runAs($org, fn () => CampaignRecipient::query()->sole());
        $this->assertStringContainsString('/t/o/'.$recipient->unsubscribe_code.'.gif', $html);
        $this->assertDoesNotMatchRegularExpression('#href="https://podreg\.ro/oferta"#', $html);
        preg_match('#href="([^"]+/t/c/[^"]+)"#', $html, $m);
        $click = html_entity_decode($m[1]);
        auth()->logout();
        $this->get('/t/o/'.$recipient->unsubscribe_code.'.gif')->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->get(parse_url($click, PHP_URL_PATH).'?'.parse_url($click, PHP_URL_QUERY))->assertRedirect('https://podreg.ro/oferta');
        // linkul modificat (alt URL, aceeași semnătură) nu redirecționează: nu e open redirect
        $evil = str_replace(urlencode('https://podreg.ro/oferta'), urlencode('https://evil.example'), parse_url($click, PHP_URL_QUERY));
        $this->get(parse_url($click, PHP_URL_PATH).'?'.$evil)->assertNotFound();

        $this->tenant()->runAs($org, function () use ($ana, $c): void {
            $r = CampaignRecipient::query()->sole();
            $this->assertNotNull($r->opened_at);
            $this->assertSame(1, $r->click_count);
            $this->assertSame(['email_clicked', 'email_opened', 'email_sent'], ContactEvent::query()->where('contact_id', $ana->id)->orderBy('type')->pluck('type')->all());
            $this->assertSame(['sent' => 1, 'opened' => 1, 'clicked' => 1, 'open_rate' => 100.0, 'click_rate' => 100.0], $c->fresh()->engagement());
        });
        $this->actingAs($owner);
        $this->get("/app/{$org->slug}/contacte/{$ana->id}")->assertSee('A deschis emailul')->assertSee('A dat click în email');
        $this->get("/app/{$org->slug}/campanii/{$c->id}")->assertSee('Deschideri unice')->assertSee('100%');
    }

    public function test_lists_and_segments_are_isolated(): void
    {
        [$org, $owner] = $this->firm();
        [$other, $otherOwner] = $this->firm('Alta');
        $this->tenant()->runAs($org, fn () => ContactList::create(['name' => 'A']));
        $listA = $this->tenant()->runAs($org, fn () => ContactList::query()->sole());
        $this->actingAs($otherOwner);
        $this->get("/app/{$other->slug}/audienta/liste/{$listA->id}")->assertNotFound();
        $this->post("/app/{$other->slug}/audienta/liste/{$listA->id}/membri", ['action' => 'add', 'contact_id' => 1])->assertNotFound();
        // o campanie a altei firme care țintește lista firmei A nu atinge pe nimeni din A
        $this->post("/app/{$other->slug}/campanii", ['name' => 'X', 'channel' => 'email']);
        $c = $this->tenant()->runAs($other, fn () => Campaign::query()->sole());
        $this->put("/app/{$other->slug}/campanii/{$c->id}", ['name' => 'X', 'subject' => 'S', 'body' => 'B', 'include' => ["list:{$listA->id}"]]);
        $this->contact($org, 'Ana', 'ana@ex.ro', ['email']);
        $this->assertSame(0, $this->tenant()->runAs($other, fn () => app(CampaignService::class)->audienceQuery($c->fresh())->count()));
    }
}
