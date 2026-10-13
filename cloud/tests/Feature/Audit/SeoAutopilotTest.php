<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Enums\OrgRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Site;
use App\Models\SiteCommand;
use App\Models\User;
use App\Models\WorkLog;
use App\Services\SiteAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Pilotul automat SEO: remedierile sigure se aplică singure o dată pe săptămână, după auditul zilnic. */
final class SeoAutopilotTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $targets = [];

    private function fake(): void
    {
        $home = '<html><head><meta name="robots" content="noindex"></head><body><img src="a.jpg"><h1>Shop</h1></body></html>';
        Http::fake(function (Request $r) use ($home) {
            if (str_ends_with($r->url(), '/wp-json/vitim/v1/command')) {
                $body = json_decode($r->body(), true);
                $this->targets[] = (string) $body['target'];

                return Http::response(['ok' => true, 'message' => 'Rezolvat.', 'issues' => [], 'applied' => explode('.', (string) $body['target'])]);
            }

            return match ($r->url()) {
                'https://shop.ro/' => Http::response($home, 200, ['Content-Type' => 'text/html']),
                'https://shop.ro/robots.txt' => Http::response("User-agent: *\nDisallow: /", 200, ['Content-Type' => 'text/plain']),
                default => Http::response('Not found', 404),
            };
        });
    }

    /** @return array{0: Organization, 1: Site} */
    private function site(string $connector = '1.4.0', bool $online = true): array
    {
        $org = $this->makeOrganization('Shop');
        [$site] = $this->makeSite($org, 'shop.ro');
        $site->forceFill(['platform' => 'wordpress', 'connector_version' => $connector, 'last_seen_at' => $online ? now() : now()->subDays(2),
            'health' => ['command_url' => 'https://shop.ro/wp-json/vitim/v1/command', 'remote_fixes' => true]])->save();

        return [$org, $site->fresh()];
    }

    public function test_weekly_autopilot_applies_only_the_safe_fixes_and_logs_them(): void
    {
        $this->fake();
        [$org, $site] = $this->site();

        $this->artisan('vitim:audit')->assertSuccessful();
        $this->assertCount(1, $this->targets, 'o singură comandă cu toate remedierile sigure');
        $applied = explode('.', $this->targets[0]);
        $this->assertContains('meta', $applied);
        $this->assertContains('alt', $applied);
        foreach (['robots', 'https', 'www', 'headers', 'gzip'] as $risky) {
            $this->assertNotContains($risky, $applied, "{$risky} rămâne la decizia echipei");
        }
        $command = $this->tenant()->runAs($org, fn () => SiteCommand::query()->sole());
        $this->assertNull($command->requested_by, 'cerută de pilot, nu de un om');
        $this->assertTrue($this->tenant()->runAs($org, fn () => WorkLog::query()->where('category', 'seo')->exists()), 'remedierile apar în Lucrări VITIM');
        $this->assertNotNull(Site::withoutTenancy()->find($site->id)->seo_autopilot_at);

        // a doua zi: auditul rulează, pilotul nu (o dată pe săptămână)
        $this->travel(25)->hours();
        $this->artisan('vitim:audit')->assertSuccessful();
        $this->assertCount(1, $this->targets);

        // după o săptămână, din nou
        $this->travel(7)->days();
        Site::withoutTenancy()->whereKey($site->id)->update(['last_seen_at' => now()]); // pluginul raportează la fiecare oră
        $this->artisan('vitim:audit')->assertSuccessful();
        $this->assertCount(2, $this->targets);
    }

    public function test_autopilot_respects_the_switch_old_plugins_and_offline_sites(): void
    {
        $this->fake();
        [$org, $site] = $this->site();
        $site->forceFill(['seo_autopilot' => false])->save();
        $this->artisan('vitim:audit')->assertSuccessful();
        $this->assertSame([], $this->targets);

        $site->forceFill(['seo_autopilot' => true, 'connector_version' => '1.3.0', 'last_audit_at' => null])->save();
        $this->artisan('vitim:audit')->assertSuccessful();
        $this->assertSame([], $this->targets, 'pluginul vechi nu are remedierile SEO');

        $site->forceFill(['connector_version' => '1.4.0', 'last_seen_at' => now()->subDays(2), 'last_audit_at' => null])->save();
        $this->artisan('vitim:audit')->assertSuccessful();
        $this->assertSame([], $this->targets, 'site fără semnal de la plugin');
    }

    public function test_staff_can_toggle_and_run_it_and_the_client_sees_it(): void
    {
        $this->fake();
        [$org, $site] = $this->site();
        $this->tenant()->runAs($org, fn () => app(SiteAuditService::class)->run($site));
        $base = "/admin/clienti/{$org->slug}/site-uri/{$site->id}";

        $this->actingAs($this->staff());
        $this->get($base)->assertOk()->assertSee('Pilot automat SEO')->assertSee('Rulează pilotul acum');
        $this->post("{$base}/pilot-seo/ruleaza")->assertRedirect()->assertSessionHas('ok');
        $this->assertCount(1, $this->targets);
        $this->post("{$base}/pilot-seo", ['on' => 0])->assertSessionHas('ok');
        $this->assertFalse(Site::withoutTenancy()->find($site->id)->seo_autopilot);
        $this->post("{$base}/pilot-seo", ['on' => 1]);

        $owner = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $owner->id, 'role' => OrgRole::Owner]));
        $this->actingAs($owner)->get("/app/{$org->slug}/site-uri/{$site->id}")->assertOk()->assertSee('Pilot automat SEO pornit');
        $this->post("{$base}/pilot-seo", ['on' => 0])->assertNotFound();
    }
}
