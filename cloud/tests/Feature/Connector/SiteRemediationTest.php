<?php

declare(strict_types=1);

namespace Tests\Feature\Connector;

use App\Enums\OrgRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Site;
use App\Models\SiteCommand;
use App\Models\SiteIssue;
use App\Models\User;
use App\Models\WorkLog;
use App\Services\IssuedSiteKey;
use App\Services\Remediation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class SiteRemediationTest extends TestCase
{
    use RefreshDatabase;

    private function signedPost(string $path, array $payload, IssuedSiteKey $key): TestResponse
    {
        $body = (string) json_encode($payload);
        $ts = (string) time();
        $nonce = Str::random(24);

        return $this->call('POST', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_VITIM_KEY' => $key->publicKey, 'HTTP_X_VITIM_TIMESTAMP' => $ts,
            'HTTP_X_VITIM_NONCE' => $nonce, 'HTTP_X_VITIM_SIGNATURE' => hash_hmac('sha256', $ts.'.'.$nonce.'.'.$body, $key->secret),
        ], $body);
    }

    private const ISSUES = [
        ['code' => 'plugin_update:akismet/akismet.php', 'severity' => 'warning', 'title' => 'Plugin de actualizat: Akismet 5.3 → 5.7', 'fix' => 'update_plugin:akismet/akismet.php'],
        ['code' => 'debug_log', 'severity' => 'critical', 'title' => 'Fișierul debug.log e public', 'details' => 'wp-content/debug.log', 'fix' => 'delete_debug_log'],
        ['code' => 'admin_username', 'severity' => 'warning', 'title' => 'Există un administrator „admin”', 'fix' => 'rm -rf /'],
    ];

    /** @return array{0: Organization, 1: Site, 2: IssuedSiteKey} */
    private function connectedSite(): array
    {
        $org = $this->makeOrganization('Podreg');
        [$site, $key] = $this->makeSite($org, 'podreg.ro');
        $this->signedPost('/connector/v1/heartbeat', ['platform' => 'wordpress', 'site_url' => 'https://podreg.ro/', 'connector_version' => '1.1.0',
            'command_url' => 'https://podreg.ro/wp-json/vitim/v1/command', 'remote_fixes' => true], $key)->assertOk();

        return [$org, $site, $key];
    }

    public function test_scan_opens_and_resolves_issues_and_drops_unknown_fixes(): void
    {
        [$org, $site, $key] = $this->connectedSite();
        $this->signedPost('/connector/v1/scan', ['issues' => self::ISSUES], $key)->assertOk()->assertJson(['open' => 3]);

        $this->tenant()->runAs($org, function () use ($site): void {
            $issues = SiteIssue::query()->where('site_id', $site->id)->get()->keyBy('code');
            $this->assertCount(3, $issues);
            $this->assertSame('delete_debug_log', $issues['debug_log']->fix);
            $this->assertNull($issues['admin_username']->fix, 'remedierile din afara listei permise sunt ignorate');
            $this->assertNotNull(Site::query()->find($site->id)->last_scan_at);
        });

        // problema care nu mai apare se închide singură
        $this->signedPost('/connector/v1/scan', ['issues' => [self::ISSUES[0]]], $key)->assertOk();
        $this->tenant()->runAs($org, function (): void {
            $this->assertSame('resolved', SiteIssue::query()->where('code', 'debug_log')->sole()->status);
            $this->assertSame(1, SiteIssue::query()->where('status', 'open')->count());
        });
        $this->signedPost('/connector/v1/scan', ['issues' => [['code' => 'x', 'severity' => 'grav', 'title' => 'x']]], $key)->assertStatus(422);
    }

    public function test_staff_fixes_an_issue_through_the_signed_plugin_call(): void
    {
        [$org, $site, $key] = $this->connectedSite();
        $this->signedPost('/connector/v1/scan', ['issues' => self::ISSUES], $key)->assertOk();
        Http::fake(['https://podreg.ro/wp-json/vitim/v1/command' => Http::response(['ok' => true, 'message' => 'debug.log șters.', 'issues' => [self::ISSUES[0]]])]);

        $staff = $this->staff();
        $this->actingAs($staff);
        $base = "/admin/clienti/{$org->slug}/site-uri/{$site->id}";
        $this->get($base)->assertOk()->assertSee('Fișierul debug.log e public')->assertSee('Șterge debug.log');

        $this->post("{$base}/remediere", ['fix' => 'delete_debug_log'])->assertRedirect($base)->assertSessionHas('ok');
        Http::assertSent(function (HttpRequest $request) use ($key): bool {
            $body = $request->body();
            $expected = hash_hmac('sha256', $request->header('X-Vitim-Timestamp')[0].'.'.$request->header('X-Vitim-Nonce')[0].'.'.$body, $key->secret);

            return $request->header('X-Vitim-Signature')[0] === $expected && json_decode($body, true)['action'] === 'delete_debug_log';
        });

        $this->tenant()->runAs($org, function () use ($staff): void {
            $command = SiteCommand::query()->sole();
            $this->assertSame(['done', 'delete_debug_log', $staff->id], [$command->status, $command->action, $command->requested_by]);
            $this->assertSame('resolved', SiteIssue::query()->where('code', 'debug_log')->sole()->status);
            $this->assertSame('Șterge debug.log', WorkLog::query()->sole()->title, 'remedierea apare în jurnalul clientului');
        });
        $this->get($base)->assertSee('reușit');
    }

    public function test_failures_and_forbidden_actions(): void
    {
        [$org, $site] = $this->connectedSite();
        Http::fake(['*' => Http::response(['ok' => false, 'message' => 'Remedierile de la distanță sunt oprite din pluginul de pe site.'], 403)]);
        $this->actingAs($this->staff());
        $base = "/admin/clienti/{$org->slug}/site-uri/{$site->id}";

        $this->post("{$base}/remediere", ['fix' => 'update_all_plugins'])->assertSessionHas('error');
        $this->tenant()->runAs($org, fn () => $this->assertSame('failed', SiteCommand::query()->sole()->status));

        $this->post("{$base}/remediere", ['fix' => 'exec:rm -rf'])->assertSessionHasErrors('fix');
        $this->post("{$base}/remediere", ['fix' => 'update_plugin'])->assertSessionHasErrors('fix'); // fără țintă
        $this->post("{$base}/remediere", ['fix' => 'update_plugin:../../wp-config.php'])->assertSessionHasErrors('fix');
        Http::assertSentCount(1);
    }

    public function test_command_url_must_be_on_the_site_domain(): void
    {
        $org = $this->makeOrganization('Podreg');
        [$site, $key] = $this->makeSite($org, 'podreg.ro');
        $this->signedPost('/connector/v1/heartbeat', ['platform' => 'wordpress', 'site_url' => 'https://podreg.ro/', 'connector_version' => '1.1.0',
            'command_url' => 'https://evil.example/wp-json/vitim/v1/command'], $key)->assertOk();
        Http::fake();
        $this->actingAs($this->staff());
        $this->post("/admin/clienti/{$org->slug}/site-uri/{$site->id}/remediere", ['fix' => 'scan'])->assertSessionHasErrors('fix');
        Http::assertNothingSent();
    }

    public function test_only_vitim_staff_of_the_right_client_reaches_the_site_page(): void
    {
        [$orgA, $siteA] = $this->connectedSite();
        $orgB = $this->makeOrganization('Alta');
        $client = User::factory()->create();
        $this->tenant()->runAs($orgA, fn () => Membership::create(['user_id' => $client->id, 'role' => OrgRole::Owner]));

        $this->actingAs($client);
        $this->get("/admin/clienti/{$orgA->slug}/site-uri/{$siteA->id}")->assertNotFound();
        $this->post("/admin/clienti/{$orgA->slug}/site-uri/{$siteA->id}/remediere", ['fix' => 'scan'])->assertNotFound();

        $this->actingAs($this->staff());
        $this->get("/admin/clienti/{$orgB->slug}/site-uri/{$siteA->id}")->assertNotFound();
    }

    public function test_plugin_version_endpoint_and_allowlist_parsing(): void
    {
        File::ensureDirectoryExists(public_path('downloads'));
        $file = public_path('downloads/vitim-connector.json');
        $previous = is_file($file) ? file_get_contents($file) : null;
        file_put_contents($file, json_encode(['version' => '1.1.0']));
        try {
            $this->getJson('/connector/v1/plugin')->assertOk()->assertJson(['version' => '1.1.0'])->assertJsonPath('download_url', asset('downloads/vitim-connector.zip'));
        } finally {
            $previous === null ? @unlink($file) : file_put_contents($file, $previous);
        }
        $this->assertSame(['update_plugin', 'akismet/akismet.php'], Remediation::parse('update_plugin:akismet/akismet.php'));
        $this->assertNull(Remediation::parse('scan:x'));
        $this->assertNull(Remediation::parse('shell'));
    }
}
