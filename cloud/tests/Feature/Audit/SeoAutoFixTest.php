<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\Organization;
use App\Models\Site;
use App\Models\SiteIssue;
use App\Models\WorkLog;
use App\Services\Remediation;
use App\Services\SiteAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Problemele SEO din audit se rezolvă la buton pe WordPress cu pluginul 1.4.0+. */
final class SeoAutoFixTest extends TestCase
{
    use RefreshDatabase;

    private function fake(): void
    {
        $home = '<html><head><meta name="robots" content="noindex"></head><body><img src="a.jpg"><h1>Shop</h1></body></html>';
        Http::fake([
            'https://shop.ro/wp-json/vitim/v1/command' => Http::response(['ok' => true, 'message' => 'Rezolvat: Descrieri, canonical și Open Graph, robots.txt corect. HTTPS nu funcționează încă.', 'issues' => [], 'applied' => ['meta', 'robots']]),
            'http://shop.ro/' => Http::response($home, 200),
            'https://www.shop.ro/' => Http::response($home, 200),
            'https://shop.ro/robots.txt' => Http::response("User-agent: *\nDisallow: /", 200, ['Content-Type' => 'text/plain']),
            'https://shop.ro/' => Http::response($home, 200, ['Content-Type' => 'text/html']),
            '*' => Http::response('Not found', 404),
        ]);
    }

    /** @return array{0: Organization, 1: Site} */
    private function site(string $connector): array
    {
        $org = $this->makeOrganization('Shop');
        $org->forceFill(['company_name' => 'Shop Online SRL'])->save();
        [$site] = $this->makeSite($org, 'shop.ro');
        $site->forceFill(['platform' => 'wordpress', 'connector_version' => $connector, 'health' => ['command_url' => 'https://shop.ro/wp-json/vitim/v1/command', 'remote_fixes' => true]])->save();

        return [$org, $site->fresh()];
    }

    public function test_audit_issues_get_fix_buttons_only_with_the_new_plugin(): void
    {
        $this->fake();
        [$org, $site] = $this->site('1.4.0');
        $this->tenant()->runAs($org, fn () => app(SiteAuditService::class)->run($site));
        $fixes = $this->tenant()->runAs($org, fn () => SiteIssue::query()->where('status', 'open')->pluck('fix', 'code')->all());
        $this->assertSame('allow_indexing', $fixes['seo.noindex_home']);
        $this->assertSame('seo_fix:robots', $fixes['seo.robots_block_all']);
        $this->assertSame('seo_fix:meta', $fixes['seo.description_missing']);
        $this->assertSame('seo_fix:alt', $fixes['seo.images_alt']);
        $this->assertSame('seo_fix:https', $fixes['seo.https_redirect']);
        $this->assertSame('seo_fix:headers', $fixes['sec.headers_missing']);

        // pluginul vechi nu are remedierile SEO: problemele rămân doar cu „Cum rezolvi”
        $site->forceFill(['connector_version' => '1.3.0'])->save();
        $this->tenant()->runAs($org, fn () => app(SiteAuditService::class)->run($site->fresh()));
        $this->assertNull($this->tenant()->runAs($org, fn () => SiteIssue::query()->where('code', 'seo.description_missing')->sole()->fix));
        $this->assertNull(Remediation::forAudit('seo.description_missing', '1.3.0'));
        $this->assertNull(Remediation::forAudit('seo.description_missing', null));
    }

    public function test_one_button_fixes_everything_logs_the_work_and_re_audits(): void
    {
        $this->fake();
        [$org, $site] = $this->site('1.4.0');
        $this->tenant()->runAs($org, fn () => app(SiteAuditService::class)->run($site));
        $combined = $this->tenant()->runAs($org, fn () => Remediation::combine(SiteIssue::query()->where('status', 'open')->pluck('fix')));
        $this->assertStringStartsWith('seo_fix:', $combined);
        $this->assertContains('schema', explode('.', substr($combined, 8)));

        $this->actingAs($this->staff());
        $this->get("/admin/clienti/{$org->slug}/site-uri/{$site->id}")->assertOk()->assertSee('Repară tot automat')->assertSee('se pot rezolva automat');
        $before = Site::withoutTenancy()->find($site->id)->last_audit_at;
        $this->travel(1)->minutes();
        $this->post("/admin/clienti/{$org->slug}/site-uri/{$site->id}/remediere", ['fix' => $combined])->assertRedirect()->assertSessionHas('ok');

        Http::assertSent(function (Request $r) use ($combined): bool {
            $body = json_decode($r->body(), true);

            return str_ends_with($r->url(), '/wp-json/vitim/v1/command') && $body['action'] === 'seo_fix' && 'seo_fix:'.$body['target'] === $combined
                && $body['data']['name'] === 'Shop Online SRL';
        });
        $this->assertEqualsCanonicalizing(['Adaugă descrieri, canonical și Open Graph', 'Repară robots.txt'],
            $this->tenant()->runAs($org, fn () => WorkLog::query()->where('category', 'seo')->pluck('title')->all()), 'în Lucrări VITIM intră doar ce s-a aplicat');
        $this->assertTrue(Site::withoutTenancy()->find($site->id)->last_audit_at->gt($before), 'auditul s-a refăcut după remediere');
    }

    public function test_only_known_seo_fixes_pass(): void
    {
        $this->assertNotNull(Remediation::parse('seo_fix:meta.robots'));
        $this->assertNull(Remediation::parse('seo_fix:meta.rm-rf'));
        $this->assertNull(Remediation::parse('seo_fix:'));
        $this->assertNull(Remediation::parse('seo_fix'));
        $this->assertSame('Repară robots.txt', Remediation::label('seo_fix:robots'));
        $this->assertSame('Repară automat 2 probleme', Remediation::label('seo_fix:meta.robots'));
        $this->assertSame('seo_fix:meta.alt', Remediation::combine(['seo_fix:meta', null, 'allow_indexing', 'seo_fix:alt', 'seo_fix:meta']));
    }
}
