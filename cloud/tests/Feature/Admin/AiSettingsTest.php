<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Ai\AgentRuntime;
use App\Ai\AiClient;
use App\Enums\Channel;
use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\PlatformSetting;
use App\Models\Site;
use App\Models\User;
use App\Services\AgentService;
use App\Services\AiKey;
use App\Services\WidgetSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAiClient;
use Tests\TestCase;

final class AiSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'sk-ant-api03-abcdefghijklmnopqrstuvwxyz0123456789-WXYZ';

    private FakeAiClient $ai;

    protected function setUp(): void
    {
        parent::setUp();
        config(['vitim.ai.api_key' => '']);
        $this->ai = new FakeAiClient([FakeAiClient::text('Răspuns AI.')]);
        $this->app->instance(AiClient::class, $this->ai);
    }

    public function test_page_is_only_for_super_admin(): void
    {
        $owner = User::factory()->create();
        $org = $this->makeOrganization('Firma A', $owner);
        $this->makeSite($org, 'firma-a.ro');

        $this->actingAs($owner)->get('/admin/agent-ai')->assertNotFound();
        $this->actingAs($this->staff(PlatformRole::VitimAdmin))->get('/admin/agent-ai')->assertForbidden();
        $this->actingAs($this->staff())->get('/admin/agent-ai')->assertOk()->assertSee('firma-a.ro')->assertSee('Firma A')->assertSee('lipsește');
    }

    public function test_key_is_saved_encrypted_tested_and_never_shown_whole(): void
    {
        $admin = $this->staff();
        $this->actingAs($admin)->put('/admin/agent-ai/cheie', ['key' => self::KEY, 'password' => 'password'])->assertRedirect('/admin/agent-ai');

        $this->assertSame(self::KEY, AiKey::current());
        $this->assertSame('panel', AiKey::source());
        $raw = (string) json_encode(PlatformSetting::query()->find(AiKey::SETTING)?->value);
        $this->assertStringNotContainsString('abcdefghijklmnop', $raw, 'cheia nu stă în clar în baza de date');
        $this->assertTrue(AuditLog::withoutTenancy()->where('action', 'platform.ai_key_saved')->exists());

        $this->get('/admin/agent-ai')->assertOk()->assertSee('Conexiune reușită')->assertSee('WXYZ')->assertDontSee(self::KEY);
    }

    public function test_key_needs_password_and_valid_format(): void
    {
        $this->actingAs($this->staff());
        $this->put('/admin/agent-ai/cheie', ['key' => self::KEY, 'password' => 'gresit'])->assertSessionHasErrors('password');
        $this->put('/admin/agent-ai/cheie', ['key' => 'cheie cu spatii si fara prefix', 'password' => 'password'])->assertSessionHasErrors('key');
        $this->assertSame('none', AiKey::source());
    }

    public function test_test_button_explains_failures(): void
    {
        $this->ai->checkResult = ['ok' => false, 'reason' => 'billing', 'detail' => 'credit balance too low'];
        $this->actingAs($this->staff())->post('/admin/agent-ai/test')->assertRedirect('/admin/agent-ai');
        $this->get('/admin/agent-ai')->assertSee('Nu funcționează')->assertSee('Contul nu are credit');
    }

    public function test_switches_turn_ai_and_chat_on_and_off_per_site(): void
    {
        $org = $this->makeOrganization('Firma A');
        [$site] = $this->makeSite($org, 'firma-a.ro');
        $this->actingAs($this->staff());

        $this->postJson("/admin/agent-ai/site-uri/{$site->id}", ['field' => 'ai', 'value' => 0])->assertOk()->assertJson(['on' => false]);
        $this->postJson("/admin/agent-ai/site-uri/{$site->id}", ['field' => 'chat', 'value' => 0])->assertOk()->assertJson(['on' => false]);
        $fresh = Site::withoutTenancy()->findOrFail($site->id);
        $this->assertFalse($fresh->ai_enabled);
        $this->assertFalse(WidgetSettings::for($fresh)['enabled']);

        $this->post('/admin/agent-ai/toate', ['value' => 1])->assertRedirect();
        $this->assertTrue(Site::withoutTenancy()->findOrFail($site->id)->ai_enabled);
        $this->assertSame(2, AuditLog::withoutTenancy()->whereIn('action', ['site.ai_toggled', 'site.chat_toggled'])->count());
    }

    public function test_agent_answers_without_ai_when_ai_is_off_on_its_site(): void
    {
        config(['vitim.ai.api_key' => self::KEY]);
        $org = $this->makeOrganization('Service Auto');
        [$site] = $this->makeSite($org, 'service-auto.ro');
        $site->forceFill(['ai_enabled' => false])->save();
        $agent = $this->tenant()->runAs($org, fn () => app(AgentService::class)->create([
            'site_id' => $site->id, 'name' => 'Asistent', 'status' => 'active', 'template' => 'auto_service',
            'system_configuration' => ['business_facts' => 'Program: luni–vineri 8–17.', 'contact_line' => '0740 000 000'],
        ]));
        $conversation = $this->tenant()->runAs($org, fn () => Conversation::create([
            'agent_id' => $agent->id, 'channel' => Channel::Web, 'status' => 'open', 'mode' => 'ai', 'is_test' => false,
        ]));

        $reply = $this->tenant()->runAs($org, fn () => app(AgentRuntime::class)->reply($conversation, 'Ce program aveți?', '203.0.113.5', 'PHPUnit'));

        $this->assertSame([], $this->ai->requests, 'niciun apel AI când AI-ul e oprit pe site');
        $this->assertSame('local', $reply->engine);
    }
}
