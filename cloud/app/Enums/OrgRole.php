<?php

declare(strict_types=1);

namespace App\Enums;

/** Rolul unui utilizator în organizația clientului. Singura sursă a matricei rol → permisiuni. */
enum OrgRole: string
{
    case Owner = 'org_owner';
    case Admin = 'org_admin';
    case Agent = 'agent';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Proprietar',
            self::Admin => 'Administrator',
            self::Agent => 'Operator',
            self::Viewer => 'Doar vizualizare',
        };
    }

    /** @return list<Permission> */
    public function permissions(): array
    {
        $viewer = [Permission::ViewReports, Permission::ViewContacts, Permission::ViewLeads, Permission::ViewConversations];
        $agent = [...$viewer, Permission::ManageContacts, Permission::ManageConsent, Permission::ManageLeads, Permission::HandleConversations];
        $admin = [...$agent, Permission::ManageAgents, Permission::ManageKnowledge, Permission::ManageSites,
            Permission::ManageIntegrations, Permission::ManageCampaigns, Permission::ManageUsers, Permission::ViewAudit, Permission::ExportData];

        return match ($this) {
            self::Viewer => $viewer,
            self::Agent => $agent,
            self::Admin => $admin,
            self::Owner => Permission::cases(),
        };
    }

    public function allows(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /** Un utilizator poate atribui doar roluri cel mult egale cu al lui (un admin nu poate crea proprietari). */
    public function canAssign(self $role): bool
    {
        return match ($this) {
            self::Owner => true,
            self::Admin => $role !== self::Owner,
            default => false,
        };
    }
}
