<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtrează automat după organizația curentă. Fără organizație curentă, interogarea eșuează
 * (fail closed) în loc să întoarcă datele tuturor clienților.
 */
final class OrganizationScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);
        if (! $context->has()) {
            throw TenancyViolation::missingContext($model::class);
        }
        $builder->where($model->qualifyColumn('organization_id'), $context->id());
    }
}
