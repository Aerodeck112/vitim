<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Enums\OrgRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Site;
use App\Models\SiteIssue;
use App\Models\User;
use App\Services\Remediation;
use App\Services\SeoAutopilot;
use App\Services\SiteAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Vizibilitatea în asistenții AI: auditul „Pregătit pentru AI”, profilul public al firmei și remedierile prin plugin. */
final class AiVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array<string, mixed>> */
    private array $commands = [];

    /** @param array<string, string> $pages */
    private function fake(array $pages): void
    {
        Http::fake(function (Request $r) use ($pages) {
            if (str_ends_with($r->url(), '/wp-json/vitim/v1/command')) {
                $body = json_decode($r->body(), true);
                $this->commands[] = $body;

                return Http::response(['ok' => true, 'message' => 'Rezolvat.', 'issues' => [], 'applied' => explode('.', (string) $body['target'])]);
            }
            $path = (string) parse_url($r->url(), PHP_URL_PATH) ?: '/';
            if (parse_url($r->url(), PHP_URL_HOST) !== 'firma.ro' || parse_url($r->url(), PHP_URL_SCHEME) !== 'https' || ! isset($pages[$path])) {
                return Http::response('Not found', 404, ['Content-Type' => 'text/html']);
            }
            $type = str_ends_with($path, '.txt') ? 'text/plain' : 'text/html; charset=utf-8';

            return Http::response($pages[$path], 200, ['Content-Type' => $type]);
        });
    }

    /** @return array{0: Organization, 1: Site, 2: User} */
    private function firm(string $connector = '1.7.0'): array
    {
        $owner = User::factory()->create(['last_login_at' => now()]);
        $org = $this->makeOrganization('Firma', $owner);
        $org->forceFill(['company_name' => 'Firma Exemplu SRL'])->save();
        [$site] = $this->makeSite($org, 'firma.ro');
        $site->forceFill(['platform' => 'wordpress', 'connector_version' => $connector, 'last_seen_at' => now(),
            'health' => ['command_url' => 'https://firma.ro/wp-json/vitim/v1/command', 'remote_fixes' => true]])->save();

        return [$org, $site->fresh(), $owner];
    }

    /** @return array<string, string> */
    private function audit(Organization $org, Site $site): array
    {
        $this->tenant()->runAs($org, fn () => app(SiteAuditService::class)->run($site->fresh()));

        return $this->tenant()->runAs($org, fn () => SiteIssue::query()->where('status', 'open')->where('category', 'ai')->pluck('fix', 'code')->all());
    }

    public function test_audit_flags_what_keeps_ai_assistants_from_recommending_the_firm(): void
    {
        $this->fake([
            '/' => '<html lang="ro"><head><title>Firma</title><script type="application/ld+json">{"@context":"https://schema.org","@type":"LocalBusiness","name":"Firma"}</script></head><body><h1>Firma</h1><a href="/servicii/">Servicii</a></body></html>',
            '/robots.txt' => "User-agent: *\nDisallow: /wp-admin/\n\nUser-agent: OAI-SearchBot\nUser-agent: PerplexityBot\nDisallow: /\n\nUser-agent: GPTBot\nDisallow: /\n",
        ]);
        [$org, $site] = $this->firm();

        $issues = $this->audit($org, $site);
        $this->assertEqualsCanonicalizing(['ai.bots_blocked', 'ai.llms_missing', 'ai.schema_incomplete', 'ai.faq_missing', 'ai.about_missing'], array_keys($issues));
        $blocked = $this->tenant()->runAs($org, fn () => SiteIssue::query()->where('code', 'ai.bots_blocked')->sole());
        $this->assertStringContainsString('oai-searchbot', $blocked->details);
        $this->assertStringNotContainsString('gptbot', $blocked->details, 'roboții de antrenare pot rămâne blocați');
        $this->assertSame('seo_fix:llms', $issues['ai.llms_missing']);
        $this->assertSame('seo_fix:schema', $issues['ai.schema_incomplete']);
        $this->assertLessThan(100, Site::withoutTenancy()->find($site->id)->scores['ai']);

        // pluginul vechi nu are llms.txt: problema rămâne doar cu „Cum rezolvi”
        $site->forceFill(['connector_version' => '1.6.0'])->save();
        $this->assertNull($this->audit($org, $site)['ai.llms_missing']);
        $this->assertSame('seo_fix:schema', Remediation::forAudit('ai.schema_incomplete', '1.6.0'));
    }

    public function test_a_well_prepared_site_has_no_ai_issues(): void
    {
        $schema = json_encode(['@context' => 'https://schema.org', '@type' => 'AutoRepair', 'name' => 'Firma', 'telephone' => '0740000000', 'description' => 'Service auto.',
            'address' => ['@type' => 'PostalAddress', 'streetAddress' => 'Str. Exemplu 1', 'addressLocality' => 'Târgu Mureș'], 'sameAs' => ['https://www.facebook.com/firma']]);
        $this->fake([
            '/' => '<html lang="ro"><head><title>Firma</title><script type="application/ld+json">'.$schema.'</script></head><body><h1>Firma</h1><a href="/despre-noi/">Despre noi</a><a href="/intrebari-frecvente/">Întrebări frecvente</a></body></html>',
            '/robots.txt' => "User-agent: *\nDisallow: /wp-admin/\n",
            '/llms.txt' => "# Firma\n> Service auto.\n",
        ]);
        [$org, $site] = $this->firm();
        $this->assertSame([], $this->audit($org, $site));
        $this->assertSame(100, Site::withoutTenancy()->find($site->id)->scores['ai']);
    }

    public function test_public_profile_reaches_the_site_through_the_plugin(): void
    {
        $this->fake(['/' => '<html><body><h1>Firma</h1></body></html>', '/robots.txt' => "User-agent: *\nDisallow:\n"]);
        [$org, $site, $owner] = $this->firm();
        $this->actingAs($owner);

        $this->put("/app/{$org->slug}/setari/profil-public", ['about' => 'Service auto în Târgu Mureș.', 'same_as' => 'http://nesigur.ro'])->assertSessionHasErrors('same_as');
        $this->put("/app/{$org->slug}/setari/profil-public", [
            'about' => 'Service auto pentru toate mărcile în Târgu Mureș.', 'area' => 'Târgu Mureș, Reghin', 'hours' => 'luni–vineri 8–17',
            'phone' => '0740 000 000', 'city' => 'Târgu Mureș', 'same_as' => "https://www.facebook.com/firma\nhttps://maps.app.goo.gl/abc",
        ])->assertSessionHas('ok');
        $this->get("/app/{$org->slug}/setari")->assertOk()->assertSee('Profil public')->assertSee('https://maps.app.goo.gl/abc');

        // pilotul automat aplică llms.txt și datele structurate, cu profilul public
        $this->audit($org, $site);
        $this->tenant()->runAs($org, fn () => app(SeoAutopilot::class)->run(Site::withoutTenancy()->find($site->id)));
        $command = collect($this->commands)->firstWhere('action', 'seo_fix');
        $this->assertContains('llms', explode('.', $command['target']));
        $this->assertContains('schema', explode('.', $command['target']));
        $this->assertSame('Firma Exemplu SRL', $command['data']['name']);
        $this->assertSame('Târgu Mureș, Reghin', $command['data']['area']);
        $this->assertSame(['https://www.facebook.com/firma', 'https://maps.app.goo.gl/abc'], $command['data']['same_as']);

        // doar proprietarul îl modifică
        $agent = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $agent->id, 'role' => OrgRole::Agent]));
        $this->actingAs($agent)->put("/app/{$org->slug}/setari/profil-public", ['about' => 'Altceva'])->assertForbidden();
    }
}
