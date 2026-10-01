<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use App\Ai\AiClient;
use App\Enums\OrgRole;
use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\AgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAiClient;
use Tests\TestCase;

final class AgentTestPageTest extends TestCase
{
    use RefreshDatabase;

    private function member(Organization $org, OrgRole $role): User
    {
        $user = User::factory()->create();
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $user->id, 'role' => $role]));

        return $user;
    }

    private function agent(Organization $org): Agent
    {
        return $this->tenant()->runAs($org, fn () => app(AgentService::class)->create(['name' => 'Asistent', 'template' => 'generic']));
    }

    public function test_admin_tests_the_agent_from_the_panel(): void
    {
        $ai = new FakeAiClient([FakeAiClient::tool('create_lead', [
            'name' => 'Ana', 'phone' => '0722123456', 'email' => '', 'company' => '', 'requested_service' => '', 'product' => '',
            'budget' => '', 'preferred_date' => '', 'notes' => '', 'intent' => 'quote_request', 'summary' => 'Ofertă.', 'consent' => true,
        ]), FakeAiClient::text('Am notat, revenim.'), FakeAiClient::text('Cu plăcere.')]);
        $this->app->instance(AiClient::class, $ai);
        $org = $this->makeOrganization('Firma A');
        $agent = $this->agent($org);
        $this->actingAs($this->member($org, OrgRole::Admin));
        $base = "/app/{$org->slug}/agent/{$agent->id}";

        $this->get($base)->assertOk()->assertSee('Testează agentul')->assertSee('Istoric versiuni');
        $this->get("{$base}/test")->assertOk()->assertSee('Scrie un mesaj');

        $response = $this->post("{$base}/test", ['message' => 'Vreau o ofertă, sunați-mă la 0722123456, sunt de acord']);
        $conversation = $this->tenant()->runAs($org, fn () => Conversation::query()->sole());
        $response->assertRedirect("{$base}/test?c={$conversation->id}");
        $this->assertTrue($conversation->is_test);

        $this->get("{$base}/test?c={$conversation->id}")->assertOk()
            ->assertSee('Am notat, revenim.')->assertSee('create_lead · dry_run');
        $this->post("{$base}/test", ['message' => 'Mersi', 'conversation_id' => $conversation->id])->assertRedirect();
        $this->tenant()->runAs($org, function (): void {
            $this->assertSame(1, Conversation::query()->count());
            $this->assertSame(0, Lead::query()->count());
        });
    }

    public function test_test_page_is_isolated_between_organizations_and_roles(): void
    {
        $this->app->instance(AiClient::class, $ai = new FakeAiClient([FakeAiClient::text('A')]));
        $orgA = $this->makeOrganization('Firma A');
        $orgB = $this->makeOrganization('Firma B');
        $agentA = $this->agent($orgA);
        $agentB = $this->agent($orgB);
        $adminA = $this->member($orgA, OrgRole::Admin);

        $this->actingAs($adminA)->post("/app/{$orgA->slug}/agent/{$agentA->id}/test", ['message' => 'Salut'])->assertRedirect();
        $conversationA = $this->tenant()->runAs($orgA, fn () => Conversation::query()->sole());

        // admin B: nu vede agentul sau conversația firmei A, nici prin URL-ul firmei lui
        $adminB = $this->member($orgB, OrgRole::Admin);
        $this->actingAs($adminB);
        $this->get("/app/{$orgA->slug}/agent/{$agentA->id}/test")->assertNotFound();
        $this->get("/app/{$orgB->slug}/agent/{$agentA->id}/test")->assertNotFound();
        $this->post("/app/{$orgB->slug}/agent/{$agentA->id}/test", ['message' => 'x'])->assertNotFound();
        $this->get("/app/{$orgB->slug}/agent/{$agentB->id}/test?c={$conversationA->id}")->assertNotFound();
        $this->post("/app/{$orgB->slug}/agent/{$agentB->id}/test", ['message' => 'x', 'conversation_id' => $conversationA->id])->assertNotFound();

        // un vizualizator nu poate consuma din plafonul de cost
        $this->actingAs($this->member($orgA, OrgRole::Viewer));
        $this->get("/app/{$orgA->slug}/agent/{$agentA->id}/test")->assertForbidden();
        $this->post("/app/{$orgA->slug}/agent/{$agentA->id}/test", ['message' => 'x'])->assertForbidden();

        $this->assertCount(1, $ai->requests);
    }

    public function test_agent_form_saves_business_facts_and_creates_a_new_version(): void
    {
        $org = $this->makeOrganization('Firma A');
        $agent = $this->agent($org);
        $this->actingAs($this->member($org, OrgRole::Admin));
        $this->put("/app/{$org->slug}/agent/{$agent->id}", [
            'name' => 'Asistent', 'business_facts' => 'ITP: 150 lei.', 'contact_line' => '0740 000 000',
            'allowed_actions' => ['create_lead'], 'required_fields' => ['name', 'phone'],
        ])->assertRedirect();

        $this->tenant()->runAs($org, function () use ($agent): void {
            $fresh = $agent->fresh();
            $this->assertSame('ITP: 150 lei.', $fresh->system_configuration['business_facts']);
            $this->assertSame([2, 1], $fresh->versions()->pluck('version')->all());
        });
    }

    public function test_agent_can_be_created_from_a_template_in_the_panel(): void
    {
        $org = $this->makeOrganization('Firma A');
        $this->actingAs($this->member($org, OrgRole::Admin));
        $this->post("/app/{$org->slug}/agent", ['name' => 'Clinica', 'template' => 'clinic'])->assertRedirect();
        $this->post("/app/{$org->slug}/agent", ['name' => 'X', 'template' => 'nu-exista'])->assertSessionHasErrors('template');

        $agent = $this->tenant()->runAs($org, fn () => Agent::query()->sole());
        $this->assertSame('clinic', $agent->template);
        $this->assertStringContainsString('112', (string) $agent->system_configuration['instructions']);
    }
}
