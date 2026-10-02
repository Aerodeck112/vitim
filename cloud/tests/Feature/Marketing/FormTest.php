<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\OrgRole;
use App\Messaging\Accounts\SmtpSender;
use App\Models\Contact;
use App\Models\ContactConsent;
use App\Models\ContactEvent;
use App\Models\ContactList;
use App\Models\Flow;
use App\Models\FlowRun;
use App\Models\FormSubmission;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\SignupForm;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\IssuedSiteKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

final class FormTest extends TestCase
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

    private function widget(string $path, array $body, string $origin = 'https://podreg.ro'): TestResponse
    {
        return $this->call('POST', '/widget/v1/'.$path, [], [], [], ['CONTENT_TYPE' => 'text/plain;charset=UTF-8', 'HTTP_ORIGIN' => $origin], (string) json_encode($body));
    }

    /** @return array{0: Organization, 1: User, 2: IssuedSiteKey} */
    private function firm(string $name = 'Podreg', string $domain = 'podreg.ro'): array
    {
        $org = $this->makeOrganization($name);
        $org->forceFill(['company_name' => $name.' SRL'])->save();
        [, $key] = $this->makeSite($org, $domain);
        $owner = User::factory()->create(['email' => strtolower($name).'@firma.ro', 'last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $owner->id, 'role' => OrgRole::Owner]));
        $this->actingAs($owner);

        return [$org, $owner, $key];
    }

    private function connectEmail(Organization $org): void
    {
        $this->put("/app/{$org->slug}/canale/email", ['host' => 'mail.podreg.ro', 'port' => '465', 'encryption' => 'ssl', 'username' => 'office@podreg.ro',
            'password' => 'x', 'from_email' => 'office@podreg.ro', 'from_name' => 'Podreg', 'hourly_limit' => 100])->assertSessionHasNoErrors();
    }

    private function newForm(Organization $org, string $type, array $settings = []): SignupForm
    {
        $this->post("/app/{$org->slug}/formulare", ['type' => $type])->assertRedirect();
        $form = $this->tenant()->runAs($org, fn () => SignupForm::query()->latest('id')->firstOrFail());
        $this->put("/app/{$org->slug}/formulare/{$form->id}", $settings + ['name' => 'Popup reducere', 'title' => 'Primești 10%', 'button' => 'Vreau', 'double_opt_in' => '1',
            'coupon' => 'BUNVENIT10', 'trigger' => 'delay', 'delay' => 5, 'frequency_days' => 7, 'fields' => ['first_name', 'phone'], 'sms' => '1'])->assertSessionHasNoErrors();

        return $form;
    }

    public function test_double_opt_in_signup_from_the_site(): void
    {
        [$org, , $key] = $this->firm();
        $list = $this->tenant()->runAs($org, fn () => ContactList::create(['name' => 'Newsletter']));
        $this->post("/app/{$org->slug}/automatizari", ['template' => 'welcome']);
        $this->tenant()->runAs($org, fn () => Flow::query()->sole()->forceFill(['status' => 'live'])->save());

        $form = $this->newForm($org, 'popup', ['contact_list_id' => $list->id]);
        // fără cont de email, dubla confirmare nu poate fi publicată
        $this->post("/app/{$org->slug}/formulare/{$form->id}/stare", ['status' => 'live'])->assertSessionHasErrors('form');
        $this->widget('config', ['key' => $key->publicKey])->assertOk()->assertJsonCount(0, 'forms');

        $this->connectEmail($org);
        $this->post("/app/{$org->slug}/formulare/{$form->id}/stare", ['status' => 'live'])->assertSessionHas('ok');
        $config = $this->widget('config', ['key' => $key->publicKey])->assertOk()->assertJsonPath('forms.0.id', $form->id)
            ->assertJsonPath('forms.0.content.title', 'Primești 10%')->assertJsonPath('forms.0.behavior.delay', 5);
        $this->assertStringContainsString('Podreg SRL', $config->json('forms.0.consent'));
        $this->assertArrayNotHasKey('contact_list_id', $config->json('forms.0'));
        $this->widget('config', ['key' => $key->publicKey], 'https://altsite.ro')->assertForbidden();

        $this->widget('forms/view', ['key' => $key->publicKey, 'form' => $form->id])->assertOk();
        $this->widget('forms/submit', ['key' => $key->publicKey, 'form' => $form->id, 'email' => 'nu-e-email'])->assertStatus(422);
        $this->widget('forms/submit', ['key' => $key->publicKey, 'form' => $form->id, 'email' => 'Maria@Ex.ro', 'first_name' => 'Maria', 'phone' => '0722 123 456', 'sms' => true, 'page' => 'https://podreg.ro/blog'])
            ->assertOk()->assertJson(['status' => 'confirm']);

        $this->tenant()->runAs($org, function () use ($form): void {
            $maria = Contact::query()->sole();
            $this->assertSame('Maria', $maria->first_name);
            // email: încă fără acord (așteaptă confirmarea); SMS: acord din bifa separată
            $this->assertSame(ConsentStatus::Unknown, app(ConsentService::class)->current($maria, Channel::Email, ConsentPurpose::Marketing));
            $this->assertSame(ConsentStatus::Granted, app(ConsentService::class)->current($maria, Channel::Sms, ConsentPurpose::Marketing));
            $this->assertTrue(ContactEvent::query()->where('type', 'form_submitted')->exists());
            $this->assertSame(1, $form->fresh()->views);
            $this->assertSame(1, $form->fresh()->submissions);
            $this->assertNull(FormSubmission::query()->sole()->confirmed_at);
        });

        $this->assertCount(1, $this->mails);
        $mail = $this->mails[0];
        $this->assertSame('Confirmă abonarea la Podreg SRL', $mail->getSubject());
        $this->assertSame('maria@ex.ro', $mail->getTo()[0]->getAddress());
        preg_match('#/confirmare/([A-Za-z0-9]{40})#', (string) $mail->getTextBody(), $m);
        $this->assertNotEmpty($m);

        auth()->logout();
        // deschiderea linkului (sau un scanner de linkuri) nu confirmă; doar butonul
        $this->get('/confirmare/'.$m[1])->assertOk()->assertSee('Da, confirm abonarea');
        $this->tenant()->runAs($org, fn () => $this->assertNull(FormSubmission::query()->sole()->confirmed_at));
        $this->post('/confirmare/'.$m[1])->assertOk()->assertSee('Abonarea e confirmată')->assertSee('BUNVENIT10');
        $this->post('/confirmare/'.str_repeat('a', 40))->assertOk()->assertSee('nu mai este valid');

        $this->tenant()->runAs($org, function () use ($list): void {
            $maria = Contact::query()->sole();
            $this->assertSame(ConsentStatus::Granted, app(ConsentService::class)->current($maria, Channel::Email, ConsentPurpose::Marketing));
            $consent = ContactConsent::query()->where('channel', 'email')->where('purpose', 'marketing')->latest('id')->first();
            $this->assertSame('signup_form', $consent->source);
            $this->assertTrue((bool) $consent->metadata['double_opt_in']);
            $this->assertTrue($list->members()->where('contact_id', $maria->id)->exists());
            $this->assertNotNull(FormSubmission::query()->sole()->confirmed_at);
            $this->assertTrue(ContactEvent::query()->where('type', 'subscribed')->exists());
            $this->assertSame(1, FlowRun::query()->count(), 'fluxul Bun venit a pornit');
        });

        // a doua înscriere a aceleiași persoane nu mai cere confirmare (are deja acord)
        $this->widget('forms/submit', ['key' => $key->publicKey, 'form' => $form->id, 'email' => 'maria@ex.ro'])->assertJson(['status' => 'subscribed']);
        $this->assertCount(1, $this->mails);
    }

    public function test_single_opt_in_honeypot_rate_limit_and_drafts(): void
    {
        [$org, , $key] = $this->firm();
        $form = $this->newForm($org, 'embed', ['double_opt_in' => null]);
        $this->widget('forms/submit', ['key' => $key->publicKey, 'form' => $form->id, 'email' => 'a@ex.ro'])->assertNotFound(); // ciornă
        $this->post("/app/{$org->slug}/formulare/{$form->id}/stare", ['status' => 'live'])->assertSessionHas('ok');
        $this->get("/app/{$org->slug}/formulare/{$form->id}")->assertOk()->assertSee('data-vitim-form="'.$form->id.'"', false);

        $this->widget('forms/submit', ['key' => $key->publicKey, 'form' => $form->id, 'email' => 'bot@ex.ro', 'website' => 'http://spam'])->assertJson(['status' => 'subscribed']);
        $this->widget('forms/submit', ['key' => $key->publicKey, 'form' => $form->id, 'email' => 'ion@ex.ro', 'sms' => true])->assertJson(['status' => 'subscribed']);
        $this->tenant()->runAs($org, function (): void {
            $this->assertSame(['ion@ex.ro'], Contact::query()->pluck('email')->all(), 'robotul nu a fost salvat');
            $ion = Contact::query()->sole();
            $this->assertSame(ConsentStatus::Granted, app(ConsentService::class)->current($ion, Channel::Email, ConsentPurpose::Marketing));
            $this->assertSame(ConsentStatus::Unknown, app(ConsentService::class)->current($ion, Channel::Sms, ConsentPurpose::Marketing), 'fără telefon nu există acord SMS');
        });
        $this->assertCount(0, $this->mails);
        $this->get("/app/{$org->slug}/formulare")->assertOk()->assertSee('Popup reducere');

        for ($i = 0; $i < 9; $i++) {
            $this->widget('forms/submit', ['key' => $key->publicKey, 'form' => $form->id, 'email' => "x{$i}@ex.ro"]);
        }
        $this->widget('forms/submit', ['key' => $key->publicKey, 'form' => $form->id, 'email' => 'ultim@ex.ro'])->assertStatus(429);
    }

    public function test_forms_are_isolated_between_firms(): void
    {
        [$org, $owner] = $this->firm();
        $form = $this->newForm($org, 'popup', ['double_opt_in' => null]);
        $this->post("/app/{$org->slug}/formulare/{$form->id}/stare", ['status' => 'live'])->assertSessionHas('ok');

        [$other, , $otherKey] = $this->firm('Alta', 'alta.ro');
        // cheia altei firme nu vede și nu poate folosi formularul
        $this->widget('config', ['key' => $otherKey->publicKey], 'https://alta.ro')->assertOk()->assertJsonCount(0, 'forms');
        $this->widget('forms/submit', ['key' => $otherKey->publicKey, 'form' => $form->id, 'email' => 'x@ex.ro'], 'https://alta.ro')->assertNotFound();
        $this->get("/app/{$other->slug}/formulare/{$form->id}")->assertNotFound();
        $this->put("/app/{$other->slug}/formulare/{$form->id}", ['name' => 'X'])->assertNotFound();
        $this->post("/app/{$other->slug}/formulare/{$form->id}/stare", ['status' => 'draft'])->assertNotFound();
        $this->delete("/app/{$other->slug}/formulare/{$form->id}")->assertNotFound();
        $this->tenant()->runAs($org, fn () => $this->assertSame('live', $form->fresh()->status));

        $operator = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $operator->id, 'role' => OrgRole::Agent]));
        $this->actingAs($operator);
        $this->get("/app/{$org->slug}/formulare")->assertForbidden();
        $this->actingAs($owner);
        $this->delete("/app/{$org->slug}/formulare/{$form->id}")->assertRedirect();
        $this->tenant()->runAs($org, fn () => $this->assertSame(0, SignupForm::query()->count()));
    }
}
