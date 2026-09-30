<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Tenantul: firma client. Relațiile spre date de client trec prin scope-ul de organizație,
 * deci se citesc doar în contextul acestei organizații (TenantContext::runAs).
 */
#[Fillable(['name', 'slug', 'status', 'locale', 'timezone', 'data_retention_days'])]
class Organization extends Model
{
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** @return HasMany<Site, $this> */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return HasOne<Subscription, $this> */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }
}
