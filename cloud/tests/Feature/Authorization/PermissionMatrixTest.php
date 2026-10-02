<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Enums\OrgRole;
use App\Enums\Permission;
use App\Enums\PlatformRole;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_matrix(): void
    {
        $viewer = [Permission::ViewReports, Permission::ViewContacts, Permission::ViewLeads, Permission::ViewConversations];
        $agent = [...$viewer, Permission::ManageContacts, Permission::ManageConsent, Permission::ManageLeads, Permission::HandleConversations];
        $admin = [...$agent, Permission::ManageAgents, Permission::ManageKnowledge, Permission::ManageSites, Permission::ManageIntegrations, Permission::ManageCampaigns,
            Permission::ManageUsers, Permission::ViewAudit, Permission::ExportData];
        $expected = ['viewer' => $viewer, 'agent' => $agent, 'org_admin' => $admin, 'org_owner' => Permission::cases()];

        foreach ($expected as $role => $allowed) {
            foreach (Permission::cases() as $permission) {
                $this->assertSame(in_array($permission, $allowed, true), OrgRole::from($role)->allows($permission), "{$role} / {$permission->value}");
            }
        }
        // doar proprietarul șterge date, gestionează facturarea și profilul firmei
        foreach ([Permission::DeleteData, Permission::ManageBilling, Permission::ManageOrganization] as $p) {
            $this->assertFalse(OrgRole::Admin->allows($p));
        }
    }

    public function test_role_assignment_hierarchy(): void
    {
        $this->assertTrue(OrgRole::Owner->canAssign(OrgRole::Owner));
        $this->assertFalse(OrgRole::Admin->canAssign(OrgRole::Owner));
        $this->assertTrue(OrgRole::Admin->canAssign(OrgRole::Agent));
        $this->assertFalse(OrgRole::Agent->canAssign(OrgRole::Viewer));
    }

    public function test_gates_use_role_in_current_organization_only(): void
    {
        $user = User::factory()->create();
        $a = $this->makeOrganization('Firma A', $user); // proprietar în A
        $b = $this->makeOrganization('Firma B');
        $this->tenant()->runAs($b, fn () => Membership::create(['user_id' => $user->id, 'role' => OrgRole::Viewer]));

        $this->tenant()->runAs($a, fn () => $this->assertTrue(Gate::forUser($user)->allows(Permission::ManageUsers->value)));
        $this->tenant()->runAs($b, function () use ($user): void {
            $this->assertTrue(Gate::forUser($user)->allows(Permission::ViewContacts->value));
            $this->assertFalse(Gate::forUser($user)->allows(Permission::ManageContacts->value));
        });
        $this->assertFalse(Gate::forUser($user)->allows(Permission::ViewReports->value));
    }

    public function test_platform_roles(): void
    {
        $super = User::factory()->create();
        $super->forceFill(['platform_role' => PlatformRole::SuperAdmin])->save();
        $vitim = User::factory()->create();
        $vitim->forceFill(['platform_role' => PlatformRole::VitimAdmin])->save();
        $org = $this->makeOrganization('Firma A');

        $this->tenant()->runAs($org, function () use ($super, $vitim): void {
            $this->assertTrue(Gate::forUser($super)->allows(Permission::DeleteData->value));
            $this->assertTrue(Gate::forUser($vitim)->allows(Permission::ManageAgents->value));
            $this->assertFalse(Gate::forUser($vitim)->allows(Permission::DeleteData->value));
            $this->assertFalse(Gate::forUser($vitim)->allows(Permission::ManageBilling->value));
        });
        $this->assertTrue(PlatformRole::SuperAdmin->canManageStaff());
        $this->assertFalse(PlatformRole::VitimAdmin->canManageStaff());
    }

    public function test_platform_role_is_not_mass_assignable(): void
    {
        $user = User::create(['name' => 'X', 'email' => 'x@example.test', 'password' => 'secret-parola', 'platform_role' => 'super_admin']);

        $this->assertNull($user->fresh()->platform_role);
    }
}
