<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Enums\OrgRole;
use App\Enums\PlatformRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Site;
use App\Models\SiteIssue;
use App\Models\User;
use App\Monitoring\FleetMonitor;
use App\Services\UpdateAutopilot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Actualizările automate ale pluginurilor: backup înainte, verificare după, alertă dacă site-ul nu se mai deschide. */
final class UpdateAutopilotTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $actions = [];

    private bool $backupOk = true;

    private string $homeAfter = '<html><body><h1>Shop</h1></body></html>';

    private function fake(): void
    {
        Http::fake(function (Request $r) {
            if (str_ends_with($r->url(), '/wp-json/vitim/v1/command')) {
                $action = (string) json_decode($r->body(), true)['action'];
                $this->actions[] = $action;

                return match ($action) {
                    'backup' => Http::response($this->backupOk ? ['ok' => true, 'message' => 'Backup făcut.'] : ['ok' => false, 'message' => 'Spațiu insuficient.']),
                    'update_all_plugins' => Http::response(['ok' => true, 'message' => '2 plugin(uri) actualizat(e).']),
                    default => Http::response(['ok' => true, 'message' => 'Scanat.', 'issues' => []]),
                };
            }
            $updated = in_array('update_all_plugins', $this->actions, true);

            return $r->url() === 'https://shop.ro/' ? Http::response($updated ? $this->homeAfter : '<html><body><h1>Shop</h1></body></html>', 200, ['Content-Type' => 'text/html'])
                : Http::response('Not found', 404);
        });
    }

    /** @return array{0: Organization, 1: Site} */
    private function site(bool $auto = true): array
    {
        $org = $this->makeOrganization('Shop');
        [$site] = $this->makeSite($org, 'shop.ro');
        $site->forceFill(['platform' => 'wordpress', 'connector_version' => '1.7.0', 'last_seen_at' => now(), 'update_autopilot' => $auto,
            'health' => ['command_url' => 'https://shop.ro/wp-json/vitim/v1/command', 'remote_fixes' => true]])->save();
        $this->tenant()->runAs($org, function () use ($site): void {
            foreach (['plugin_update:woocommerce/woocommerce.php', 'plugin_update:akismet/akismet.php'] as $code) {
                SiteIssue::create(['site_id' => $site->id, 'code' => $code, 'category' => 'updates', 'source' => 'plugin', 'severity' => 'warning',
                    'title' => 'Plugin de actualizat', 'status' => 'open', 'first_seen_at' => now(), 'last_seen_at' => now()]);
            }
        });

        return [$org, $site->fresh()];
    }

    private function night(): void
    {
        $this->travelTo(now()->setTimezone('Europe/Bucharest')->setTime(2, 30));
    }

    public function test_plugins_update_at_night_once_a_week_with_a_backup_first(): void
    {
        $this->fake();
        $this->travelTo(now()->setTimezone('Europe/Bucharest')->setTime(14, 0));
        [, $site] = $this->site();

        $this->artisan('vitim:maintain')->assertSuccessful();
        $this->assertSame([], $this->actions, 'ziua nu se actualizează nimic');

        $this->night();
        Site::withoutTenancy()->whereKey($site->id)->update(['last_seen_at' => now()]);
        $this->artisan('vitim:maintain')->assertSuccessful();
        $this->assertSame(['backup', 'update_all_plugins', 'scan'], $this->actions);
        $fresh = Site::withoutTenancy()->find($site->id);
        $this->assertStringContainsString('2 plugin(uri) actualizat(e)', $fresh->update_autopilot_result);
        $this->assertStringContainsString('Backup făcut înainte', $fresh->update_autopilot_result);

        $this->artisan('vitim:maintain')->assertSuccessful();
        $this->assertCount(3, $this->actions, 'o singură rulare pe săptămână');
    }

    public function test_nothing_is_updated_without_a_backup_or_when_switched_off(): void
    {
        $this->fake();
        $this->night();
        [, $off] = $this->site(false);
        $this->artisan('vitim:maintain')->assertSuccessful();
        $this->assertSame([], $this->actions, 'oprit implicit');

        $this->backupOk = false;
        Site::withoutTenancy()->whereKey($off->id)->update(['update_autopilot' => true]);
        $this->artisan('vitim:maintain')->assertSuccessful();
        $this->assertSame(['backup'], $this->actions);
        $this->assertStringContainsString('nu am actualizat nimic', Site::withoutTenancy()->find($off->id)->update_autopilot_result);
    }

    public function test_a_site_broken_by_an_update_becomes_a_critical_alert(): void
    {
        $this->fake();
        $this->night();
        $this->homeAfter = '<html><body><p>There has been a critical error on this website.</p></body></html>';
        [$org, $site] = $this->site();

        $result = $this->tenant()->runAs($org, fn () => app(UpdateAutopilot::class)->run($site));
        $this->assertStringStartsWith('ATENȚIE', $result);
        $this->assertNotContains('scan', $this->actions);
        $report = app(FleetMonitor::class)->report();
        $this->assertSame(1, $report['critical']);
        $this->assertStringContainsString('nu se mai deschide corect după actualizarea automată', $report['items'][0]['title']);

        // după o actualizare reușită, problema se închide singură
        $this->homeAfter = '<html><body><h1>Shop</h1></body></html>';
        $this->tenant()->runAs($org, fn () => app(UpdateAutopilot::class)->run($site->fresh()));
        $this->assertSame(0, app(FleetMonitor::class)->report()['critical']);
    }

    public function test_only_vitim_staff_switch_and_run_it(): void
    {
        $this->fake();
        [$org, $site] = $this->site(false);
        $base = "/admin/clienti/{$org->slug}/site-uri/{$site->id}";
        $owner = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $owner->id, 'role' => OrgRole::Owner]));
        $this->actingAs($owner)->post("{$base}/pilot-actualizari", ['on' => 1])->assertNotFound();
        $this->post("{$base}/pilot-actualizari/ruleaza")->assertNotFound();
        $this->post('/admin/monitorizare/actualizari', ['on' => 1])->assertNotFound();
        $this->assertSame([], $this->actions);

        $this->actingAs($this->staff(PlatformRole::VitimAdmin))->post('/admin/monitorizare/actualizari', ['on' => 1])->assertForbidden();
        $this->actingAs($this->staff());
        $this->get($base)->assertOk()->assertSee('Actualizări automate')->assertSee('pluginuri de actualizat acum: 2');
        $this->post('/admin/monitorizare/actualizari', ['on' => 1])->assertSessionHas('ok', 'Actualizările automate au fost pornite pe 1 site-uri.');
        $this->assertTrue(Site::withoutTenancy()->find($site->id)->update_autopilot);
        $this->post("{$base}/pilot-actualizari", ['on' => 0])->assertSessionHas('ok');
        $this->post("{$base}/pilot-actualizari/ruleaza")->assertSessionHas('ok');
        $this->assertSame(['backup', 'update_all_plugins', 'scan'], $this->actions);
        $this->get('/admin/monitorizare')->assertOk()->assertSee('Actualizări automate ale pluginurilor');
    }
}
