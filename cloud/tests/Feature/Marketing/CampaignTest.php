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
use App\Models\ChannelAccount;
use App\Models\Contact;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Suppression;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\ContactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

final class CampaignTest extends TestCase
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

    private function lastMail(): Email
    {
        return $this->mails[count($this->mails) - 1];
    }

    /** @return array{0: Organization, 1: User} */
    private function firm(string $name = 'Podreg'): array
    {
        $org = $this->makeOrganization($name);
        $org->forceFill(['company_name' => $name.' SRL', 'vat_id' => 'RO123'])->save();
        $owner = User::factory()->create(['name' => 'Ana Ionescu', 'email' => strtolower($name).'@firma.ro', 'last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $owner->id, 'role' => OrgRole::Owner]));

        return [$org, $owner];
    }

    private function contact(Organization $org, string $first, ?string $email, ?string $phone, array $consent = []): Contact
    {
        return $this->tenant()->runAs($org, function () use ($first, $email, $phone, $consent) {
            $c = app(ContactService::class)->create(array_filter(['first_name' => $first, 'email' => $email, 'phone' => $phone]), ContactSource::Manual);
            foreach ($consent as $channel) {
                app(ConsentService::class)->record($c, Channel::from($channel), ConsentPurpose::Marketing, ConsentStatus::Granted, 'test');
            }

            return $c;
        });
    }

    private function connectEmail(Organization $org, User $owner, int $limit = 100): void
    {
        $this->actingAs($owner);
        $this->put("/app/{$org->slug}/canale/email", ['host' => 'mail.podreg.ro', 'port' => '465', 'encryption' => 'ssl', 'username' => 'office@podreg.ro',
            'password' => 'secret-smtp', 'from_email' => 'office@podreg.ro', 'from_name' => 'Podreg', 'hourly_limit' => $limit])->assertSessionHasNoErrors();
    }

    private function campaign(Organization $org, string $channel, array $content): Campaign
    {
        $this->post("/app/{$org->slug}/campanii", ['name' => $content['name'], 'channel' => $channel])->assertRedirect();
        $campaign = $this->tenant()->runAs($org, fn () => Campaign::query()->where('name', $content['name'])->latest('id')->firstOrFail());
        $this->put("/app/{$org->slug}/campanii/{$campaign->id}", $content)->assertSessionHasNoErrors();

        return $campaign;
    }

    public function test_email_campaign_end_to_end_with_consent_test_approval_and_unsubscribe(): void
    {
        [$org, $owner] = $this->firm();
        $this->connectEmail($org, $owner);
        // parola e criptată în baza de date și nu reapare în pagină; câmpul gol o păstrează
        $this->assertStringNotContainsString('secret-smtp', (string) DB::table('channel_accounts')->value('config'));
        $this->get("/app/{$org->slug}/canale")->assertOk()->assertDontSee('secret-smtp')->assertSee('salvat');
        $this->put("/app/{$org->slug}/canale/email", ['host' => 'mail.podreg.ro', 'port' => '465', 'encryption' => 'ssl', 'username' => 'office@podreg.ro', 'password' => '',
            'from_email' => 'office@podreg.ro', 'hourly_limit' => 100])->assertSessionHasNoErrors();
        $this->assertSame('secret-smtp', $this->tenant()->runAs($org, fn () => ChannelAccount::query()->sole()->setting('password')));
        $this->post("/app/{$org->slug}/canale/email/test", ['test_to' => 'ana@firma.ro'])->assertSessionHas('ok');
        $this->assertCount(1, $this->mails);

        $maria = $this->contact($org, 'Maria', 'maria@ex.ro', null, ['email']);
        $this->contact($org, 'Ion', 'ion@ex.ro', null); // fără acord
        $this->contact($org, 'Dan', null, '0722123456', ['sms']); // fără email

        $campaign = $this->campaign($org, 'email', ['name' => 'Primăvară', 'subject' => '{{prenume}}, 20% reducere', 'body' => "Bună {{prenume}},\n\n**Reducere** la cabane: https://podreg.ro/oferta"]);
        $base = "/app/{$org->slug}/campanii/{$campaign->id}";
        $this->get($base)->assertOk()->assertSee('contacte vor primi campania')->assertSee('Fără consimțământ de marketing')->assertSee('Maria, 20% reducere');

        $this->post("{$base}/test", ['test_to' => 'ana@firma.ro'])->assertSessionHas('ok');
        $this->assertSame('[TEST] Ana, 20% reducere', $this->lastMail()->getSubject());

        $this->post("{$base}/aprobare", [])->assertSessionHasErrors('confirm');
        $this->post("{$base}/aprobare", ['confirm' => '1'])->assertSessionHas('ok');
        $this->put($base, ['name' => 'Altul'])->assertStatus(422); // după aprobare nu se mai modifică
        $this->artisan('vitim:campaigns')->assertSuccessful();

        $mail = $this->lastMail();
        $this->assertSame('Maria, 20% reducere', $mail->getSubject());
        $this->assertSame('maria@ex.ro', $mail->getTo()[0]->getAddress());
        $this->assertStringContainsString('Bună Maria', $mail->getTextBody());
        $this->assertStringContainsString('Podreg SRL · CUI RO123', $mail->getTextBody());
        $this->assertStringContainsString('<strong>Reducere</strong>', $mail->getHtmlBody());
        $unsubscribe = trim((string) $mail->getHeaders()->get('List-Unsubscribe')?->getBodyAsString(), '<>');
        $this->assertStringContainsString('/d/', $unsubscribe);
        $this->assertSame('List-Unsubscribe=One-Click', $mail->getHeaders()->get('List-Unsubscribe-Post')?->getBodyAsString());

        $this->tenant()->runAs($org, function () use ($campaign): void {
            $this->assertSame('completed', $campaign->fresh()->status);
            $this->assertSame(['excluded' => 2, 'sent' => 1], collect($campaign->stats())->sortKeys()->all());
        });

        // dezabonare dintr-un click (POST trimis de Gmail, fără CSRF și fără sesiune)
        auth()->logout();
        $path = (string) parse_url($unsubscribe, PHP_URL_PATH);
        $this->get($path)->assertOk()->assertSee('Podreg SRL')->assertSee('Da, dezabonează-mă');
        $this->post($path, ['List-Unsubscribe' => 'One-Click'])->assertOk();
        $this->tenant()->runAs($org, function () use ($maria): void {
            $this->assertSame(ConsentStatus::Revoked, app(ConsentService::class)->current($maria, Channel::Email, ConsentPurpose::Marketing));
            $this->assertTrue(Suppression::query()->where('channel', 'email')->exists());
            $this->assertSame('unsubscribed', CampaignRecipient::query()->where('contact_id', $maria->id)->sole()->status);
        });
        $this->get($path)->assertSee('Te-ai dezabonat');

        // o nouă campanie nu o mai atinge; nici un import cu acord nu o readuce
        $this->actingAs($owner);
        $csv = UploadedFile::fake()->createWithContent('lista.csv', "prenume;email\nMaria;maria@ex.ro\nVlad;vlad@ex.ro\n");
        $this->post("/app/{$org->slug}/contacte/import", ['file' => $csv, 'consent' => ['email'], 'evidence' => 'formular site', 'declare' => '1'])
            ->assertSessionHas('ok', fn ($m) => str_contains($m, '1 contacte noi') && str_contains($m, '1 adrese dezabonate'));
        $second = $this->campaign($org, 'email', ['name' => 'A doua', 'subject' => 'S', 'body' => 'B']);
        $this->post("/app/{$org->slug}/campanii/{$second->id}/aprobare", ['confirm' => '1'])->assertSessionHas('ok', fn ($m) => str_contains($m, 'către 1 destinatari'));
    }

    public function test_hourly_limit_and_import_requires_declared_consent(): void
    {
        [$org, $owner] = $this->firm();
        $this->connectEmail($org, $owner, 10);
        $rows = "email,prenume\n".implode("\n", array_map(fn ($i) => "c{$i}@ex.ro,C{$i}", range(1, 12)));
        $this->post("/app/{$org->slug}/contacte/import", ['file' => UploadedFile::fake()->createWithContent('a.csv', $rows), 'consent' => ['email']])
            ->assertSessionHasErrors(['evidence', 'declare']);
        $this->post("/app/{$org->slug}/contacte/import", ['file' => UploadedFile::fake()->createWithContent('a.csv', $rows), 'consent' => ['email'], 'evidence' => 'abonați newsletter', 'declare' => '1'])
            ->assertSessionHas('ok');
        $this->tenant()->runAs($org, fn () => $this->assertSame(12, Contact::query()->count()));

        $c = $this->campaign($org, 'email', ['name' => 'Mare', 'subject' => 'S', 'body' => 'B']);
        $this->post("/app/{$org->slug}/campanii/{$c->id}/aprobare", ['confirm' => '1'])->assertSessionHas('ok');
        $this->artisan('vitim:campaigns');
        $this->assertCount(10, $this->mails, 'limita de 10 pe oră');
        $this->artisan('vitim:campaigns');
        $this->assertCount(10, $this->mails);
        $this->travel(61)->minutes();
        $this->artisan('vitim:campaigns');
        $this->assertCount(12, $this->mails);
        $this->tenant()->runAs($org, fn () => $this->assertSame('completed', $c->fresh()->status));
    }

    public function test_sms_via_smslink_and_auto_pause_on_repeated_errors(): void
    {
        [$org, $owner] = $this->firm();
        Http::fake(['secure.smslink.ro/*' => Http::sequence()->push('MESSAGE;1;Message sent;987654;1', 200)
            ->push('ERROR;12;Insufficient credit', 200)->push('ERROR;12;Insufficient credit', 200)->push('ERROR;12;Insufficient credit', 200)]);
        $this->actingAs($owner);
        $this->put("/app/{$org->slug}/canale/sms", ['connection_id' => 'ABC', 'password' => 'pw', 'ascii' => '1'])->assertSessionHasNoErrors();
        foreach (['Ana' => '0722000001', 'Bogdan' => '0722000002', 'Cezar' => '0722000003', 'Dana' => '0722000004', 'Elena' => '0722000005'] as $n => $p) {
            $this->contact($org, $n, null, $p, ['sms']);
        }
        $c = $this->campaign($org, 'sms', ['name' => 'SMS', 'body' => 'Bună {{prenume}}! Reduceri la cabane săptămâna asta.']);
        $this->post("/app/{$org->slug}/campanii/{$c->id}/aprobare", ['confirm' => '1'])->assertSessionHas('ok');
        $this->artisan('vitim:campaigns');

        Http::assertSent(fn (Request $r) => $r['to'] === '0722000001' && $r['connection_id'] === 'ABC'
            && str_starts_with($r['message'], 'Buna Ana! Reduceri la cabane saptamana asta.') && str_contains($r['message'], "\nDezabonare: http"));
        $this->tenant()->runAs($org, function () use ($c): void {
            $fresh = $c->fresh();
            $this->assertSame('paused', $fresh->status, 'oprită după 3 erori la rând');
            $this->assertStringContainsString('Insufficient credit', (string) $fresh->last_error);
            $this->assertSame(['failed' => 3, 'pending' => 1, 'sent' => 1], collect($fresh->stats())->sortKeys()->all());
            $this->assertSame('987654', CampaignRecipient::query()->where('status', 'sent')->sole()->external_id);
        });
    }

    public function test_whatsapp_template_webhook_statuses_and_stop(): void
    {
        [$org, $owner] = $this->firm();
        Http::fake(['graph.facebook.com/*' => Http::response(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.ABC']]])]);
        $this->actingAs($owner);
        $this->put("/app/{$org->slug}/canale/whatsapp", ['phone_number_id' => '1234567890', 'access_token' => 'EAAG-token', 'app_secret' => 'app-secret'])->assertSessionHasNoErrors();
        $maria = $this->contact($org, 'Maria', null, '0722123456', ['whatsapp']);
        $this->post("/app/{$org->slug}/campanii", ['name' => 'WA', 'channel' => 'whatsapp']);
        $c = $this->tenant()->runAs($org, fn () => Campaign::query()->sole());
        $this->put("/app/{$org->slug}/campanii/{$c->id}", ['name' => 'WA', 'template_name' => 'Oferta Mare'])->assertSessionHasErrors('template_name');
        $this->put("/app/{$org->slug}/campanii/{$c->id}", ['name' => 'WA', 'template_name' => 'oferta_primavara', 'template_language' => 'ro', 'template_variables' => "{{prenume}}\n20%"]);
        $this->post("/app/{$org->slug}/campanii/{$c->id}/aprobare", ['confirm' => '1'])->assertSessionHas('ok');
        $this->artisan('vitim:campaigns');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/1234567890/messages') && $r->hasHeader('Authorization', 'Bearer EAAG-token')
            && $r['to'] === '40722123456' && $r['template']['name'] === 'oferta_primavara'
            && $r['template']['components'][0]['parameters'] === [['type' => 'text', 'text' => 'Maria'], ['type' => 'text', 'text' => '20%']]);

        $account = $this->tenant()->runAs($org, fn () => ChannelAccount::query()->sole());
        $url = "/webhooks/whatsapp/{$account->webhook_token}";
        $this->get($url.'?hub_mode=subscribe&hub_verify_token=gresit&hub_challenge=42')->assertForbidden();
        $this->get($url.'?hub_mode=subscribe&hub_verify_token='.$account->setting('verify_token').'&hub_challenge=42')->assertOk()->assertSeeText('42');

        $send = function (array $payload, string $secret = 'app-secret') use ($url) {
            $body = (string) json_encode($payload);

            return $this->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, $secret)], $body);
        };
        $status = fn (string $s) => ['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.ABC', 'status' => $s]]]]]]]];
        $send($status('read'), 'alt-secret')->assertForbidden();
        $send($status('read'))->assertOk();
        $this->tenant()->runAs($org, fn () => $this->assertSame('read', CampaignRecipient::query()->sole()->status));
        $send($status('delivered'))->assertOk(); // statusul nu coboară
        $this->tenant()->runAs($org, fn () => $this->assertSame('read', CampaignRecipient::query()->sole()->status));

        $send(['entry' => [['changes' => [['value' => ['messages' => [['from' => '40722123456', 'type' => 'text', 'text' => ['body' => 'STOP']]]]]]]]])->assertOk();
        $this->tenant()->runAs($org, fn () => $this->assertSame(ConsentStatus::Revoked, app(ConsentService::class)->current($maria, Channel::WhatsApp, ConsentPurpose::Marketing)));
    }

    public function test_permissions_and_isolation(): void
    {
        [$org, $owner] = $this->firm();
        [$other, $otherOwner] = $this->firm('Alta');
        $this->actingAs($owner);
        $this->post("/app/{$org->slug}/campanii", ['name' => 'X', 'channel' => 'email']);
        $c = $this->tenant()->runAs($org, fn () => Campaign::query()->sole());

        $operator = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $operator->id, 'role' => OrgRole::Agent]));
        $this->actingAs($operator);
        $this->get("/app/{$org->slug}/campanii")->assertForbidden();
        $this->get("/app/{$org->slug}/canale")->assertForbidden();

        $this->actingAs($otherOwner);
        $this->get("/app/{$other->slug}/campanii/{$c->id}")->assertNotFound();
        $this->post("/app/{$other->slug}/campanii/{$c->id}/aprobare", ['confirm' => '1'])->assertNotFound();
        $this->get('/d/nuexista12')->assertOk()->assertSee('nu mai este valid');
        $this->get('/webhooks/whatsapp/'.str_repeat('x', 64).'?hub_mode=subscribe')->assertForbidden();
    }
}
