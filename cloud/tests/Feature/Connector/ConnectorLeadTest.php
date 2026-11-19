<?php

declare(strict_types=1);

namespace Tests\Feature\Connector;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Models\Contact;
use App\Models\Lead;
use App\Services\ConnectionCode;
use App\Services\ConsentService;
use App\Services\IssuedSiteKey;
use App\Support\Vitim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Cererile din formularele site-urilor făcute de VITIM ajung în panou ca lead-uri, în firma site-ului. */
final class ConnectorLeadTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $payload */
    private function signed(array $payload, IssuedSiteKey $key, ?string $secret = null): TestResponse
    {
        $body = (string) json_encode($payload);
        $ts = (string) time();
        $nonce = Str::random(24);

        return $this->call('POST', '/connector/v1/lead', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_VITIM_KEY' => $key->publicKey, 'HTTP_X_VITIM_TIMESTAMP' => $ts,
            'HTTP_X_VITIM_NONCE' => $nonce, 'HTTP_X_VITIM_SIGNATURE' => hash_hmac('sha256', $ts.'.'.$nonce.'.'.$body, $secret ?? $key->secret)], $body);
    }

    public function test_a_form_request_becomes_a_contact_and_a_lead(): void
    {
        $org = $this->makeOrganization('Optoplus');
        $other = $this->makeOrganization('Altă firmă');
        [$site, $key] = $this->makeSite($org, 'optoplus.ro');
        $form = ['ref' => 'cerere-101', 'name' => 'Ana Pop', 'email' => 'ana@ex.ro', 'phone' => '0722 123 456', 'message' => 'Aș vrea o programare pentru control.',
            'intent' => 'appointment', 'page' => '/contact', 'marketing_consent' => true, 'consent_text' => 'Vreau să primesc noutăți'];

        $this->signed($form, $key, 'secret-gresit')->assertUnauthorized();
        $this->signed(['ref' => 'x', 'message' => 'fără date de contact'], $key)->assertStatus(422);
        $this->signed($form, $key)->assertOk()->assertJson(['ok' => true]);
        $this->signed($form, $key)->assertOk()->assertJson(['duplicate' => true]); // retrimiterea nu dublează

        $this->tenant()->runAs($org, function () use ($site): void {
            $lead = Lead::query()->with('contact')->sole();
            $this->assertSame(['website_form', 'appointment', $site->id], [$lead->source, $lead->intent->value, $lead->site_id]);
            $this->assertStringContainsString('Aș vrea o programare', (string) $lead->summary);
            $this->assertSame(['Ana', 'Pop', 'ana@ex.ro'], [$lead->contact->first_name, $lead->contact->last_name, $lead->contact->email]);
            $this->assertSame(ConsentStatus::Granted, app(ConsentService::class)->current($lead->contact, Channel::Email, ConsentPurpose::Marketing));
        });

        // a doua cerere a aceluiași om: același contact, lead nou; fără bifă nu se înregistrează niciun acord
        $this->signed(['ref' => 'cerere-102', 'phone' => '0722123456', 'message' => 'Mai am o întrebare.'], $key)->assertOk();
        $this->signed(['ref' => 'cerere-103', 'name' => 'Dan', 'email' => 'dan@ex.ro'], $key)->assertOk();
        $this->tenant()->runAs($org, function (): void {
            $this->assertSame(2, Contact::query()->count());
            $this->assertSame(3, Lead::query()->count());
            $dan = Contact::query()->where('first_name', 'Dan')->sole();
            $this->assertSame(ConsentStatus::Unknown, app(ConsentService::class)->current($dan, Channel::Email, ConsentPurpose::Marketing));
        });
        $this->assertSame(0, $this->tenant()->runAs($other, fn () => Lead::query()->count() + Contact::query()->count()), 'nimic în altă firmă');
    }

    public function test_the_laravel_kit_signs_requests_the_panel_accepts(): void
    {
        require_once base_path('../connectors/laravel/Vitim.php');
        $org = $this->makeOrganization('Optoplus');
        [, $key] = $this->makeSite($org, 'optoplus.ro');

        // fără cod: nu face nimic și nu strică pagina
        $this->assertSame('', (string) Vitim::widget());
        $this->assertFalse(Vitim::lead(['email' => 'ana@ex.ro']));

        config(['services.vitim.code' => ConnectionCode::encode('https://ai.vitim.ro', $key->publicKey, $key->secret), 'app.url' => 'https://www.optoplus.ro']);
        $this->assertStringContainsString('https://ai.vitim.ro/widget/v1/loader.js" data-site="'.$key->publicKey.'"', (string) Vitim::widget());

        // cererile trimise de fișier sunt trecute prin ruta reală a panoului, cu semnătura lor
        Http::fake(function ($request) {
            $server = ['CONTENT_TYPE' => 'application/json'];
            foreach (['X-Vitim-Key', 'X-Vitim-Timestamp', 'X-Vitim-Nonce', 'X-Vitim-Signature'] as $h) {
                $server['HTTP_'.strtoupper(str_replace('-', '_', $h))] = $request->header($h)[0];
            }
            $response = $this->call('POST', (string) parse_url($request->url(), PHP_URL_PATH), [], [], [], $server, $request->body());

            return Http::response((string) $response->getContent(), $response->getStatusCode());
        });
        $this->assertTrue(Vitim::heartbeat());
        $this->assertTrue(Vitim::lead(['name' => 'Ana Pop', 'email' => 'ana@ex.ro', 'message' => 'Ofertă pentru lentile', 'intent' => 'quote_request']));
        $this->assertTrue(Vitim::worklog('updates', 'Actualizare aplicație la 1.4.0'));
        $this->assertSame('quote_request', $this->tenant()->runAs($org, fn () => Lead::query()->sole())->intent->value);
    }
}
