<?php

declare(strict_types=1);

namespace Tests\Feature\Connector;

use App\Audit\Guidance;
use App\Enums\OrgRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Site;
use App\Models\SiteBackup;
use App\Models\SiteIssue;
use App\Models\User;
use App\Models\WorkLog;
use App\Services\IssuedSiteKey;
use App\Services\Remediation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class BackupTest extends TestCase
{
    use RefreshDatabase;

    private function signed(string $path, array $payload, IssuedSiteKey $key): TestResponse
    {
        $body = (string) json_encode($payload);
        $ts = (string) time();
        $nonce = Str::random(24);

        return $this->call('POST', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_VITIM_KEY' => $key->publicKey, 'HTTP_X_VITIM_TIMESTAMP' => $ts,
            'HTTP_X_VITIM_NONCE' => $nonce, 'HTTP_X_VITIM_SIGNATURE' => hash_hmac('sha256', $ts.'.'.$nonce.'.'.$body, $key->secret),
        ], $body);
    }

    private function ok(string $when = 'now'): array
    {
        $t = now()->modify($when);

        return ['status' => 'ok', 'verified' => true, 'started_at' => $t->toIso8601String(), 'finished_at' => $t->copy()->addMinutes(2)->toIso8601String(),
            'db_bytes' => 5_242_880, 'files_bytes' => 157_286_400, 'files_count' => 4210, 'location' => '/home/podreg/vitim-backups/podreg.ro/20261001-030512', 'kept' => 7];
    }

    /** @return array{0: Organization, 1: Site, 2: IssuedSiteKey} */
    private function site(string $schedule = 'daily'): array
    {
        $org = $this->makeOrganization('Podreg');
        [$site, $key] = $this->makeSite($org, 'podreg.ro');
        $this->signed('/connector/v1/heartbeat', ['platform' => 'wordpress', 'site_url' => 'https://podreg.ro/', 'connector_version' => '1.2.0',
            'command_url' => 'https://podreg.ro/wp-json/vitim/v1/command', 'backup_schedule' => $schedule, 'backup_keep' => 7], $key)->assertOk();

        return [$org, $site, $key];
    }

    private function openCodes($org): array
    {
        return $this->tenant()->runAs($org, fn () => SiteIssue::query()->where('status', 'open')->where('source', 'backup')->pluck('severity', 'code')->all());
    }

    public function test_no_backup_yet_then_verified_backup_is_recorded_and_logged_weekly(): void
    {
        [$org, $site, $key] = $this->site();
        $this->assertSame(['backup.none' => 'warning'], $this->openCodes($org));

        $this->signed('/connector/v1/backup', $this->ok(), $key)->assertOk();
        $this->signed('/connector/v1/backup', $this->ok('+1 day'), $key)->assertOk();

        $this->assertSame([], $this->openCodes($org));
        $this->tenant()->runAs($org, function () use ($site): void {
            $this->assertSame(2, SiteBackup::query()->where('site_id', $site->id)->count());
            $log = WorkLog::query()->where('category', 'backup')->get();
            $this->assertCount(1, $log, 'o singură intrare în jurnal pe săptămână');
            $this->assertStringContainsString('5 MB', $log[0]->title);
            $this->assertStringContainsString('150 MB', $log[0]->title);
            $this->assertSame(100, Site::query()->find($site->id)->scores['backup']);
        });
    }

    public function test_failed_and_stale_backups_open_issues_with_guidance(): void
    {
        [$org, $site, $key] = $this->site();
        $this->signed('/connector/v1/backup', $this->ok('-4 days'), $key)->assertOk();
        $this->assertSame(['backup.stale' => 'warning'], $this->openCodes($org));

        $this->signed('/connector/v1/backup', ['status' => 'failed', 'started_at' => now()->toIso8601String(), 'error' => 'Spațiu insuficient pe disc'], $key)->assertOk();
        $codes = $this->openCodes($org);
        $this->assertSame('critical', $codes['backup.failed']);
        $this->assertArrayHasKey('backup.stale', $codes);
        $this->assertNotNull(Guidance::howTo('backup.failed'));

        $this->signed('/connector/v1/backup', $this->ok(), $key)->assertOk();
        $this->assertSame([], $this->openCodes($org));

        // backup oprit din plugin → nicio alertă
        $this->signed('/connector/v1/heartbeat', ['platform' => 'wordpress', 'site_url' => 'https://podreg.ro/', 'connector_version' => '1.2.0', 'backup_schedule' => 'off'], $key)->assertOk();
        $this->assertSame([], $this->openCodes($org));
    }

    public function test_hourly_check_flags_sites_that_stopped_reporting(): void
    {
        [$org, , $key] = $this->site();
        $this->signed('/connector/v1/backup', $this->ok(), $key)->assertOk();
        $this->travel(9)->days();
        $this->artisan('vitim:backups-check')->assertSuccessful();
        $this->assertSame(['backup.stale' => 'critical'], $this->openCodes($org));
    }

    public function test_backup_is_an_allowed_remote_action_and_reports_are_isolated(): void
    {
        $this->assertSame(['backup', null], Remediation::parse('backup'));
        [$org, $site, $key] = $this->site();
        $this->signed('/connector/v1/backup', $this->ok(), $key)->assertOk();
        $this->signed('/connector/v1/backup', ['status' => 'grozav', 'started_at' => 'ieri'], $key)->assertStatus(422);

        $other = $this->makeOrganization('Alta');
        $this->tenant()->runAs($other, fn () => $this->assertSame(0, SiteBackup::query()->count()));

        $client = User::factory()->create();
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $client->id, 'role' => OrgRole::Viewer]));
        $this->actingAs($client);
        $this->get("/app/{$org->slug}/site-uri/{$site->id}")->assertOk()->assertSee('Backup-uri')->assertSee('verificat')
            ->assertDontSee('/home/podreg/vitim-backups', false);

        $this->actingAs($this->staff());
        $this->get("/admin/clienti/{$org->slug}/site-uri/{$site->id}")->assertOk()->assertSee('/home/podreg/vitim-backups')->assertSee('Backup acum');
    }
}
