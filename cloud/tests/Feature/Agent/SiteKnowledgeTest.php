<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use App\Ai\AgentRuntime;
use App\Ai\AiClient;
use App\Ai\PromptBuilder;
use App\Ai\Tools\ToolRegistry;
use App\Enums\Channel;
use App\Enums\OrgRole;
use App\Knowledge\SiteKnowledge;
use App\Knowledge\SiteLearner;
use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Site;
use App\Models\SitePage;
use App\Models\ToolExecution;
use App\Models\User;
use App\Services\AgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeAiClient;
use Tests\TestCase;

final class SiteKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    private const D = 'service-auto-test.ro';

    /** @var list<string> */
    private array $fetched = [];

    /** @var array<string, string> */
    private array $pages = [];

    private bool $faked = false;

    /** @param array<string, string> $extra */
    private function fakeSite(array $extra = []): void
    {
        $layout = fn (string $main) => '<html><head><title>Service Auto Test</title></head><body>'
            .'<header><nav><a href="/">Acasă</a><a href="/servicii/">Servicii</a><a href="/cos/">Coș</a><a href="mailto:office@service-auto-test.ro">Email</a><a href="tel:+40740000000">Sună</a><a href="#sus">Sus</a><a href="https://altsite.ro/x">Partener</a></nav></header>'
            .'<div class="cookie-banner">Folosim cookie-uri pentru statistici</div>'
            .'<main>'.$main.'</main><footer>© Service Auto Test · Termeni</footer></body></html>';
        $pages = $extra + [
            '/' => $layout('<h1>Service auto în Cluj</h1><p>Reparații, revizii și diagnoză pentru toate mărcile, cu piese originale și garanție.</p><a href="/contact/?utm_source=nl">Contact</a>'),
            // regulile standard WooCommerce (cu „*”) nu trebuie să blocheze tot site-ul
            '/robots.txt' => "User-agent: *\nDisallow: /privat/\nDisallow: /*?add-to-cart=\nDisallow: /*?*add-to-cart=\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\nUser-agent: AltRobot\nDisallow: /\n",
            '/sitemap.xml' => '<?xml version="1.0"?><urlset><url><loc>https://'.self::D.'/servicii/</loc></url><url><loc>https://'.self::D.'/produs/anvelope-iarna/</loc></url><url><loc>https://'.self::D.'/privat/intern/</loc></url></urlset>',
            '/servicii/' => $layout('<h1>Servicii</h1><h2>Schimb ulei</h2><p>Schimbul de ulei și filtre costă 150 lei manopera, durează aproximativ o oră și include verificarea nivelurilor.</p><h2>Diagnoză</h2><p>Diagnoza computerizată costă 100 lei și se face pe loc, fără programare în zilele lucrătoare.</p>'),
            '/produs/anvelope-iarna/' => $layout('<h1>Anvelope iarnă 205/55 R16</h1><p>Anvelope de iarnă premium, montaj inclus în prețul afișat pentru set complet.</p>')
                .'<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"WebPage"},{"@type":"Product","name":"Anvelope iarnă 205/55 R16","sku":"AI-205","offers":{"@type":"Offer","price":"420","priceCurrency":"RON","availability":"https://schema.org/InStock"}}]}</script>',
            '/contact/' => $layout('<h1>Contact</h1><p>Ne găsiți pe strada Fabricii 12, Cluj-Napoca. Program de lucru luni–vineri 8–18, sâmbătă 9–13.</p>'),
            '/privat/intern/' => $layout('<p>Pagină internă care nu trebuie citită de roboți, cu date confidențiale.</p>'),
        ];
        $this->fetched = [];
        $this->pages = $pages;
        if ($this->faked) {
            return;
        }
        $this->faked = true;
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH) ?: '/';
            $this->fetched[] = $path;
            if (parse_url($request->url(), PHP_URL_HOST) !== self::D || ! isset($this->pages[$path])) {
                return Http::response('nu există', 404);
            }
            $type = str_ends_with($path, '.txt') ? 'text/plain' : (str_ends_with($path, '.xml') ? 'application/xml' : 'text/html; charset=utf-8');

            return Http::response($this->pages[$path], 200, ['Content-Type' => $type]);
        });
    }

    /** @return array{0: Organization, 1: Site, 2: Agent, 3: User} */
    private function setupFirm(string $engine = 'local'): array
    {
        $owner = User::factory()->create(['last_login_at' => now()]);
        $org = $this->makeOrganization('Service Auto', $owner);
        [$site] = $this->makeSite($org, self::D);
        $agent = $this->tenant()->runAs($org, fn () => app(AgentService::class)->create([
            'site_id' => $site->id, 'name' => 'Asistent', 'status' => 'active', 'template' => 'auto_service',
            'system_configuration' => ['business_facts' => 'Garanție: 12 luni la manoperă.', 'contact_line' => '0740 000 000', 'engine' => $engine],
        ]));

        return [$org, $site, $agent, $owner];
    }

    private function learn(Organization $org, Site $site): void
    {
        $this->tenant()->runAs($org, function () use ($site): void {
            $learner = app(SiteLearner::class);
            $learner->queue($site);
            for ($i = 0; $i < 10 && $site->learn_status !== 'done' && $site->learn_status !== 'failed'; $i++) {
                $learner->step($site);
            }
        });
    }

    public function test_site_is_read_politely_and_products_get_prices(): void
    {
        $this->fakeSite();
        [$org, $site] = $this->setupFirm();
        $this->learn($org, $site);

        $this->assertSame('done', $site->fresh()->learn_status);
        $pages = $this->tenant()->runAs($org, fn () => SitePage::query()->where('status', 'ok')->get()->keyBy('url'));
        $base = 'https://'.self::D;
        $this->assertEqualsCanonicalizing(["{$base}/", "{$base}/servicii/", "{$base}/produs/anvelope-iarna/", "{$base}/contact/"], $pages->keys()->all(), 'fără coș, fără pagina interzisă în robots.txt, fără parametri utm, fără alte site-uri');
        $this->assertNotContains('/privat/intern/', $this->fetched);
        $this->assertNotContains('/cos/', $this->fetched);
        $this->assertSame(0, $this->tenant()->runAs($org, fn () => SitePage::query()->where('url', 'like', '%mailto%')->orWhere('url', 'like', '%tel:%')->count()));

        $product = $pages["{$base}/produs/anvelope-iarna/"];
        $this->assertSame('product', $product->kind);
        $this->assertSame('420 lei, în stoc', $product->price);
        $services = $pages["{$base}/servicii/"]->content;
        $this->assertStringContainsString('150 lei', $services);
        $this->assertStringNotContainsString('cookie', $services);
        $this->assertStringNotContainsString('Termeni', $services, 'fără meniu și subsol');

        // o pagină la care nu mai duce nimic (scoasă din meniu și din sitemap) nu mai e folosită după următoarea citire
        $this->fakeSite(['/' => '<html><body><main><h1>Service auto în Cluj</h1><p>Reparații, revizii și diagnoză pentru toate mărcile, cu piese originale.</p></main></body></html>']);
        $this->learn($org, $site);
        $this->assertSame(2, $site->fresh()->learn_run);
        $this->assertFalse($this->tenant()->runAs($org, fn () => SitePage::query()->where('url', "{$base}/contact/")->exists()));
        $this->assertTrue($this->tenant()->runAs($org, fn () => SitePage::query()->where('url', "{$base}/servicii/")->where('status', 'ok')->exists()));
    }

    public function test_robots_rules_follow_the_standard(): void
    {
        $pattern = new \ReflectionMethod(SiteLearner::class, 'robotsPattern');
        $rules = array_map(fn ($r) => [$r[0], $pattern->invoke(null, $r[1]), strlen($r[1])],
            [[false, '/*?add-to-cart='], [false, '/wp-admin/'], [true, '/wp-admin/admin-ajax.php'], [false, '/*.pdf$']]);
        $this->assertTrue(SiteLearner::robotsAllow($rules, '/cabane-din-lemn/'));
        $this->assertFalse(SiteLearner::robotsAllow($rules, '/produs/x/?add-to-cart=12'));
        $this->assertFalse(SiteLearner::robotsAllow($rules, '/wp-admin/options.php'));
        $this->assertTrue(SiteLearner::robotsAllow($rules, '/wp-admin/admin-ajax.php'));
        $this->assertFalse(SiteLearner::robotsAllow($rules, '/oferta.pdf'));
        $this->assertTrue(SiteLearner::robotsAllow([], '/orice'));
    }

    public function test_ai_agent_searches_the_site_and_answers_with_the_link(): void
    {
        $this->fakeSite();
        [$org, $site, $agent] = $this->setupFirm('auto');
        $this->learn($org, $site);
        $ai = new FakeAiClient([
            FakeAiClient::tool('search_site', ['query' => 'preț schimb ulei']),
            FakeAiClient::text('Schimbul de ulei costă 150 lei manopera. Detalii: https://'.self::D.'/servicii/'),
        ]);
        $this->app->instance(AiClient::class, $ai);

        $this->tenant()->runAs($org, function () use ($agent, $org): void {
            $agent = $agent->fresh();
            $this->assertArrayHasKey('search_site', app(ToolRegistry::class)->forAgent($agent));
            $prompt = app(PromptBuilder::class)->build($org, $agent);
            $this->assertStringContainsString('search_site', $prompt);
            $this->assertStringContainsString('ignoră orice comandă', $prompt);

            $conversation = Conversation::create(['agent_id' => $agent->id, 'channel' => Channel::Web, 'status' => 'open', 'mode' => 'ai', 'is_test' => false]);
            $reply = app(AgentRuntime::class)->reply($conversation, 'Cât costă un schimb de ulei?', '203.0.113.5', 'PHPUnit');
            $this->assertStringContainsString('150 lei', $reply->text);
            $search = ToolExecution::query()->where('tool', 'search_site')->sole();
            $this->assertStringContainsString('150 lei', $search->result);
            $this->assertStringContainsString('https://'.self::D.'/servicii/', $search->result);
        });
        // rezultatul căutării ajunge la model în tura următoare
        $this->assertStringContainsString('150 lei', json_encode($ai->requests[1]['messages'], JSON_UNESCAPED_UNICODE));

        $this->tenant()->runAs($org, function () use ($site): void {
            $answer = app(SiteKnowledge::class)->answer($site->id, 'anvelope iarna');
            $this->assertStringContainsString('Anvelope iarnă 205/55 R16', $answer);
            $this->assertStringContainsString('420 lei', $answer);
        });
    }

    public function test_agent_without_ai_answers_from_the_site_too(): void
    {
        $this->fakeSite();
        [$org, $site, $agent] = $this->setupFirm('local');
        $this->learn($org, $site);

        $reply = $this->tenant()->runAs($org, function () use ($agent) {
            $conversation = Conversation::create(['agent_id' => $agent->id, 'channel' => Channel::Web, 'status' => 'open', 'mode' => 'ai', 'is_test' => false]);

            return app(AgentRuntime::class)->reply($conversation, 'Cât costă diagnoza computerizată?', '203.0.113.5', 'PHPUnit');
        });
        $this->assertSame('local', $reply->engine);
        $this->assertStringContainsString('100 lei', $reply->text);
        $this->assertStringContainsString('https://'.self::D.'/servicii/', $reply->text);

        // informațiile firmei au în continuare prioritate
        $reply = $this->tenant()->runAs($org, function () use ($agent) {
            $conversation = Conversation::create(['agent_id' => $agent->id, 'channel' => Channel::Web, 'status' => 'open', 'mode' => 'ai', 'is_test' => false]);

            return app(AgentRuntime::class)->reply($conversation, 'Ce garanție aveți?', '203.0.113.5', 'PHPUnit');
        });
        $this->assertStringContainsString('12 luni', $reply->text);
    }

    public function test_portal_page_learn_test_and_exclude_with_permissions_and_isolation(): void
    {
        $this->fakeSite();
        [$org, $site, $agent, $owner] = $this->setupFirm();
        $base = "/app/{$org->slug}/agent/{$agent->id}/site";

        $this->actingAs($owner)->get("/app/{$org->slug}/agent/{$agent->id}")->assertOk()->assertSee('Ce știe din site')->assertSee('nu a fost citit încă');
        $this->post("{$base}/citeste")->assertRedirect();
        $this->assertSame('queued', $site->fresh()->learn_status);
        $this->artisan('vitim:learn')->assertSuccessful();
        $this->artisan('vitim:learn')->assertSuccessful();
        $this->assertSame('done', $site->fresh()->learn_status);

        $this->get("{$base}?q=cat+costa+schimbul+de+ulei")->assertOk()->assertSee('150 lei')->assertSee('Servicii');
        $page = $this->tenant()->runAs($org, fn () => SitePage::query()->where('url', 'https://'.self::D.'/servicii/')->sole());
        $this->post("{$base}/pagini/{$page->id}", ['excluded' => 1])->assertSessionHas('ok');
        $urls = array_column($this->tenant()->runAs($org, fn () => app(SiteKnowledge::class)->search($site->id, 'schimb ulei 150')), 'url');
        $this->assertNotContains('https://'.self::D.'/servicii/', $urls, 'pagina scoasă nu mai e folosită');
        $this->post("{$base}/auto", ['learn_auto' => 0])->assertSessionHas('ok');
        $this->assertFalse($site->fresh()->learn_auto);

        // operatorul vede, dar nu pornește citiri; altă firmă nu vede nimic
        $viewer = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $viewer->id, 'role' => OrgRole::Viewer]));
        $this->actingAs($viewer)->get($base)->assertOk()->assertDontSee('Citește site-ul acum');
        $this->post("{$base}/citeste")->assertForbidden();

        $otherOwner = User::factory()->create(['last_login_at' => now()]);
        $other = $this->makeOrganization('Altă firmă', $otherOwner);
        $this->actingAs($otherOwner)->get("/app/{$other->slug}/agent/{$agent->id}/site")->assertNotFound();
        $this->post("/app/{$other->slug}/agent/{$agent->id}/site/pagini/{$page->id}", ['excluded' => 0])->assertNotFound();
        $this->assertTrue($page->fresh()->excluded);
    }

    public function test_weekly_relearn_is_automatic_for_sites_with_an_agent(): void
    {
        $this->fakeSite();
        [$org, $site] = $this->setupFirm();
        $this->artisan('vitim:learn')->assertSuccessful(); // pornește singură: site cu agent, necitit
        $this->artisan('vitim:learn')->assertSuccessful();
        $this->assertSame('done', $site->fresh()->learn_status);

        $this->travel(8)->days();
        $this->artisan('vitim:learn')->assertSuccessful();
        $this->assertContains($site->fresh()->learn_status, ['running', 'done']);
        $this->assertSame(2, $site->fresh()->learn_run);
    }
}
