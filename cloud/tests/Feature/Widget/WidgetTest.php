<?php

declare(strict_types=1);

namespace Tests\Feature\Widget;

use App\Ai\AiClient;
use App\Enums\OrgRole;
use App\Mail\TeamAlert;
use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Services\AgentService;
use App\Services\IssuedSiteKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeAiClient;
use Tests\TestCase;

final class WidgetTest extends TestCase
{
    use RefreshDatabase;

    private function call_(string $path, array $body, string $origin = 'https://podreg.ro'): TestResponse
    {
        return $this->call('POST', '/widget/v1/'.$path, [], [], [], ['CONTENT_TYPE' => 'text/plain;charset=UTF-8', 'HTTP_ORIGIN' => $origin], (string) json_encode($body));
    }

    /** @return array{0: Organization, 1: Site, 2: IssuedSiteKey, 3: Agent} */
    private function setup_(string $status = 'active'): array
    {
        $org = $this->makeOrganization('Podreg');
        [$site, $key] = $this->makeSite($org, 'podreg.ro');
        $agent = $this->tenant()->runAs($org, fn () => app(AgentService::class)->create([
            'name' => 'Asistent Podreg', 'site_id' => $site->id, 'status' => $status, 'template' => 'generic',
            'system_configuration' => ['greeting' => 'Bună! Cu ce te ajut?', 'business_facts' => 'Cabane din lemn, 375 EUR/mp.'],
        ]));

        return [$org, $site, $key, $agent];
    }

    public function test_config_respects_origin_and_agent_status(): void
    {
        [$org, $site, $key, $agent] = $this->setup_('draft');
        $this->call_('config', ['key' => $key->publicKey], 'https://evil.example')->assertForbidden()->assertHeaderMissing('Access-Control-Allow-Origin');
        $this->call_('config', ['key' => 'pk_inexistent'])->assertForbidden();
        $this->call_('config', ['key' => $key->publicKey])->assertOk()->assertJson(['enabled' => false])->assertHeader('Access-Control-Allow-Origin', 'https://podreg.ro');

        $this->tenant()->runAs($org, fn () => app(AgentService::class)->update($agent, ['status' => 'active']));
        $this->call_('config', ['key' => $key->publicKey], 'https://www.podreg.ro')->assertOk()
            ->assertJson(['enabled' => true, 'greeting' => 'Bună! Cu ce te ajut?', 'title' => 'Asistent Podreg', 'position' => 'right']);

        $site->forceFill(['widget_config' => ['enabled' => false]])->save();
        $this->call_('config', ['key' => $key->publicKey])->assertJson(['enabled' => false]);
        $this->call_('start', ['key' => $key->publicKey])->assertStatus(409);
    }

    public function test_visitor_chats_leaves_a_lead_and_the_team_gets_an_email(): void
    {
        Mail::fake();
        $this->app->instance(AiClient::class, new FakeAiClient([
            FakeAiClient::text('Prețul orientativ e 375 EUR/mp.'),
            FakeAiClient::tool('create_lead', ['name' => 'Ion Pop', 'phone' => '0722123456', 'email' => '', 'company' => '', 'requested_service' => 'Cabană',
                'product' => '', 'budget' => '', 'preferred_date' => '', 'notes' => '', 'intent' => 'quote_request', 'summary' => 'Vrea o cabană de 60 mp.', 'consent' => true]),
            FakeAiClient::text('Mulțumim, te sunăm curând.'),
        ]));
        [$org, $site, $key] = $this->setup_();
        $owner = User::factory()->create(['email' => 'owner@podreg.ro']);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $owner->id, 'role' => OrgRole::Owner]));

        $token = $this->call_('start', ['key' => $key->publicKey, 'page' => 'https://podreg.ro/cabane'])->assertOk()->json('token');
        $this->assertSame(48, strlen($token));
        $this->call_('message', ['key' => $key->publicKey, 'token' => $token, 'message' => 'Cât costă o cabană?'])->assertOk()->assertJson(['reply' => 'Prețul orientativ e 375 EUR/mp.', 'status' => 'ok']);
        $this->call_('message', ['key' => $key->publicKey, 'token' => $token, 'message' => 'Ion, 0722123456, da, sunați-mă'])->assertOk()->assertJson(['reply' => 'Mulțumim, te sunăm curând.']);
        $this->call_('history', ['key' => $key->publicKey, 'token' => $token])->assertOk()->assertJsonCount(4, 'messages')->assertJsonPath('messages.0.role', 'visitor');

        $this->tenant()->runAs($org, function () use ($site): void {
            $conversation = Conversation::query()->sole();
            $this->assertFalse($conversation->is_test);
            $this->assertSame($site->id, $conversation->site_id);
            $this->assertSame('https://podreg.ro/cabane', $conversation->visitor_page);
            $this->assertNotNull(Lead::query()->where('conversation_id', $conversation->id)->first());
        });

        $this->artisan('vitim:events')->assertSuccessful();
        Mail::assertSent(TeamAlert::class, fn (TeamAlert $m) => $m->hasTo('owner@podreg.ro') && str_contains($m->title, 'Ion Pop') && str_contains(implode(' ', $m->lines), '+40722123456'));

        // clientul vede conversația în panou
        $this->actingAs($owner);
        $this->get("/app/{$org->slug}/conversatii")->assertOk()->assertSee('Ion Pop')->assertSee('lead');
        $id = $this->tenant()->runAs($org, fn () => Conversation::query()->sole()->id);
        $this->get("/app/{$org->slug}/conversatii/{$id}")->assertOk()->assertSee('Cât costă o cabană?')->assertSee('cerere salvată');
    }

    public function test_tokens_and_conversations_are_isolated(): void
    {
        $this->app->instance(AiClient::class, new FakeAiClient([FakeAiClient::text('A')]));
        [$orgA, , $keyA] = $this->setup_();
        $orgB = $this->makeOrganization('Alta');
        [$siteB, $keyB] = $this->makeSite($orgB, 'alta.ro');
        $this->tenant()->runAs($orgB, fn () => app(AgentService::class)->create(['name' => 'B', 'site_id' => $siteB->id, 'status' => 'active']));

        $token = $this->call_('start', ['key' => $keyA->publicKey])->json('token');
        // tokenul firmei A nu merge cu cheia firmei B (și nici cu Origin-ul ei)
        $this->call_('message', ['key' => $keyB->publicKey, 'token' => $token, 'message' => 'x'], 'https://alta.ro')->assertNotFound();
        $this->call_('history', ['key' => $keyB->publicKey, 'token' => $token], 'https://alta.ro')->assertNotFound();
        $this->call_('message', ['key' => $keyA->publicKey, 'token' => str_repeat('x', 48), 'message' => 'x'])->assertNotFound();

        // conversațiile de test din panou nu apar în inbox și nu se pot continua din widget
        $owner = User::factory()->create();
        $this->tenant()->runAs($orgB, fn () => Membership::create(['user_id' => $owner->id, 'role' => OrgRole::Owner]));
        $this->actingAs($owner);
        $idA = $this->tenant()->runAs($orgA, fn () => Conversation::query()->sole()->id);
        $this->get("/app/{$orgB->slug}/conversatii/{$idA}")->assertNotFound();
    }

    public function test_abuse_limits(): void
    {
        $this->app->instance(AiClient::class, new FakeAiClient(array_fill(0, 50, FakeAiClient::text('ok'))));
        [, , $key] = $this->setup_();
        $token = $this->call_('start', ['key' => $key->publicKey])->json('token');
        $this->call_('message', ['key' => $key->publicKey, 'token' => $token, 'message' => str_repeat('a', 1001)])->assertStatus(422);
        for ($i = 0; $i < 30; $i++) {
            $this->call_('message', ['key' => $key->publicKey, 'token' => $token, 'message' => "m{$i}"])->assertOk();
        }
        $this->call_('message', ['key' => $key->publicKey, 'token' => $token, 'message' => 'încă unul'])->assertStatus(429);
    }

    public function test_widget_settings_are_saved_from_the_agent_page(): void
    {
        [$org, $site, $key, $agent] = $this->setup_();
        $admin = User::factory()->create();
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $admin->id, 'role' => OrgRole::Admin]));
        $this->actingAs($admin);
        $this->get("/app/{$org->slug}/agent/{$agent->id}")->assertOk()->assertSee('Agentul pe site')->assertSee('widget/v1/loader.js');
        $this->put("/app/{$org->slug}/agent/{$agent->id}/widget", ['enabled' => '1', 'color' => '#00AA55', 'position' => 'left', 'launcher' => 'Ai o întrebare?', 'privacy_url' => 'https://podreg.ro/gdpr'])->assertRedirect();
        $saved = Site::withoutTenancy()->find($site->id)->widget_config;
        $this->assertSame(['enabled' => true, 'color' => '#00aa55', 'position' => 'left', 'title' => null, 'launcher' => 'Ai o întrebare?', 'privacy_url' => 'https://podreg.ro/gdpr'],
            array_intersect_key($saved, array_flip(['enabled', 'color', 'position', 'title', 'launcher', 'privacy_url'])));
        $this->assertSame([], $saved['quick_replies']);
        $this->assertFalse($saved['sound']);

        $this->put("/app/{$org->slug}/agent/{$agent->id}/widget", ['enabled' => '1', 'quick_replies' => "Preț\n\n Program \nA\nB\nC", 'proactive_delay' => 15,
            'hours_start' => '08:30', 'hours_end' => '17:00', 'weekends' => '1', 'sound' => '1', 'avatar_url' => 'https://podreg.ro/logo.png'])->assertRedirect();
        $saved = Site::withoutTenancy()->find($site->id)->widget_config;
        $this->assertSame(['Preț', 'Program', 'A', 'B'], $saved['quick_replies']);
        $this->assertSame([15, '08:30', '17:00', true, true], [$saved['proactive_delay'], $saved['hours_start'], $saved['hours_end'], $saved['weekends'], $saved['sound']]);
        $this->call_('config', ['key' => $key->publicKey])->assertOk()->assertJson(['quick_replies' => ['Preț', 'Program', 'A', 'B'], 'proactive_delay' => 15, 'avatar_url' => 'https://podreg.ro/logo.png']);
        $this->put("/app/{$org->slug}/agent/{$agent->id}/widget", ['avatar_url' => 'http://podreg.ro/logo.png'])->assertSessionHasErrors('avatar_url');
        $this->put("/app/{$org->slug}/agent/{$agent->id}/widget", ['privacy_url' => 'javascript:alert(1)'])->assertSessionHasErrors('privacy_url');
    }
}
