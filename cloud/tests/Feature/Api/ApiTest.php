<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ContactSource;
use App\Enums\OrgRole;
use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\AgentService;
use App\Services\ContactService;
use App\Services\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class ApiTest extends TestCase
{
    use RefreshDatabase;

    private function member(Organization $org, OrgRole $role): User
    {
        $user = User::factory()->create();
        $this->tenant()->runAs($org, fn () => Membership::create(['user_id' => $user->id, 'role' => $role]));

        return $user;
    }

    private function url(Organization $org, string $path = ''): string
    {
        return "/api/v1/orgs/{$org->slug}".$path;
    }

    public function test_errors_have_a_consistent_format(): void
    {
        $org = $this->makeOrganization('Firma A');
        $this->getJson($this->url($org, '/contacts'))->assertStatus(401)->assertJsonPath('error.code', 'unauthenticated');

        $owner = $this->member($org, OrgRole::Owner);
        $this->actingAs($owner)->postJson($this->url($org, '/contacts'), ['email' => str_repeat('a', 300)])
            ->assertStatus(422)->assertJsonPath('error.code', 'validation_failed')->assertJsonStructure(['error' => ['details' => ['email']]]);
        $this->getJson($this->url($org, '/contacts/999999'))->assertStatus(404)->assertJsonPath('error.code', 'not_found');
    }

    public function test_contact_crud_duplicates_search_and_consent(): void
    {
        $org = $this->makeOrganization('Firma A');
        $owner = $this->member($org, OrgRole::Owner);
        $this->actingAs($owner);

        $id = $this->postJson($this->url($org, '/contacts'), ['first_name' => 'Ion', 'email' => 'Ion@Example.test', 'phone' => '0722 000 001'])
            ->assertCreated()->assertJsonPath('data.email', 'ion@example.test')->assertJsonPath('data.source', 'api')
            ->assertJsonPath('data.organization_id', $org->id)->json('data.id');
        $this->postJson($this->url($org, '/contacts'), ['email' => 'ion@example.test'])
            ->assertStatus(409)->assertJsonPath('error.code', 'duplicate_contact')->assertJsonPath('error.details.existing_contact_id', $id);

        $this->getJson($this->url($org, '/contacts?q=ion'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
        $this->patchJson($this->url($org, "/contacts/{$id}"), ['company' => 'Demo SRL', 'source' => 'form'])->assertStatus(422);
        $this->patchJson($this->url($org, "/contacts/{$id}"), ['company' => 'Demo SRL'])->assertOk()->assertJsonPath('data.company', 'Demo SRL');

        $this->postJson($this->url($org, "/contacts/{$id}/consents"), ['channel' => 'email', 'purpose' => 'marketing', 'status' => 'granted', 'source' => 'formular'])
            ->assertCreated()->assertJsonPath('data.recorded_by_user_id', $owner->id);
        $this->postJson($this->url($org, "/contacts/{$id}/consents"), ['channel' => 'web', 'purpose' => 'marketing', 'status' => 'granted', 'source' => 'x'])->assertStatus(422);
        $this->getJson($this->url($org, "/contacts/{$id}"))->assertJsonPath('data.consents.email.marketing', 'granted')->assertJsonPath('data.consents.sms.marketing', 'unknown');
        $this->getJson($this->url($org, "/contacts/{$id}/consents"))->assertJsonCount(1, 'data');

        $this->deleteJson($this->url($org, "/contacts/{$id}"))->assertNoContent();
        $this->getJson($this->url($org, "/contacts/{$id}"))->assertNotFound();
    }

    public function test_leads_crud_and_contact_from_other_organization(): void
    {
        $a = $this->makeOrganization('Firma A');
        $b = $this->makeOrganization('Firma B');
        $owner = $this->member($a, OrgRole::Owner);
        $contactA = $this->tenant()->runAs($a, fn () => app(ContactService::class)->create(['first_name' => 'Ion'], ContactSource::Manual));
        $contactB = $this->tenant()->runAs($b, fn () => app(ContactService::class)->create(['first_name' => 'Străin'], ContactSource::Manual));
        $this->actingAs($owner);

        $this->postJson($this->url($a, '/leads'), ['contact_id' => $contactB->id, 'intent' => 'quote_request'])->assertStatus(422);
        $id = $this->postJson($this->url($a, '/leads'), ['contact_id' => $contactA->id, 'intent' => 'quote_request', 'summary' => 'Ofertă site', 'score' => 70])
            ->assertCreated()->assertJsonPath('data.status', 'new')->assertJsonPath('data.contact.name', 'Ion')->json('data.id');
        $this->patchJson($this->url($a, "/leads/{$id}"), ['status' => 'won'])->assertOk()->assertJsonPath('data.status', 'won');
        $this->getJson($this->url($a, '/leads?status=won'))->assertJsonCount(1, 'data');
        $this->patchJson($this->url($a, "/leads/{$id}"), ['status' => 'inexistent'])->assertStatus(422);
        $this->deleteJson($this->url($a, "/leads/{$id}"))->assertNoContent();
    }

    public function test_sites_secret_is_returned_only_on_create_and_rotate(): void
    {
        $org = $this->makeOrganization('Firma A', null, 'pro');
        $this->actingAs($this->member($org, OrgRole::Admin));

        $res = $this->postJson($this->url($org, '/sites'), ['domain' => 'https://www.firma-a.ro', 'platform' => 'woocommerce', 'name' => 'Magazin'])
            ->assertCreated()->assertJsonPath('data.domain', 'firma-a.ro')->assertJsonPath('data.platform', 'woocommerce');
        $this->assertStringStartsWith('sk_', $res->json('secret'));
        $id = $res->json('data.id');

        $show = $this->getJson($this->url($org, "/sites/{$id}"))->assertOk();
        $this->assertStringNotContainsString($res->json('secret'), $show->getContent());
        $this->assertStringStartsWith('pk_', $show->json('data.public_key'));
        $this->patchJson($this->url($org, "/sites/{$id}"), ['domain' => 'alt.ro'])->assertStatus(422);
        $this->patchJson($this->url($org, "/sites/{$id}"), ['name' => 'Magazin online'])->assertOk()->assertJsonPath('data.name', 'Magazin online');
        $rotated = $this->postJson($this->url($org, "/sites/{$id}/rotate-keys"))->assertOk();
        $this->assertNotSame($show->json('data.public_key'), $rotated->json('data.public_key'));
    }

    public function test_agents_crud_rejects_secrets(): void
    {
        $org = $this->makeOrganization('Firma A', null, 'pro');
        $this->actingAs($this->member($org, OrgRole::Admin));

        $this->postJson($this->url($org, '/agents'), ['name' => 'X', 'model_configuration' => ['api_key' => 'sk-ant-123']])
            ->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $id = $this->postJson($this->url($org, '/agents'), ['name' => 'Asistent', 'system_configuration' => ['tone' => 'friendly']])
            ->assertCreated()->assertJsonPath('data.system_configuration.tone', 'friendly')
            ->assertJsonPath('data.system_configuration.lead_rules.require_consent', true)->json('data.id');
        $this->patchJson($this->url($org, "/agents/{$id}"), ['status' => 'active'])->assertOk()->assertJsonPath('data.status', 'active');
        $this->deleteJson($this->url($org, "/agents/{$id}"))->assertNoContent();
        $this->assertTrue(AuditLog::withoutTenancy()->where('action', 'agent.deleted')->exists());
    }

    public function test_users_invitation_and_role_rules(): void
    {
        Notification::fake();
        $org = $this->makeOrganization('Firma A', null, 'pro');
        $admin = $this->member($org, OrgRole::Admin);
        $this->actingAs($admin);

        $this->postJson($this->url($org, '/users'), ['email' => 'x@example.test', 'name' => 'X', 'role' => 'org_owner'])->assertStatus(422);
        $mid = $this->postJson($this->url($org, '/users'), ['email' => 'op@example.test', 'name' => 'Operator', 'role' => 'agent'])
            ->assertCreated()->assertJsonPath('data.role', 'agent')->json('data.id');
        $this->patchJson($this->url($org, "/users/{$mid}"), ['role' => 'viewer'])->assertOk()->assertJsonPath('data.role', 'viewer');
        $this->getJson($this->url($org, '/users'))->assertOk()->assertJsonCount(2, 'data');
        $this->deleteJson($this->url($org, "/users/{$mid}"))->assertNoContent();
    }

    public function test_permissions_are_enforced_per_role(): void
    {
        $org = $this->makeOrganization('Firma A');
        $contact = $this->tenant()->runAs($org, fn () => app(ContactService::class)->create(['first_name' => 'Ion'], ContactSource::Manual));
        $viewer = $this->member($org, OrgRole::Viewer);
        $agent = $this->member($org, OrgRole::Agent);

        $this->actingAs($viewer)->getJson($this->url($org, '/contacts'))->assertOk();
        $this->postJson($this->url($org, '/contacts'), ['first_name' => 'X'])->assertForbidden()->assertJsonPath('error.code', 'forbidden');
        $this->getJson($this->url($org, '/users'))->assertForbidden();

        $this->actingAs($agent)->postJson($this->url($org, '/contacts'), ['first_name' => 'Y'])->assertCreated();
        $this->deleteJson($this->url($org, "/contacts/{$contact->id}"))->assertForbidden(); // ștergerea = doar proprietar
        $this->postJson($this->url($org, '/agents'), ['name' => 'Z'])->assertForbidden();
    }

    /** Criteriul de acceptanță: un utilizator din firma A nu poate atinge nicio resursă a firmei B. */
    public function test_cross_tenant_access_is_denied_everywhere(): void
    {
        $a = $this->makeOrganization('Firma A', null, 'pro');
        $b = $this->makeOrganization('Firma B', null, 'pro');
        $ownerA = $this->member($a, OrgRole::Owner);
        $memberB = $this->member($b, OrgRole::Owner);
        [$siteB] = $this->makeSite($b, 'firma-b.ro');
        [$contactB, $leadB, $agentB] = $this->tenant()->runAs($b, function () {
            $c = app(ContactService::class)->create(['first_name' => 'Secret', 'email' => 'secret@b.test'], ContactSource::Manual);

            return [$c, app(LeadService::class)->create($c, []), app(AgentService::class)->create(['name' => 'Agent B'])];
        });
        $memberBId = $this->tenant()->runAs($b, fn () => Membership::query()->where('user_id', $memberB->id)->value('id'));
        $this->actingAs($ownerA);

        // 1) ID-uri din B prin URL-ul firmei A → 404 (resursa „nu există” pentru A)
        foreach (["/contacts/{$contactB->id}", "/leads/{$leadB->id}", "/sites/{$siteB->id}", "/agents/{$agentB->id}", "/contacts/{$contactB->id}/consents"] as $path) {
            $this->getJson($this->url($a, $path))->assertNotFound();
        }
        $this->patchJson($this->url($a, "/contacts/{$contactB->id}"), ['first_name' => 'X'])->assertNotFound();
        $this->deleteJson($this->url($a, "/contacts/{$contactB->id}"))->assertNotFound();
        $this->patchJson($this->url($a, "/leads/{$leadB->id}"), ['status' => 'won'])->assertNotFound();
        $this->postJson($this->url($a, "/sites/{$siteB->id}/rotate-keys"))->assertNotFound();
        $this->deleteJson($this->url($a, "/users/{$memberBId}"))->assertNotFound();
        $this->postJson($this->url($a, "/contacts/{$contactB->id}/consents"), ['channel' => 'email', 'purpose' => 'marketing', 'status' => 'revoked', 'source' => 'x'])->assertNotFound();

        // 2) direct prin URL-ul firmei B → 404 (nu confirmăm nici existența firmei)
        foreach (['/contacts', "/contacts/{$contactB->id}", '/leads', '/sites', '/agents'] as $path) {
            $this->getJson($this->url($b, $path))->assertNotFound();
        }

        // 3) listele firmei A nu conțin nimic din B
        $this->assertStringNotContainsString('secret@b.test', $this->getJson($this->url($a, '/contacts'))->getContent());
        $this->assertSame('Secret', $contactB->fresh()->first_name);
    }

    public function test_admin_organizations_api_is_for_vitim_staff_only(): void
    {
        Notification::fake();
        $client = User::factory()->create();
        $this->makeOrganization('Firma A', $client);
        $this->actingAs($client)->getJson('/api/v1/admin/organizations')->assertNotFound();

        $staff = $this->staff(PlatformRole::VitimAdmin);
        $this->actingAs($staff);
        $id = $this->postJson('/api/v1/admin/organizations', ['name' => 'Demo Auto SRL', 'plan' => 'pro', 'country' => 'RO', 'vat_id' => 'RO123', 'owner_email' => 'owner@example.test'])
            ->assertCreated()->assertJsonPath('data.slug', 'demo-auto-srl')->assertJsonPath('data.subscription.plan', 'pro')->json('data.id');
        $this->getJson('/api/v1/admin/organizations?q=demo')->assertOk()->assertJsonPath('meta.total', 1);
        $this->patchJson("/api/v1/admin/organizations/{$id}", ['status' => 'suspended', 'plan' => 'start'])->assertStatus(422);
        $this->patchJson("/api/v1/admin/organizations/{$id}", ['status' => 'suspended'])->assertOk()->assertJsonPath('data.status', 'suspended');
        $this->assertSame(OrgRole::Owner, User::where('email', 'owner@example.test')->first()->roleIn(Organization::find($id)));
    }

    public function test_staff_without_second_factor_in_session_is_blocked(): void
    {
        $staff = $this->staff();
        $this->flushSession();
        $this->actingAs($staff)->getJson('/api/v1/admin/organizations')->assertForbidden();
    }
}
