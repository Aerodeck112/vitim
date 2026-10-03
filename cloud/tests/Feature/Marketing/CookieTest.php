<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Enums\OrgRole;
use App\Models\CookieConsent;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Services\IssuedSiteKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class CookieTest extends TestCase
{
    use RefreshDatabase;

    private function widget(string $path, array $body, string $origin = 'https://podreg.ro'): TestResponse
    {
        return $this->call('POST', '/widget/v1/'.$path, [], [], [], ['CONTENT_TYPE' => 'text/plain;charset=UTF-8', 'HTTP_ORIGIN' => $origin, 'REMOTE_ADDR' => '203.0.113.9'], (string) json_encode($body));
    }

    /** @return array{0: Organization, 1: Site, 2: IssuedSiteKey, 3: User} */
    private function firm(string $name = 'Podreg', string $domain = 'podreg.ro'): array
    {
        $org = $this->makeOrganization($name);
        $org->forceFill(['company_name' => $name.' SRL'])->save();
        [$site, $key] = $this->makeSite($org, $domain);
        $owner = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $owner->id, 'role' => OrgRole::Owner]));
        $this->actingAs($owner);

        return [$org, $site, $key, $owner];
    }

    public function test_banner_settings_payload_and_consent_log(): void
    {
        [$org, $site, $key] = $this->firm();
        $this->widget('config', ['key' => $key->publicKey])->assertOk()->assertJsonPath('cookies', null);
        $this->get("/app/{$org->slug}/cookie-uri")->assertOk()->assertSee('Afișează bannerul VITIM');

        $this->put("/app/{$org->slug}/cookie-uri/{$site->id}", ['enabled' => '1', 'layout' => 'box', 'color' => '#e11d48', 'title' => 'Cookie-uri',
            'text' => 'Text', 'policy_url' => 'https://podreg.ro/cookies', 'services' => ['ga4', 'meta', 'necunoscut'], 'consent_days' => 180, 'reopen' => '1', 'gcm' => '1',
            'custom' => [['name' => 'Smartsupp <b>', 'provider' => 'Smartsupp', 'category' => 'preferences', 'cookies' => 'ssupp.vid', 'duration' => '6 luni'], ['name' => '']]])->assertSessionHas('ok');
        $cfg = $this->widget('config', ['key' => $key->publicKey])->assertOk()->json('cookies');
        $this->assertSame(1, $cfg['version']);
        $this->assertSame('box', $cfg['layout']);
        $this->assertSame('Podreg SRL', $cfg['company']);
        $cats = collect($cfg['categories'])->keyBy('key');
        $this->assertSame(['necessary', 'preferences', 'statistics', 'marketing'], $cats->keys()->all());
        $this->assertContains('_ga', $cats['statistics']['cookies'][0]);
        $this->assertSame('vitim_ct', collect($cats['marketing']['cookies'])->firstWhere('name', 'Recunoașterea abonaților')['cookies']);
        $this->assertTrue(collect($cats['marketing']['cookies'])->contains('cookies', '_fbp'));
        $this->assertSame('Smartsupp', $cats['preferences']['cookies'][0]['name']);

        // reconsimțământ: versiunea crește
        $this->put("/app/{$org->slug}/cookie-uri/{$site->id}", ['enabled' => '1', 'services' => ['ga4'], 'reconsent' => '1'])->assertSessionHas('ok');
        $this->assertSame(2, $this->widget('config', ['key' => $key->publicKey])->json('cookies.version'));

        $id = (string) Str::uuid();
        $this->widget('consent', ['key' => $key->publicKey, 'consent_id' => $id, 'action' => 'custom', 'statistics' => true, 'marketing' => false, 'version' => 2, 'page' => 'https://podreg.ro/'])->assertOk();
        $this->widget('consent', ['key' => $key->publicKey, 'consent_id' => 'x', 'action' => 'custom'])->assertStatus(422);
        $this->widget('consent', ['key' => $key->publicKey, 'consent_id' => $id, 'action' => 'reject_all'], 'https://alt.ro')->assertForbidden();
        $this->tenant()->runAs($org, function () use ($id): void {
            $c = CookieConsent::query()->sole();
            $this->assertSame($id, $c->consent_id);
            $this->assertTrue($c->statistics);
            $this->assertFalse($c->marketing);
            $this->assertSame(2, $c->policy_version);
            $this->assertNotSame('203.0.113.9', $c->ip_hash);
            $this->assertSame(32, strlen((string) $c->ip_hash));
        });
        $this->get("/app/{$org->slug}/cookie-uri")->assertOk()->assertSee('a ales din setări');
        $csv = $this->get("/app/{$org->slug}/cookie-uri/{$site->id}/registru")->assertOk()->streamedContent();
        $this->assertStringContainsString($id.';custom;nu;da;nu;2', $csv);
        $this->assertStringNotContainsString('203.0.113.9', $csv);
    }

    public function test_heartbeat_reports_banner_and_isolation(): void
    {
        [$org, $site, $key, $owner] = $this->firm();
        $this->put("/app/{$org->slug}/cookie-uri/{$site->id}", ['enabled' => '1'])->assertSessionHas('ok');
        $body = (string) json_encode(['platform' => 'wordpress', 'site_url' => 'https://podreg.ro/', 'connector_version' => '1.6.0']);
        $ts = (string) time();
        $nonce = Str::random(24);
        $this->call('POST', '/connector/v1/heartbeat', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_VITIM_KEY' => $key->publicKey, 'HTTP_X_VITIM_TIMESTAMP' => $ts,
            'HTTP_X_VITIM_NONCE' => $nonce, 'HTTP_X_VITIM_SIGNATURE' => hash_hmac('sha256', $ts.'.'.$nonce.'.'.$body, $key->secret)], $body)
            ->assertOk()->assertJson(['cookie_banner' => true]);

        [$other, , $otherKey, $stranger] = $this->firm('Alta', 'alta.ro');
        $this->actingAs($stranger);
        $this->put("/app/{$other->slug}/cookie-uri/{$site->id}", ['enabled' => '0'])->assertNotFound();
        $this->get("/app/{$other->slug}/cookie-uri/{$site->id}/registru")->assertNotFound();
        $this->get("/app/{$other->slug}/cookie-uri?site={$site->id}")->assertOk()->assertDontSee('podreg.ro');
        $this->widget('consent', ['key' => $otherKey->publicKey, 'consent_id' => (string) Str::uuid(), 'action' => 'accept_all'], 'https://alta.ro')->assertOk();
        $this->tenant()->runAs($org, fn () => $this->assertSame(0, CookieConsent::query()->count()));
        $this->tenant()->runAs($org, fn () => $this->assertTrue(Site::query()->find($site->id)->cookie_config['enabled']));

        $agent = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $agent->id, 'role' => OrgRole::Agent]));
        $this->actingAs($agent);
        $this->get("/app/{$org->slug}/cookie-uri")->assertForbidden();
    }
}
