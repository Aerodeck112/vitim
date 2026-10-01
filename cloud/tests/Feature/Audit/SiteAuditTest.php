<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Audit\Guidance;
use App\Enums\OrgRole;
use App\Models\Membership;
use App\Models\Site;
use App\Models\SiteIssue;
use App\Models\User;
use App\Services\SiteAuditService;
use App\Services\SiteScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SiteAuditTest extends TestCase
{
    use RefreshDatabase;

    private function goodHome(): string
    {
        return '<!doctype html><html lang="ro"><head><title>Service auto Târgu Mureș – distribuție, ITP | Demo</title>'
            .'<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="Service auto în Târgu Mureș: distribuție, ITP, diagnoză. Programează-te online.">'
            .'<link rel="canonical" href="https://demo.ro/"><meta property="og:title" content="Demo"><script type="application/ld+json">{"@type":"AutoRepair","name":"Demo"}</script>'
            .'<script src="https://www.googletagmanager.com/gtag/js?id=G-1"></script><script src="/cookieyes.js" id="cookieyes"></script></head><body>'
            .'<h1>Service auto</h1><img src="a.jpg" alt="atelier"><form><input type="email"><input type="checkbox"> Politica de confidențialitate</form>'
            .'<footer><a href="/politica-de-confidentialitate">Politica de confidențialitate</a> <a href="/cookies">Politica cookie</a> <a href="/termeni">Termeni și condiții</a>'
            .'<a href="https://anpc.ro/ce-este-sal/">ANPC</a> <a href="https://ec.europa.eu/consumers/odr">SOL</a> Demo SRL · CUI RO12345678 · J26/123/2015 · office@demo.ro</footer></body></html>';
    }

    private function fakeGoodSite(): void
    {
        $secure = ['X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'SAMEORIGIN', 'Referrer-Policy' => 'strict-origin', 'Strict-Transport-Security' => 'max-age=31536000', 'Content-Encoding' => 'gzip'];
        Http::fake([
            'http://demo.ro/' => Http::response('', 301, ['Location' => 'https://demo.ro/']),
            'https://www.demo.ro/' => Http::response('', 301, ['Location' => 'https://demo.ro/']),
            'https://demo.ro/robots.txt' => Http::response("User-agent: *\nDisallow: /wp-admin/\nSitemap: https://demo.ro/sitemap.xml", 200, ['Content-Type' => 'text/plain']),
            'https://demo.ro/sitemap.xml' => Http::response('<urlset><url><loc>https://demo.ro/</loc></url><url><loc>https://demo.ro/contact</loc></url></urlset>', 200, ['Content-Type' => 'application/xml']),
            'https://demo.ro/contact' => Http::response(str_replace(['<title>Service auto Târgu Mureș – distribuție, ITP | Demo</title>', '<link rel="canonical" href="https://demo.ro/">'], ['<title>Contact – Service auto Demo Târgu Mureș</title>', '<link rel="canonical" href="https://demo.ro/contact">'], $this->goodHome()), 200, $secure + ['Content-Type' => 'text/html']),
            'https://demo.ro/' => Http::response($this->goodHome(), 200, $secure + ['Content-Type' => 'text/html']),
            '*' => Http::response('Not found', 404),
        ]);
    }

    private function fakeBadSite(): void
    {
        $home = '<html><head><meta name="robots" content="noindex"><script src="https://connect.facebook.net/en_US/fbevents.js"></script><script>fbq("init")</script></head>'
            .'<body><img src="a.jpg"><img src="b.jpg" alt=""><a href="/despre">Despre</a><form><input type="email" name="e"><button>Abonează-te</button></form><a class="add_to_cart_button">Adaugă în coș</a></body></html>';
        Http::fake([
            'http://shop.ro/' => Http::response($home, 200),
            'https://www.shop.ro/' => Http::response($home, 200),
            'https://shop.ro/robots.txt' => Http::response("User-agent: *\nDisallow: /", 200, ['Content-Type' => 'text/plain']),
            'https://shop.ro/.env' => Http::response("APP_ENV=production\nDB_PASSWORD=secret", 200),
            'https://shop.ro/.git/HEAD' => Http::response('ref: refs/heads/main', 200),
            'https://shop.ro/wp-content/uploads/' => Http::response('<title>Index of /wp-content/uploads</title><h1>Index of /wp-content/uploads</h1>', 200),
            'https://shop.ro/despre' => Http::response('<html><head><title>Despre</title></head><body><h1>A</h1><h1>B</h1></body></html>', 200, ['Content-Type' => 'text/html']),
            'https://shop.ro/' => Http::response($home, 200, ['Content-Type' => 'text/html', 'X-Powered-By' => 'PHP/7.4.3']),
            '*' => Http::response('Not found', 404),
        ]);
    }

    public function test_bad_site_gets_seo_security_and_legal_findings_with_guidance(): void
    {
        $this->fakeBadSite();
        $org = $this->makeOrganization('Shop');
        [$site] = $this->makeSite($org, 'shop.ro');
        $this->tenant()->runAs($org, fn () => app(SiteAuditService::class)->run($site));

        $issues = $this->tenant()->runAs($org, fn () => SiteIssue::query()->where('status', 'open')->get()->keyBy('code'));
        foreach (['seo.https_redirect', 'seo.www_duplicate', 'seo.robots_block_all', 'seo.noindex_home', 'seo.viewport_missing', 'seo.title_missing',
            'seo.description_missing', 'seo.h1_multiple', 'seo.images_alt', 'seo.sitemap_missing', 'sec.exposed_files', 'sec.dir_listing', 'sec.server_leak',
            'sec.headers_missing', 'legal.privacy_policy', 'legal.cookie_consent', 'legal.terms', 'legal.anpc', 'legal.sol', 'legal.company_id', 'legal.form_consent'] as $code) {
            $this->assertArrayHasKey($code, $issues->all(), "lipsește {$code}");
            $this->assertNotNull(Guidance::howTo($code), "fără „Cum rezolvi” pentru {$code}");
        }
        $this->assertSame('critical', $issues['legal.anpc']->severity, 'magazin online → ANPC obligatoriu');
        $this->assertStringContainsString('https://shop.ro/.env', $issues['sec.exposed_files']->details);
        $this->assertSame('legal', $issues['legal.cookie_consent']->category);
        $this->assertSame('audit', $issues['seo.noindex_home']->source);

        $fresh = Site::withoutTenancy()->find($site->id);
        $this->assertNotNull($fresh->last_audit_at);
        $this->assertLessThan(50, $fresh->scores['legal']);
        $this->assertLessThan(50, $fresh->scores['seo']);
    }

    public function test_good_site_is_clean_and_audit_resolves_fixed_issues(): void
    {
        $this->fakeGoodSite();
        $org = $this->makeOrganization('Demo');
        [$site] = $this->makeSite($org, 'demo.ro');
        // o problemă veche din audit, între timp rezolvată, și una din plugin (sursă diferită: nu se atinge)
        $this->tenant()->runAs($org, function () use ($site): void {
            app(SiteScanService::class)->ingest($site, [['code' => 'legal.anpc', 'severity' => 'warning', 'title' => 'vechi']], 'audit');
            app(SiteScanService::class)->ingest($site, [['code' => 'xmlrpc', 'severity' => 'info', 'title' => 'XML-RPC e activ']], 'plugin');
            app(SiteAuditService::class)->run($site);
        });

        $open = $this->tenant()->runAs($org, fn () => SiteIssue::query()->where('status', 'open')->pluck('code')->all());
        $this->assertSame(['xmlrpc'], $open, 'site-ul conform nu are constatări în audit: '.implode(', ', $open));
        $this->assertSame('resolved', $this->tenant()->runAs($org, fn () => SiteIssue::query()->where('code', 'legal.anpc')->sole()->status));
        $this->assertSame(100, Site::withoutTenancy()->find($site->id)->scores['legal']);
    }

    public function test_audit_never_leaves_the_site_domain(): void
    {
        Http::fake([
            'https://demo.ro/' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
            '*' => Http::response('x', 200),
        ]);
        $org = $this->makeOrganization('Demo');
        [$site] = $this->makeSite($org, 'demo.ro');
        $this->tenant()->runAs($org, fn () => app(SiteAuditService::class)->run($site));
        Http::assertNotSent(fn ($request) => ! in_array(parse_url($request->url(), PHP_URL_HOST), ['demo.ro', 'www.demo.ro'], true));
    }

    public function test_pages_and_command_are_isolated_and_client_sees_report(): void
    {
        $this->fakeBadSite();
        $org = $this->makeOrganization('Shop');
        $other = $this->makeOrganization('Alta');
        [$site] = $this->makeSite($org, 'shop.ro');
        $this->actingAs($this->staff());
        $this->post("/admin/clienti/{$org->slug}/site-uri/{$site->id}/audit")->assertRedirect()->assertSessionHas('ok');
        $this->get("/admin/clienti/{$org->slug}/site-uri/{$site->id}?categorie=legal")->assertOk()->assertSee('Cum rezolvi')->assertSee('Legea 365/2002');
        $this->post("/admin/clienti/{$other->slug}/site-uri/{$site->id}/audit")->assertNotFound();

        $client = User::factory()->create();
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $client->id, 'role' => OrgRole::Viewer]));
        $this->actingAs($client);
        $this->get("/app/{$org->slug}/site-uri/{$site->id}")->assertOk()->assertSee('De îmbunătățit')->assertSee('Lipsește linkul ANPC')->assertDontSee('Cum rezolvi');
        $this->get("/app/{$org->slug}")->assertSee('raport');
        $this->post("/admin/clienti/{$org->slug}/site-uri/{$site->id}/audit")->assertNotFound();

        $outsider = User::factory()->create();
        $this->tenant()->runAs($other, fn () => Membership::create(['user_id' => $outsider->id, 'role' => OrgRole::Owner]));
        $this->actingAs($outsider);
        $this->get("/app/{$other->slug}/site-uri/{$site->id}")->assertNotFound();
    }
}
