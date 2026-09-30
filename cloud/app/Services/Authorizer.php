<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Permission;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Decide dacă un utilizator poate face o acțiune în organizația curentă.
 * Folosit de Gate-uri (numele permisiunii), deci și de middleware-ul `can:`.
 */
final class Authorizer
{
    public function __construct(private readonly TenantContext $context) {}

    public function allows(User $user, Permission $permission): bool
    {
        if ($user->platform_role !== null && $user->platform_role->allows($permission)) {
            return true;
        }
        if (! $this->context->has()) {
            return false;
        }

        return $user->roleIn($this->context->organization())?->allows($permission) ?? false;
    }
}
