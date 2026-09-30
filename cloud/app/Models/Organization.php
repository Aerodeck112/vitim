<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\OrganizationScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Tenantul: firma client. Relațiile spre date de client trec prin scope-ul de organizație,
 * deci se citesc doar în contextul acestei organizații (TenantContext::runAs).
 */
#[Fillable(['name', 'slug', 'status', 'country', 'timezone', 'default_language', 'company_name', 'vat_id', 'data_retention_days', 'billing_details', 'branding'])]
class Organization extends Model
{
    protected function casts(): array
    {
        return ['billing_details' => 'array', 'branding' => 'array'];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** @return HasMany<Site, $this> */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /** @return HasMany<Agent, $this> */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Abonamentul citit din cod de platformă (liste de clienți), fără organizație curentă.
     *
     * @return HasOne<Subscription, $this>
     */
    public function subscriptionWithoutTenancy(): HasOne
    {
        return $this->hasOne(Subscription::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return HasOne<Subscription, $this> */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }
}
