<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Ai\AiClient;
use App\Enums\OrgRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Site;
use App\Models\SitePage;
use App\Models\SocialAsset;
use App\Models\SocialConnection;
use App\Models\SocialPost;
use App\Models\SocialReference;
use App\Models\User;
use App\Social\SocialPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeAiClient;
use Tests\TestCase;

/** Cercetarea agentului de social media: pozele firmei (site, Meta, încărcări) și postările deja publicate. */
final class SocialResearchTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'EAAB-cod-de-acces-al-paginii-foarte-secret';

    private static function photo(int $w = 900, int $h = 700): string
    {
        $im = imagecreatetruecolor($w, $h);
        for ($i = 0; $i < 400; $i++) {
            imagefilledrectangle($im, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), imagecolorallocate($im, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        }
        ob_start();
        imagejpeg($im, null, 85);

        return (string) ob_get_clean();
    }

    /** @return array{0: Organization, 1: User, 2: Site} */
    private function firm(string $name, string $domain): array
    {
        $org = $this->makeOrganization($name);
        $user = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $user->id, 'role' => OrgRole::Owner]));
        [$site] = $this->makeSite($org, $domain);

        return [$org, $user, $site];
    }

    public function test_photos_are_collected_from_the_firm_site_only(): void
    {
        Storage::fake('local');
        [$org, $owner, $site] = $this->firm('Podreg', 'podreg.ro');
        $this->tenant()->runAs($org, fn () => SitePage::create(['site_id' => $site->id, 'url' => 'https://podreg.ro/cabane', 'url_hash' => sha1('https://podreg.ro/cabane'),
            'title' => 'Cabane din lemn', 'content' => 'x', 'kind' => 'page', 'status' => 'ok', 'run' => 1]));
        $big = self::photo();
        Http::fake(fn (Request $r) => match ($r->url()) {
            'https://podreg.ro/cabane' => Http::response('<html><head><meta property="og:image" content="https://podreg.ro/og/cabane.png"></head><body>'
                .'<img src="/img/logo.png"><img src="/img/mica.jpg"><img src="https://alt-site.ro/poza.jpg"><img src="/img/cabana.jpg" alt="Cabană de 60 mp la Sovata"></body></html>', 200, ['Content-Type' => 'text/html']),
            'https://podreg.ro/img/cabana.jpg' => Http::response($big, 200, ['Content-Type' => 'image/jpeg']),
            'https://podreg.ro/img/mica.jpg' => Http::response(self::photo(200, 200), 200, ['Content-Type' => 'image/jpeg']),
            default => Http::response('nu', 404),
        });

        $this->actingAs($owner)->post("/app/{$org->slug}/social/materiale/site", ['site' => $site->id])->assertSessionHas('ok', 'Am adăugat 1 fotografii din podreg.ro.');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'alt-site.ro'));
        $asset = $this->tenant()->runAs($org, fn () => SocialAsset::query()->sole());
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/og/'));
        $this->assertSame(['site', 'Cabană de 60 mp la Sovata', 900], [$asset->source, $asset->title, $asset->width]);
        $this->get("/app/{$org->slug}/social/materiale")->assertOk()->assertSee('din site');
        $this->get("/app/{$org->slug}/social/materiale/{$asset->id}/fisier")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        // a doua oară nu se dublează; încărcarea și ștergerea merg
        $this->post("/app/{$org->slug}/social/materiale/site", ['site' => $site->id])->assertSessionHas('ok', fn ($m) => str_contains($m, 'Nu am găsit fotografii noi'));
        $this->post("/app/{$org->slug}/social/materiale", ['photos' => [UploadedFile::fake()->createWithContent('echipa.jpg', self::photo())]])->assertSessionHas('ok', fn ($m) => str_starts_with($m, '1 fotografii'));
        $this->delete("/app/{$org->slug}/social/materiale/{$asset->id}")->assertSessionHas('ok');
        Storage::disk('local')->assertMissing($asset->path);
    }

    public function test_meta_page_is_read_through_the_official_api_and_feeds_the_plan(): void
    {
        Storage::fake('local');
        [$org, $owner] = $this->firm('Podreg', 'podreg.ro');
        $base = "/app/{$org->slug}/social";
        Http::fake(function (Request $r) {
            if (str_starts_with($r->url(), 'https://graph.facebook.com/1234567890/posts')) {
                return $r->hasHeader('Authorization', 'Bearer '.self::TOKEN) ? Http::response(['data' => [
                    ['id' => '1234567890_1', 'message' => 'Am predat o cabană de 60 mp la Sovata.', 'created_time' => '2026-11-01T10:00:00+0000', 'permalink_url' => 'https://www.facebook.com/p/1',
                        'full_picture' => 'https://scontent.fbcdn.net/v/cabana.jpg?sig=abc', 'likes' => ['summary' => ['total_count' => 40]], 'comments' => ['summary' => ['total_count' => 5]], 'shares' => ['count' => 3]],
                    ['id' => '1234567890_2', 'message' => 'Program de sărbători.', 'created_time' => '2026-10-20T10:00:00+0000', 'full_picture' => 'https://evil.example/x.jpg', 'likes' => ['summary' => ['total_count' => 2]]],
                ]]) : Http::response(['error' => ['message' => 'Invalid OAuth access token.']], 400);
            }

            return str_starts_with($r->url(), 'https://scontent.fbcdn.net/') ? Http::response(self::photo(), 200, ['Content-Type' => 'image/jpeg']) : Http::response('nu', 404);
        });

        $this->actingAs($owner)->put("{$base}/meta", ['page_id' => 'pagina-mea', 'token' => self::TOKEN])->assertSessionHasErrors('page_id');
        $this->put("{$base}/meta", ['page_id' => '1234567890', 'token' => 'cod-gresit-dar-destul-de-lung-000'])->assertSessionHas('ok');
        $this->post("{$base}/meta/citeste")->assertSessionHas('error', fn ($m) => str_contains($m, 'Invalid OAuth'));
        $this->put("{$base}/meta", ['page_id' => '1234567890', 'token' => self::TOKEN])->assertSessionHas('ok');
        $this->post("{$base}/meta/citeste")->assertSessionHas('ok', 'Am citit pagina: 2 postări noi și 1 fotografii noi.');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'evil.example') || str_contains($r->url(), self::TOKEN));

        // codul de acces: criptat în baza de date, niciodată în pagină
        $this->assertStringNotContainsString(self::TOKEN, (string) DB::table('social_connections')->value('config'));
        $this->get("{$base}/materiale")->assertOk()->assertSee('conectată')->assertSee('48 reacții')->assertDontSee(self::TOKEN);
        $this->put("{$base}/meta", ['page_id' => '1234567890'])->assertSessionHas('ok'); // câmp gol: rămâne codul salvat
        $this->assertSame(self::TOKEN, $this->tenant()->runAs($org, fn () => SocialConnection::query()->sole())->config['token']);

        // planul: agentul primește postările vechi și fotografiile, iar fotografia aleasă e legată de postare
        $asset = $this->tenant()->runAs($org, fn () => SocialAsset::query()->sole());
        $monday = SocialPlanner::monday();
        $ai = new FakeAiClient([FakeAiClient::text((string) json_encode(['posts' => [
            ['day' => $monday->toDateString(), 'topic' => 'Cabane', 'hook' => 'Cabana ta', 'caption' => 'Text.', 'hashtags' => ['#cabane'], 'asset' => $asset->id, 'scenes' => []],
            ['day' => $monday->copy()->addDay()->toDateString(), 'topic' => 'Altceva', 'hook' => 'Alt titlu', 'caption' => 'Text 2.', 'asset' => 999999, 'scenes' => []],
        ]]))]);
        $this->app->instance(AiClient::class, $ai);
        $this->post("{$base}/plan", ['s' => $monday->toDateString()])->assertSessionHas('ok');
        $this->assertStringContainsString('[facebook, 48 reacții] Am predat o cabană de 60 mp la Sovata.', $ai->requests[0]['system']);
        $this->assertStringContainsString("- {$asset->id}: Am predat o cabană", $ai->requests[0]['system']);
        $posts = $this->tenant()->runAs($org, fn () => SocialPost::query()->orderBy('day')->get());
        $this->assertSame([$asset->id, null], [$posts[0]->asset_id, $posts[1]->asset_id]);
        $this->get("{$base}/postari/{$posts[0]->id}")->assertOk()->assertSee("materiale/{$asset->id}/fisier");
    }

    public function test_materials_and_connections_stay_inside_the_firm(): void
    {
        Storage::fake('local');
        Http::fake();
        [$a, $ownerA] = $this->firm('Firma A', 'a.ro');
        [$b, , $siteB] = $this->firm('Firma B', 'b.ro');
        $assetB = $this->tenant()->runAs($b, function () {
            SocialReference::create(['network' => 'facebook', 'external_id' => '1', 'message' => 'Postare secretă B', 'engagement' => 9]);
            SocialConnection::create(['network' => 'meta', 'config' => ['page_id' => '99999', 'token' => self::TOKEN]]);
            Storage::disk('local')->put('social/b/x.jpg', self::photo());

            return SocialAsset::create(['source' => 'upload', 'origin_hash' => sha1('x'), 'path' => 'social/b/x.jpg', 'title' => 'Poza B', 'width' => 900, 'height' => 700, 'bytes' => 1]);
        });
        $base = "/app/{$a->slug}/social";

        $this->actingAs($ownerA)->get("{$base}/materiale")->assertOk()->assertDontSee('Postare secretă B')->assertDontSee('99999');
        $this->get("{$base}/materiale/{$assetB->id}/fisier")->assertNotFound();
        $this->delete("{$base}/materiale/{$assetB->id}")->assertNotFound();
        $this->post("{$base}/materiale/site", ['site' => $siteB->id])->assertNotFound();
        $this->post("{$base}/meta/citeste")->assertNotFound();
        $this->get("/app/{$b->slug}/social/materiale")->assertNotFound();
        Http::assertNothingSent();

        // fotografia altei firme nu poate fi pusă pe o postare
        $post = $this->tenant()->runAs($a, fn () => SocialPost::create(['day' => now()->toDateString(), 'topic' => 't', 'hook' => 'h', 'caption' => 'c', 'status' => 'draft', 'source' => 'manual']));
        $this->put("{$base}/postari/{$post->id}", ['day' => now()->toDateString(), 'topic' => 't', 'hook' => 'h', 'caption' => 'c', 'asset_id' => $assetB->id])->assertSessionHasErrors('asset_id');

        $viewer = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($a, fn () => Membership::create(['user_id' => $viewer->id, 'role' => OrgRole::Viewer]));
        $this->actingAs($viewer)->get("{$base}/materiale")->assertForbidden();
        $this->put("{$base}/meta", ['page_id' => '1234567890', 'token' => self::TOKEN])->assertForbidden();
    }
}
