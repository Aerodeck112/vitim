<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Obligatoriu pe orice model cu date ale unui client (verificat de ArchitectureTest).
 *
 * - citire: filtrată automat pe organizația curentă;
 * - creare: organization_id vine din context; altă valoare = excepție;
 * - actualizare: organization_id nu se poate schimba.
 *
 * Cod de platformă (admin VITIM, rezolvarea cheilor) folosește explicit withoutTenancy().
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);
            $current = $context->has() ? $context->id() : null;
            $given = $model->getAttribute('organization_id');

            if ($current === null) {
                if ($given === null && static::allowsPlatformRows()) {
                    return; // ex. evenimente de audit ale platformei
                }
                throw TenancyViolation::missingContext($model::class);
            }
            if ($given === null) {
                $model->setAttribute('organization_id', $current);
            } elseif ((int) $given !== $current) {
                throw TenancyViolation::crossTenantWrite($model::class);
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('organization_id')) {
                throw TenancyViolation::immutableOrganization($model::class);
            }
        });
    }

    /** Rânduri fără organizație (doar pentru modelele care o permit explicit). */
    protected static function allowsPlatformRows(): bool
    {
        return false;
    }

    /**
     * Interogare peste toate organizațiile. Doar în cod de platformă, niciodată cu input de la client.
     *
     * @return Builder<static>
     */
    public static function withoutTenancy(): Builder
    {
        return static::query()->withoutGlobalScope(OrganizationScope::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
