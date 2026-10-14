<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use App\Models\User;
use App\Services\AgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Exemplul de „Informații despre firmă” din pagina agentului ține de domeniul agentului, nu de un anumit client. */
final class AgentExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_example_matches_the_agent_template_and_names_no_client(): void
    {
        $owner = User::factory()->create(['last_login_at' => now()]);
        $org = $this->makeOrganization('Firma Test', $owner);
        $this->actingAs($owner);
        $agent = $this->tenant()->runAs($org, fn () => app(AgentService::class)->create(['name' => 'Asistent', 'status' => 'draft', 'template' => 'generic']));
        foreach (['auto_service' => 'schimb ulei', 'clinic' => 'consultație', 'ecommerce' => 'Retur', 'generic' => 'Zonă'] as $template => $expected) {
            $agent->forceFill(['template' => $template])->save();
            $this->get("/app/{$org->slug}/agent/{$agent->id}")->assertOk()->assertSee($expected)->assertSee('Exemplu orientativ')
                ->assertDontSee('cabane')->assertDontSee('cabană');
        }
    }
}
