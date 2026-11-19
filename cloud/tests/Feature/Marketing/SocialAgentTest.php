<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Ai\AiClient;
use App\Enums\OrgRole;
use App\Mail\TeamAlert;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\SocialPost;
use App\Models\User;
use App\Services\UsageMeter;
use App\Social\SocialPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakeAiClient;
use Tests\TestCase;

/** Agentul de social media: planul săptămânii din datele firmei, aprobare de către un om, postarea zilei pe email. */
final class SocialAgentTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Organization, 1: User} */
    private function firm(string $name, OrgRole $role = OrgRole::Owner): array
    {
        $org = $this->makeOrganization($name);
        $org->forceFill(['public_profile' => ['about' => 'Cabane din lemn la comandă, în județul Mureș.']])->save();
        $user = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $user->id, 'role' => $role]));

        return [$org, $user];
    }

    /** @param list<string> $days @return array<string, mixed> */
    private function answer(array $days, string $prefix = 'Cabana'): array
    {
        return FakeAiClient::text("Iată planul:\n".json_encode(['posts' => array_map(fn (string $d) => [
            'day' => $d, 'topic' => "{$prefix} {$d}", 'hook' => "{$prefix} ta din lemn", 'caption' => "Construim cabane la comandă. ({$d})",
            'hashtags' => ['#cabane', 'lemn masiv!', '#mureș'], 'visual' => 'Fotografie cu o cabană terminată, la apus.',
            'scenes' => [['text' => 'De la fundație', 'shot' => 'cadru cu fundația', 'seconds' => 3], ['text' => 'la cheie', 'shot' => 'ușa deschisă', 'seconds' => 30]],
        ], $days)], JSON_UNESCAPED_UNICODE));
    }

    /** @return list<string> */
    private function week(Carbon $monday): array
    {
        return array_map(fn (int $i) => $monday->copy()->addDays($i)->toDateString(), range(0, 6));
    }

    public function test_agent_writes_the_week_from_firm_data_and_a_person_approves(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 10:00', 'Europe/Bucharest'));
        $monday = SocialPlanner::monday();
        $ai = new FakeAiClient([$this->answer($this->week($monday)), $this->answer([$monday->toDateString()], 'Altă abordare')]);
        $this->app->instance(AiClient::class, $ai);
        [$org, $owner] = $this->firm('Podreg');
        $base = "/app/{$org->slug}/social";

        $this->actingAs($owner)->get($base)->assertOk()->assertSee('Scrie planul săptămânii')->assertSee('Nicio postare încă.');
        $this->post("{$base}/plan", ['s' => $monday->toDateString()])->assertSessionHas('ok', fn ($m) => str_contains($m, '7 postări'));
        $this->assertStringContainsString('Cabane din lemn la comandă', $ai->requests[0]['system']);
        $this->assertStringContainsString('Nu inventezi prețuri', $ai->requests[0]['system']);
        $this->assertGreaterThan(0, $this->tenant()->runAs($org, fn () => app(UsageMeter::class)->thisMonth('ai_cost_micro_usd')));

        $posts = $this->tenant()->runAs($org, fn () => SocialPost::query()->orderBy('day')->get());
        $this->assertCount(7, $posts);
        $first = $posts[0];
        $this->assertSame('draft', $first->status);
        $this->assertSame('#cabane #lemnmasiv #mureș', $first->hashtags);
        $this->assertSame(10, $first->scenes[1]['seconds'], 'durata unei scene e limitată');
        $this->get($base)->assertOk()->assertSee('Cabana ta din lemn')->assertSee('Aprobă');

        // omul corectează, aprobă și marchează publicarea
        $this->put("{$base}/postari/{$first->id}", ['day' => $first->day->toDateString(), 'topic' => 'Cabane', 'hook' => 'Cabana ta, la cheie', 'caption' => 'Text corectat.',
            'hashtags' => '#cabane', 'design' => '{"fit":"contain","zoom":120}', 'scenes' => [['text' => 'O scenă', 'shot' => '', 'seconds' => 4], ['text' => '', 'shot' => 'x', 'seconds' => 4]]])->assertSessionHas('ok');
        $this->post("{$base}/postari/{$first->id}/stare", ['status' => 'approved'])->assertSessionHas('ok');
        $this->get("{$base}/postari/{$first->id}")->assertOk()->assertSee('Cabana ta, la cheie')->assertSee('Editor foto')->assertSee('Editor video')->assertSee('Am publicat-o');
        $this->assertCount(1, $first->fresh()->scenes);
        $this->assertSame(['fit' => 'contain', 'zoom' => 120], $first->fresh()->design);

        // rescrierea săptămânii nu atinge postarea aprobată
        $ai2 = new FakeAiClient([$this->answer($this->week($monday), 'Varianta nouă')]);
        $this->app->instance(AiClient::class, $ai2);
        $this->post("{$base}/plan", ['s' => $monday->toDateString()])->assertSessionHas('ok', fn ($m) => str_contains($m, '6 postări'));
        $this->assertStringNotContainsString($monday->toDateString().' (luni)', $ai2->requests[0]['messages'][0]['content']);
        $this->assertSame('Text corectat.', $first->fresh()->caption);
        $this->assertSame(7, $this->tenant()->runAs($org, fn () => SocialPost::query()->count()));

        // un răspuns care nu e JSON nu strică nimic
        $this->app->instance(AiClient::class, new FakeAiClient([FakeAiClient::text('Nu pot.')]));
        $this->post("{$base}/postari/{$first->id}/rescrie", ['instruction' => 'mai scurt'])->assertSessionHas('error');
        $this->assertSame('Text corectat.', $first->fresh()->caption);
    }

    public function test_without_ai_the_page_explains_and_manual_posts_still_work(): void
    {
        $ai = new FakeAiClient;
        $ai->configured = false;
        $this->app->instance(AiClient::class, $ai);
        [$org, $owner] = $this->firm('Podreg');
        $base = "/app/{$org->slug}/social";

        $this->actingAs($owner)->get($base)->assertOk()->assertSee('nu poate scrie acum');
        $this->post("{$base}/plan", ['s' => SocialPlanner::monday()->toDateString()])->assertSessionHas('error');
        $this->assertSame([], $ai->requests);
        $this->post("{$base}/postari", ['day' => now()->toDateString()])->assertRedirect();
        $this->assertSame('manual', $this->tenant()->runAs($org, fn () => SocialPost::query()->sole())->source);
    }

    public function test_weekly_plan_and_daily_reminder_run_on_their_own(): void
    {
        Mail::fake();
        [$org, $owner] = $this->firm('Podreg');
        [$other] = $this->firm('Fără social');
        $this->travelTo(Carbon::parse('2026-11-22 17:10', 'Europe/Bucharest')); // duminică
        $next = SocialPlanner::monday()->addWeek();
        $ai = new FakeAiClient([$this->answer($this->week($next))]);
        $this->app->instance(AiClient::class, $ai);

        $this->artisan('vitim:social')->assertSuccessful();
        $this->assertSame([], $ai->requests, 'planul automat e oprit implicit');

        $this->actingAs($owner)->put("/app/{$org->slug}/social/setari", ['platforms' => ['facebook', 'tiktok'], 'days' => [1, 3, 5], 'color' => '#aa3300', 'auto' => 1, 'remind' => 1])->assertSessionHas('ok');
        $this->artisan('vitim:social')->assertSuccessful();
        $this->artisan('vitim:social')->assertSuccessful();
        $this->assertCount(1, $ai->requests, 'o singură scriere pe săptămână');
        $this->assertSame(3, $this->tenant()->runAs($org, fn () => SocialPost::query()->count()), 'doar zilele alese');
        $this->assertSame(0, $this->tenant()->runAs($other, fn () => SocialPost::query()->count()));

        // luni dimineața: postarea zilei pe email, o singură dată
        $this->travelTo(Carbon::parse($next->toDateString().' 08:20', 'Europe/Bucharest'));
        $this->artisan('vitim:social')->assertSuccessful();
        $this->artisan('vitim:social')->assertSuccessful();
        Mail::assertSent(TeamAlert::class, 1);
        Mail::assertSent(TeamAlert::class, fn (TeamAlert $m) => $m->hasTo($owner->email) && str_contains($m->title, 'Postarea de azi') && str_contains(implode("\n", $m->lines), 'Construim cabane'));
    }

    public function test_permissions_and_isolation_between_firms(): void
    {
        $this->app->instance(AiClient::class, new FakeAiClient);
        [$a, $ownerA] = $this->firm('Firma A');
        [$b] = $this->firm('Firma B');
        $theirs = $this->tenant()->runAs($b, fn () => SocialPost::create(['day' => now()->toDateString(), 'topic' => 'Secret B', 'hook' => 'Titlu B', 'caption' => 'Text B', 'status' => 'draft', 'source' => 'manual']));

        $this->actingAs($ownerA)->get("/app/{$a->slug}/social")->assertOk()->assertDontSee('Titlu B');
        $this->get("/app/{$a->slug}/social/postari/{$theirs->id}")->assertNotFound();
        $this->put("/app/{$a->slug}/social/postari/{$theirs->id}", ['day' => now()->toDateString(), 'topic' => 'x', 'hook' => 'x', 'caption' => 'x'])->assertNotFound();
        $this->post("/app/{$a->slug}/social/postari/{$theirs->id}/stare", ['status' => 'approved'])->assertNotFound();
        $this->post("/app/{$a->slug}/social/postari/{$theirs->id}/rescrie")->assertNotFound();
        $this->delete("/app/{$a->slug}/social/postari/{$theirs->id}")->assertNotFound();
        $this->get("/app/{$b->slug}/social")->assertNotFound();
        $this->assertSame('Text B', $theirs->fresh()->caption);

        $viewer = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($a, fn () => Membership::create(['user_id' => $viewer->id, 'role' => OrgRole::Viewer]));
        $this->actingAs($viewer)->get("/app/{$a->slug}/social")->assertForbidden();
        $this->post("/app/{$a->slug}/social/plan", ['s' => now()->toDateString()])->assertForbidden();
    }
}
