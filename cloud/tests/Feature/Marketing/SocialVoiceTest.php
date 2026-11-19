<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Enums\OrgRole;
use App\Enums\PlatformRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\SocialPost;
use App\Models\User;
use App\Services\UsageMeter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Agentul audio: vocea postării, generată prin serviciul de voce al platformei și ținută în firma ei. */
final class SocialVoiceTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'sk_cheie-de-voce-foarte-secreta-000';

    private const VOICE = 'AbCdEfGh1234567890Xy';

    /** @return array{0: Organization, 1: User, 2: SocialPost} */
    private function firm(string $name, ?string $voiceover = 'Construim cabane din lemn, la cheie. Sunați la zero șapte patru patru.'): array
    {
        $org = $this->makeOrganization($name);
        $user = User::factory()->create(['last_login_at' => now()]);
        $post = $this->tenant()->runAs($org, function () use ($user, $voiceover) {
            Membership::create(['user_id' => $user->id, 'role' => OrgRole::Owner]);

            return SocialPost::create(['day' => now()->toDateString(), 'topic' => 't', 'hook' => 'Titlu', 'caption' => 'Text', 'voiceover' => $voiceover, 'status' => 'draft', 'source' => 'manual']);
        });

        return [$org, $user, $post];
    }

    private function fakeService(): void
    {
        Http::fake(function (Request $r) {
            if (! $r->hasHeader('xi-api-key', self::KEY)) {
                return Http::response(['detail' => ['message' => 'Invalid API key']], 401);
            }

            return str_contains($r->url(), '/voices') ? Http::response(['voices' => [['voice_id' => self::VOICE, 'name' => 'Ana']]])
                : Http::response(str_repeat('ID3-audio-', 400), 200, ['Content-Type' => 'audio/mpeg']);
        });
    }

    public function test_voice_is_generated_in_romanian_from_the_post_text(): void
    {
        Storage::fake('local');
        $this->fakeService();
        [$org, $owner, $post] = $this->firm('Podreg');
        $base = "/app/{$org->slug}/social/postari/{$post->id}";

        // fără cheie: pagina explică, nimic nu pleacă spre serviciu
        $this->actingAs($owner)->get($base)->assertOk()->assertSee('Serviciul de voce nu este configurat');
        $this->post("{$base}/voce")->assertSessionHas('error');
        Http::assertNothingSent();

        // super adminul pune cheia (cu parola lui); administratorul VITIM obișnuit nu poate
        $this->actingAs($this->staff(PlatformRole::VitimAdmin))->put('/admin/agent-ai/voce', ['voice_key' => self::KEY, 'voice_default' => self::VOICE, 'password' => 'password'])->assertForbidden();
        $admin = $this->staff();
        $this->actingAs($admin)->put('/admin/agent-ai/voce', ['voice_key' => self::KEY, 'voice_default' => self::VOICE, 'password' => 'gresita'])->assertSessionHasErrors('password');
        $this->put('/admin/agent-ai/voce', ['voice_key' => self::KEY, 'voice_default' => self::VOICE, 'password' => 'password'])->assertSessionHas('status', fn ($m) => str_contains($m, '1 voci disponibile'));
        $this->assertStringNotContainsString(self::KEY, (string) json_encode(DB::table('platform_settings')->pluck('value')));
        $this->get('/admin/agent-ai')->assertOk()->assertSee('configurată')->assertDontSee(self::KEY);

        $this->actingAs($owner)->post("{$base}/voce")->assertSessionHas('ok');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/text-to-speech/'.self::VOICE) && $r['language_code'] === 'ro' && str_contains($r['text'], 'Construim cabane din lemn'));
        $fresh = $post->fresh();
        $this->assertNotNull($fresh->voice_path);
        Storage::disk('local')->assertExists($fresh->voice_path);
        $this->assertSame(mb_strlen($post->voiceover), $this->tenant()->runAs($org, fn () => app(UsageMeter::class)->thisMonth('voice_chars')));
        $this->get("{$base}/voce")->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        $this->get($base)->assertOk()->assertSee('voice-audio', false)->assertSee('Generează din nou vocea');
        $this->get("/app/{$org->slug}/birou")->assertOk()->assertSee('Seniorul audio')->assertSee('Vocile săptămânii sunt înregistrate.');

        // plafonul lunar
        $this->tenant()->runAs($org, fn () => app(UsageMeter::class)->increment('voice_chars', 40_000));
        $this->post("{$base}/voce")->assertSessionHas('error', 'Plafonul lunar de voce al firmei a fost atins.');

        $this->delete("{$base}/voce")->assertSessionHas('ok');
        Storage::disk('local')->assertMissing($fresh->voice_path);
        $this->get("{$base}/voce")->assertNotFound();
    }

    public function test_a_voice_belongs_to_its_firm(): void
    {
        Storage::fake('local');
        $this->fakeService();
        [$a, $ownerA] = $this->firm('Firma A');
        [$b, , $postB] = $this->firm('Firma B');
        Storage::disk('local')->put('social/b/voce.mp3', 'audio');
        $postB->forceFill(['voice_path' => 'social/b/voce.mp3'])->save();

        $this->actingAs($ownerA)->get("/app/{$a->slug}/social/postari/{$postB->id}/voce")->assertNotFound();
        $this->post("/app/{$a->slug}/social/postari/{$postB->id}/voce")->assertNotFound();
        $this->delete("/app/{$a->slug}/social/postari/{$postB->id}/voce")->assertNotFound();
        $this->get("/app/{$b->slug}/social/postari/{$postB->id}/voce")->assertNotFound();
        Storage::disk('local')->assertExists('social/b/voce.mp3');
        Http::assertNothingSent();

        $viewer = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($b, fn () => Membership::create(['user_id' => $viewer->id, 'role' => OrgRole::Viewer]));
        $this->actingAs($viewer)->post("/app/{$b->slug}/social/postari/{$postB->id}/voce")->assertForbidden();
    }
}
