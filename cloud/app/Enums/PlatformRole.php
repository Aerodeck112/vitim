<?php

declare(strict_types=1);

namespace App\Enums;

/** Rolurile echipei VITIM, valabile în toate organizațiile. */
enum PlatformRole: string
{
    /** Tot, inclusiv administrarea echipei VITIM. */
    case SuperAdmin = 'super_admin';
    /** Administrează clienții (firme, site-uri, agenți, utilizatori), fără echipa VITIM și fără ștergeri de date. */
    case VitimAdmin = 'vitim_admin';

    public function label(): string
    {
        return $this === self::SuperAdmin ? 'Super admin' : 'Admin VITIM';
    }

    public function allows(Permission $permission): bool
    {
        return match ($this) {
            self::SuperAdmin => true,
            self::VitimAdmin => ! in_array($permission, [Permission::DeleteData, Permission::ManageBilling], true),
        };
    }

    public function canManageStaff(): bool
    {
        return $this === self::SuperAdmin;
    }
}
