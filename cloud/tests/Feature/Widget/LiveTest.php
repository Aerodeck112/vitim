<?php

declare(strict_types=1);

namespace Tests\Feature\Widget;

use App\Ai\AiClient;
use App\Enums\OrgRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\SiteVisitor;
use App\Models\User;
use App\Services\AgentService;
use App\Services\IssuedSiteKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeAiClient;
use Tests\TestCase;

final class LiveTest extends TestCase
{
    use RefreshDatabase;

    private function widget(string $path, array $body, string $origin = 'https://podreg.ro'): TestResponse
    {
        return $this->call('POST', '/widget/v1/'.$path, [], [], [], ['CONTENT_TYPE' => 'text/plain;charset=UTF-8', 'HTTP_ORIGIN' => $origin], (string) json_encode($body));
    }

    /** @return array{0: Organization, 1: IssuedSiteKey, 2: User} */
    private function firm(string $name = 'Podreg', string $domain = 'podreg.ro'): array
    {
        $owner = User::factory()->create(['last_login_at' => now()]);
        $org = $this->makeOrganization($name, $owner);
        [$site, $key] = $this->makeSite($org, $domain);
        $this->tenant()->runAs($org, fn () => app(AgentService::class)->create([
            'name' => 'Asistent', 'site_id' => $site->id, 'status' => 'active', 'template' => 'generic',
            'system_configuration' => ['business_facts' => 'Cabane din lemn, 375 EUR/mp.', 'engine' => 'local'],
        ]));

        return [$org, $key, $owner];
    }

    public function test_visitors_on_the_site_are_counted_anonymously_and_leave(): void
    {
        [$org, $key, $owner] = $this->firm();
        $a = str_repeat('a', 24);
        $b = str_repeat('b', 24);

        $this->widget('presence', ['key' => $key->publicKey, 'v' => $a, 'page' => 'https://podreg.ro/cabane?email=ion@ex.ro', 'title' => 'Cabane'])->assertOk();
        $this->widget('presence', ['key' => $key->publicKey, 'v' => $a, 'page' => 'https://podreg.ro/cabane', 'title' => 'Cabane'])->assertOk(); // același vizitator, nu se dublează
        $this->widget('presence', ['key' => $key->publicKey, 'v' => $b, 'page' => '/contact', 'title' => 'Contact'])->assertOk();
        $this->widget('presence', ['key' => $key->publicKey, 'v' => 'scurt'])->assertStatus(422);
        $this->widget('presence', ['key' => $key->publicKey, 'v' => $a], 'https://evil.example')->assertForbidden();

        $rows = $this->tenant()->runAs($org, fn () => SiteVisitor::query()->get());
        $this->assertCount(2, $rows);
        $this->assertSame('/cabane', $rows->firstWhere('visitor', $a)->page, 'doar adresa paginii, fără parametri (pot conține date personale)');

        $this->actingAs($owner)->getJson("/app/{$org->slug}/acum")->assertOk()
            ->assertJson(['visitors' => 2])->assertJsonPath('sites.0.domain', 'podreg.ro')->assertJsonCount(2, 'sites.0.pages');
        $this->get("/app/{$org->slug}")->assertOk()->assertSee('pe site acum')->assertSee('Acum pe site');

        // plecare explicită (fila închisă) și plecare tăcută (fila ascunsă nu mai trimite semne)
        $this->widget('presence', ['key' => $key->publicKey, 'v' => $b, 'leave' => 1])->assertOk();
        $this->getJson("/app/{$org->slug}/acum")->assertJson(['visitors' => 1]);
        $this->travel(SiteVisitor::ACTIVE_SECONDS + 5)->seconds();
        $this->getJson("/app/{$org->slug}/acum")->assertJson(['visitors' => 0]);
    }

    public function test_new_visitor_messages_reach_the_open_panel_for_the_sound(): void
    {
        $this->app->instance(AiClient::class, new FakeAiClient);
        [$org, $key, $owner] = $this->firm();
        $this->actingAs($owner);

        $baseline = $this->getJson("/app/{$org->slug}/acum")->assertOk()->json();
        $this->assertSame(0, $baseline['last_id']);
        $this->assertSame([], $baseline['messages']);

        $token = $this->widget('start', ['key' => $key->publicKey, 'page' => 'https://podreg.ro/'])->assertOk()->json('token');
        $this->widget('message', ['key' => $key->publicKey, 'token' => $token, 'message' => 'Cât costă o cabană de 60 mp?'])->assertOk();

        $live = $this->getJson("/app/{$org->slug}/acum?after=0")->json();
        $this->assertGreaterThan(0, $live['last_id']);
        $this->assertSame([], $live['messages'], 'prima citire doar fixează punctul de plecare (fără sunet la deschiderea panoului)');

        $this->widget('message', ['key' => $key->publicKey, 'token' => $token, 'message' => 'Și livrați în Cluj?'])->assertOk();
        $live = $this->getJson("/app/{$org->slug}/acum?after={$live['last_id']}")->assertOk()->json();
        $this->assertCount(1, $live['messages']);
        $this->assertSame('Și livrați în Cluj?', $live['messages'][0]['preview']);
        $this->assertSame('Vizitator', $live['messages'][0]['from']);
        $this->assertStringContainsString("/app/{$org->slug}/conversatii/", $live['messages'][0]['url']);
        $this->assertSame([], $this->getJson("/app/{$org->slug}/acum?after={$live['last_id']}")->json('messages'), 'mesajul nu sună de două ori');
    }

    public function test_each_firm_sees_only_its_own_visitors_and_messages(): void
    {
        $this->app->instance(AiClient::class, new FakeAiClient);
        [$a, $keyA, $ownerA] = $this->firm('Podreg', 'podreg.ro');
        [$b, $keyB, $ownerB] = $this->firm('Altă firmă', 'alta-firma.ro');
        $this->widget('presence', ['key' => $keyB->publicKey, 'v' => str_repeat('c', 20), 'page' => '/'], 'https://alta-firma.ro')->assertOk();
        $token = $this->widget('start', ['key' => $keyB->publicKey], 'https://alta-firma.ro')->json('token');
        $this->widget('message', ['key' => $keyB->publicKey, 'token' => $token, 'message' => 'Mesaj secret pentru firma B'], 'https://alta-firma.ro')->assertOk();

        $live = $this->actingAs($ownerA)->getJson("/app/{$a->slug}/acum?after=1")->assertOk()->json();
        $this->assertSame(0, $live['visitors']);
        $this->assertSame([], $live['messages']);
        $this->getJson("/app/{$b->slug}/acum")->assertNotFound();

        // și rolul „doar vizualizare” vede cine e pe site
        $viewer = User::factory()->create(['last_login_at' => now()]);
        $this->tenant()->runAs($b, fn () => Membership::create(['user_id' => $viewer->id, 'role' => OrgRole::Viewer]));
        $this->actingAs($viewer)->getJson("/app/{$b->slug}/acum")->assertOk()->assertJson(['visitors' => 1]);
    }
}
