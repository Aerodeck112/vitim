<?php

declare(strict_types=1);

namespace App\Enums;

/** Rolurile echipei VITIM, valabile în toate organizațiile. */
enum PlatformRole: string
{
    case Admin = 'platform_admin';
    case Support = 'platform_support';

    public function allows(Permission $permission): bool
    {
        return match ($this) {
            self::Admin => true,
            // suportul vede, dar nu modifică și nu exportă date
            self::Support => in_array($permission, [Permission::ViewReports, Permission::ViewConversations], true),
        };
    }
}
