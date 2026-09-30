<?php

declare(strict_types=1);

namespace App\Tenancy;

use RuntimeException;

/**
 * O operație ar fi putut atinge datele altei organizații. Este întotdeauna o eroare de programare:
 * nu se prinde și nu se ascunde.
 */
final class TenancyViolation extends RuntimeException
{
    public static function missingContext(?string $model = null): self
    {
        return new self('Nu există organizație curentă'.($model ? " pentru {$model}" : '').'. Folosește TenantContext::runAs() sau withoutTenancy() în cod de platformă.');
    }

    public static function crossTenantWrite(string $model): self
    {
        return new self("Scriere în altă organizație decât cea curentă ({$model}).");
    }

    public static function immutableOrganization(string $model): self
    {
        return new self("organization_id nu se poate schimba ({$model}).");
    }
}
