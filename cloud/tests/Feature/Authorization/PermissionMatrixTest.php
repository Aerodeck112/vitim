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
        $expected = [
            'viewer' => [Permission::ViewReports],
            'operator' => [Permission::ViewReports, Permission::ViewConversations, Permission::HandleConversations, Permission::ManageLeads],
            'manager' => [Permission::ViewReports, Permission::ViewConversations, Permission::HandleConversations, Permission::ManageLeads,
                Permission::ManageAgent, Permission::ManageKnowledge, Permission::ManageSites, Permission::ExportData],
            'owner' => Permission::cases(),
        ];
        foreach ($expected as $role => $allowed) {
            foreach (Permission::cases() as $permission) {
                $this->assertSame(
                    in_array($permission, $allowed, true),
                    OrgRole::from($role)->allows($permission),
                    "{$role} / {$permission->value}"
                );
            }
        }
    }

    public function test_gates_use_role_in_current_organization_only(): void
    {
        $user = User::factory()->create();
        $a = $this->makeOrganization('Firma A', $user); // owner în A
        $b = $this->makeOrganization('Firma B');
        $this->tenant()->runAs($b, fn () => Membership::create(['user_id' => $user->id, 'role' => OrgRole::Viewer]));

        $this->tenant()->runAs($a, fn () => $this->assertTrue(Gate::forUser($user)->allows(Permission::ManageUsers->value)));
        $this->tenant()->runAs($b, function () use ($user): void {
            $this->assertTrue(Gate::forUser($user)->allows(Permission::ViewReports->value));
            $this->assertFalse(Gate::forUser($user)->allows(Permission::ManageUsers->value));
        });
        // fără organizație curentă, un utilizator obișnuit nu poate nimic
        $this->assertFalse(Gate::forUser($user)->allows(Permission::ViewReports->value));
    }

    public function test_platform_roles(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['platform_role' => PlatformRole::Admin])->save();
        $support = User::factory()->create();
        $support->forceFill(['platform_role' => PlatformRole::Support])->save();
        $org = $this->makeOrganization('Firma A');

        $this->tenant()->runAs($org, function () use ($admin, $support): void {
            $this->assertTrue(Gate::forUser($admin)->allows(Permission::DeleteData->value));
            $this->assertTrue(Gate::forUser($support)->allows(Permission::ViewConversations->value));
            $this->assertFalse(Gate::forUser($support)->allows(Permission::ExportData->value));
            $this->assertFalse(Gate::forUser($support)->allows(Permission::ManageAgent->value));
        });
    }

    public function test_platform_role_is_not_mass_assignable(): void
    {
        $user = User::create(['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret-parola', 'platform_role' => 'platform_admin']);

        $this->assertNull($user->fresh()->platform_role);
    }
}
