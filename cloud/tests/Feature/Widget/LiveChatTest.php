<?php

declare(strict_types=1);

namespace Tests\Feature\Widget;

use App\Ai\AiClient;
use App\Enums\OrgRole;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Services\AgentService;
use App\Services\IssuedSiteKey;
use App\Services\WidgetSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeAiClient;
use Tests\TestCase;

final class LiveChatTest extends TestCase
{
    use RefreshDatabase;

    private FakeAiClient $ai;

    private function call_(string $path, array $body, string $origin = 'https://podreg.ro'): TestResponse
    {
        return $this->call('POST', '/widget/v1/'.$path, [], [], [], ['CONTENT_TYPE' => 'text/plain;charset=UTF-8', 'HTTP_ORIGIN' => $origin], (string) json_encode($body));
    }

    /** @return array{0: Organization, 1: Site, 2: IssuedSiteKey, 3: User, 4: string} */
    private function chat(): array
    {
        $this->ai = new FakeAiClient([FakeAiClient::text('Bună! Cu ce te ajut?'), FakeAiClient::text('Din nou eu, asistentul.')]);
        $this->app->instance(AiClient::class, $this->ai);
        $org = $this->makeOrganization('Podreg');
        [$site, $key] = $this->makeSite($org, 'podreg.ro');
        $this->tenant()->runAs($org, fn () => app(AgentService::class)->create(['name' => 'Asistent', 'site_id' => $site->id, 'status' => 'active']));
        $agent = User::factory()->create(['name' => 'Ana Popescu']);
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $agent->id, 'role' => OrgRole::Agent]));
        $token = $this->call_('start', ['key' => $key->publicKey])->json('token');
        $this->call_('message', ['key' => $key->publicKey, 'token' => $token, 'message' => 'Salut'])->assertOk()->assertJsonPath('messages.0.text', 'Bună! Cu ce te ajut?');

        return [$org, $site, $key, $agent, $token];
    }

    private function conversationId(Organization $org): int
    {
        return $this->tenant()->runAs($org, fn () => Conversation::query()->sole()->id);
    }

    public function test_operator_takes_over_and_chats_live_with_the_visitor(): void
    {
        [$org, , $key, $agent, $token] = $this->chat();
        $id = $this->conversationId($org);
        $base = "/app/{$org->slug}/conversatii/{$id}";
        $this->actingAs($agent);

        // vizitatorul e pe site (widgetul verifică mesajele), echipa vede asta
        $history = $this->call_('history', ['key' => $key->publicKey, 'token' => $token])->assertOk()->assertJson(['live' => false, 'typing' => false]);
        $last = $history->json('last_id');
        $this->get($base)->assertOk()->assertSee('Vizitatorul e pe site acum')->assertSee('Preia conversația');

        $this->post("{$base}/scrie")->assertOk();
        $this->postJson("{$base}/raspuns", ['message' => 'Bună, sunt Ana. Te pot ajuta eu.'])->assertOk();
        $this->call_('history', ['key' => $key->publicKey, 'token' => $token, 'after' => $last])->assertOk()
            ->assertJson(['live' => true, 'operator' => 'Ana'])
            ->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.role', 'operator')->assertJsonPath('messages.0.name', 'Ana');

        // în modul live mesajele vizitatorului nu mai ajung la AI
        $calls = count($this->ai->requests);
        $this->call_('message', ['key' => $key->publicKey, 'token' => $token, 'message' => 'Vreau o ofertă'])->assertOk()->assertJson(['reply' => null, 'status' => 'human']);
        $this->assertCount($calls, $this->ai->requests);
        $poll = $this->getJson("{$base}/mesaje?after={$last}")->assertOk()->assertJson(['live' => true, 'visitor_online' => true]);
        $this->assertSame(['op', 'me'], array_column($poll->json('messages'), 'class'));
        $this->get("/app/{$org->slug}/conversatii?status=live")->assertOk()->assertSee('live');

        // predat înapoi: răspunde din nou asistentul
        $this->post("{$base}/actiune", ['action' => 'release'])->assertRedirect();
        $this->call_('message', ['key' => $key->publicKey, 'token' => $token, 'message' => 'Mai e cineva?'])->assertOk()->assertJson(['reply' => 'Din nou eu, asistentul.']);

        $this->post("{$base}/actiune", ['action' => 'close'])->assertRedirect();
        $this->tenant()->runAs($org, fn () => $this->assertSame('closed', Conversation::query()->sole()->status->value));
        $messages = $this->call_('history', ['key' => $key->publicKey, 'token' => $token])->json('messages');
        $this->assertSame('system', end($messages)['role']);
    }

    public function test_viewers_cannot_reply_and_other_companies_get_404(): void
    {
        [$org] = $this->chat();
        $id = $this->conversationId($org);
        $viewer = User::factory()->create();
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $viewer->id, 'role' => OrgRole::Viewer]));
        $this->actingAs($viewer);
        $this->get("/app/{$org->slug}/conversatii/{$id}")->assertOk()->assertDontSee('Preia conversația');
        $this->postJson("/app/{$org->slug}/conversatii/{$id}/raspuns", ['message' => 'x'])->assertForbidden();

        $other = $this->makeOrganization('Alta');
        $stranger = User::factory()->create();
        $this->tenant()->runAs($other, fn () => Membership::create(['user_id' => $stranger->id, 'role' => OrgRole::Owner]));
        $this->actingAs($stranger);
        $this->getJson("/app/{$other->slug}/conversatii/{$id}/mesaje")->assertNotFound();
        $this->postJson("/app/{$other->slug}/conversatii/{$id}/raspuns", ['message' => 'x'])->assertNotFound();
        $this->post("/app/{$other->slug}/conversatii/{$id}/actiune", ['action' => 'take'])->assertNotFound();
        $this->tenant()->runAs($org, fn () => $this->assertSame('ai', Conversation::query()->sole()->mode));
    }

    public function test_visitor_leaves_email_with_consent(): void
    {
        [$org, , $key, , $token] = $this->chat();
        $this->call_('contact', ['key' => $key->publicKey, 'token' => $token, 'email' => 'ion@example.ro', 'consent' => false])->assertStatus(422);
        $this->call_('contact', ['key' => $key->publicKey, 'token' => $token, 'email' => 'nu-e-email', 'consent' => true])->assertStatus(422)->assertJson(['error' => 'invalid_email']);
        $this->call_('contact', ['key' => $key->publicKey, 'token' => str_repeat('x', 48), 'email' => 'ion@example.ro', 'consent' => true])->assertNotFound();
        $this->call_('contact', ['key' => $key->publicKey, 'token' => $token, 'name' => 'Ion', 'email' => 'ion@example.ro', 'consent' => true])->assertOk();
        $this->call_('history', ['key' => $key->publicKey, 'token' => $token])->assertJson(['has_contact' => true]);

        $this->tenant()->runAs($org, function (): void {
            $conversation = Conversation::query()->with('contact')->sole();
            $this->assertSame('ion@example.ro', $conversation->contact->email);
            $lead = Lead::query()->sole();
            $this->assertSame($conversation->id, $lead->conversation_id);
            $this->assertStringContainsString('Salut', (string) $lead->summary);
        });
    }

    public function test_office_hours(): void
    {
        $s = WidgetSettings::DEFAULTS;
        $this->assertTrue(WidgetSettings::online($s, Carbon::parse('2026-10-05 10:00', 'Europe/Bucharest')));   // luni
        $this->assertFalse(WidgetSettings::online($s, Carbon::parse('2026-10-05 18:30', 'Europe/Bucharest')));
        $this->assertFalse(WidgetSettings::online($s, Carbon::parse('2026-10-04 10:00', 'Europe/Bucharest')));  // duminică
        $this->assertTrue(WidgetSettings::online(['weekends' => true] + $s, Carbon::parse('2026-10-04 10:00', 'Europe/Bucharest')));
        $night = ['hours_start' => '22:00', 'hours_end' => '06:00', 'weekends' => true] + $s;
        $this->assertTrue(WidgetSettings::online($night, Carbon::parse('2026-10-05 23:00', 'Europe/Bucharest')));
        $this->assertFalse(WidgetSettings::online($night, Carbon::parse('2026-10-05 12:00', 'Europe/Bucharest')));
    }
}
