<?php

declare(strict_types=1);

namespace Tests\Feature\Connector;

use App\Models\Site;
use App\Models\WorkLog;
use App\Services\ConnectionCode;
use App\Services\IssuedSiteKey;
use App\Services\SiteKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class ConnectorApiTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $payload */
    private function signed(string $path, array $payload, IssuedSiteKey $key, ?string $secret = null, ?string $nonce = null, ?int $time = null): TestResponse
    {
        $body = (string) json_encode($payload);
        $ts = (string) ($time ?? time());
        $nonce ??= Str::random(24);

        return $this->call('POST', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_VITIM_KEY' => $key->publicKey,
            'HTTP_X_VITIM_TIMESTAMP' => $ts,
            'HTTP_X_VITIM_NONCE' => $nonce,
            'HTTP_X_VITIM_SIGNATURE' => hash_hmac('sha256', $ts.'.'.$nonce.'.'.$body, $secret ?? $key->secret),
        ], $body);
    }

    private function heartbeat(string $url = 'https://www.podreg.ro'): array
    {
        return ['platform' => 'wordpress', 'site_url' => $url, 'connector_version' => '1.0.0', 'php_version' => '8.3.10',
            'core_version' => '6.8.1', 'core_update' => '6.8.2', 'theme' => 'Astra', 'plugins_total' => 18,
            'plugin_updates' => [['name' => 'Elementor', 'from' => '3.20', 'to' => '3.21']], 'theme_updates' => 0, 'https' => true];
    }

    public function test_heartbeat_records_site_health(): void
    {
        $org = $this->makeOrganization('Podreg');
        [$site, $key] = $this->makeSite($org, 'podreg.ro');

        $this->signed('/connector/v1/heartbeat', $this->heartbeat(), $key)->assertOk()->assertJson(['ok' => true, 'verified' => true]);
        $fresh = Site::withoutTenancy()->find($site->id);
        $this->assertTrue($fresh->isOnline());
        $this->assertSame('verified', $fresh->verification_status);
        $this->assertSame('1.0.0', $fresh->connector_version);
        $this->assertSame('6.8.1', $fresh->health['core_version']);
        $this->assertSame('Elementor', $fresh->health['plugin_updates'][0]['name']);

        // alt domeniu raportat → marcat, nu ignorat
        $this->signed('/connector/v1/heartbeat', $this->heartbeat('https://alt-site.ro'), $key)->assertOk()->assertJson(['verified' => false]);
        $this->assertSame('mismatch', Site::withoutTenancy()->find($site->id)->verification_status);
    }

    public function test_requests_without_a_valid_signature_are_rejected(): void
    {
        $org = $this->makeOrganization('Podreg');
        [$site, $key] = $this->makeSite($org, 'podreg.ro');

        $this->signed('/connector/v1/heartbeat', $this->heartbeat(), $key, secret: 'sk_gresit')->assertStatus(401);
        $this->signed('/connector/v1/heartbeat', $this->heartbeat(), $key, time: time() - 3600)->assertStatus(401);
        $this->postJson('/connector/v1/heartbeat', $this->heartbeat())->assertStatus(401);
        // aceeași cerere retrimisă (replay) e refuzată
        $nonce = Str::random(24);
        $time = time();
        $this->signed('/connector/v1/heartbeat', $this->heartbeat(), $key, nonce: $nonce, time: $time)->assertOk();
        $this->signed('/connector/v1/heartbeat', $this->heartbeat(), $key, nonce: $nonce, time: $time)->assertStatus(401);
        // după schimbarea cheilor, cheia veche nu mai merge
        $this->tenant()->runAs($org, fn () => app(SiteKeyService::class)->rotate($site));
        $this->signed('/connector/v1/heartbeat', $this->heartbeat(), $key)->assertStatus(401);
    }

    public function test_worklog_entries_are_saved_once_for_the_right_client(): void
    {
        $podreg = $this->makeOrganization('Podreg');
        $other = $this->makeOrganization('Alta Firma');
        [$site, $key] = $this->makeSite($podreg, 'podreg.ro');
        $entries = ['entries' => [
            ['ref' => 'plugin:elementor:3.21', 'category' => 'updates', 'title' => 'Elementor actualizat 3.20 → 3.21', 'performed_at' => '2026-10-01T10:00:00+03:00'],
            ['ref' => 'core:6.8.2', 'category' => 'updates', 'title' => 'WordPress actualizat la 6.8.2'],
        ]];

        $this->signed('/connector/v1/worklog', $entries, $key)->assertOk()->assertJson(['saved' => 2, 'skipped' => 0]);
        $this->signed('/connector/v1/worklog', $entries, $key)->assertOk()->assertJson(['saved' => 0, 'skipped' => 2]);
        $this->signed('/connector/v1/worklog', ['entries' => [['ref' => 'x', 'category' => 'nu-exista', 'title' => 'x']]], $key)->assertStatus(422);

        $this->tenant()->runAs($podreg, function () use ($site): void {
            $logs = WorkLog::query()->orderBy('id')->get();
            $this->assertCount(2, $logs);
            $this->assertSame([$site->id, 'plugin', true], [$logs[0]->site_id, $logs[0]->source, $logs[0]->visible_to_client]);
        });
        $this->tenant()->runAs($other, fn () => $this->assertSame(0, WorkLog::query()->count()));
    }

    public function test_connection_code_round_trip(): void
    {
        $code = ConnectionCode::encode('https://ai.vitim.ro/', 'pk_abc', 'sk_def');
        $this->assertStringStartsWith('VITIM1-', $code);
        $this->assertSame(['u' => 'https://ai.vitim.ro', 'k' => 'pk_abc', 's' => 'sk_def'], ConnectionCode::decode($code));
        $this->assertNull(ConnectionCode::decode('alt-cod'));
    }
}
