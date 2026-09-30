<?php

declare(strict_types=1);

namespace App\Enums;

/** Rolul unui utilizator în organizația clientului. */
enum OrgRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Operator = 'operator';
    case Viewer = 'viewer';

    /** @return list<Permission> */
    public function permissions(): array
    {
        $viewer = [Permission::ViewReports];
        $operator = [...$viewer, Permission::ViewConversations, Permission::HandleConversations, Permission::ManageLeads];
        $manager = [...$operator, Permission::ManageAgent, Permission::ManageKnowledge, Permission::ManageSites, Permission::ExportData];

        return match ($this) {
            self::Viewer => $viewer,
            self::Operator => $operator,
            self::Manager => $manager,
            self::Owner => Permission::cases(),
        };
    }

    public function allows(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
