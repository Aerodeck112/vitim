<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Services\IssuedSiteKey;
use App\Services\OrganizationService;
use App\Services\SiteService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tenant(): TenantContext
    {
        return app(TenantContext::class);
    }

    protected function makeOrganization(string $name, ?User $owner = null, string $plan = 'start'): Organization
    {
        return app(OrganizationService::class)->create($name, $plan, $owner);
    }

    /** @return array{0: Site, 1: IssuedSiteKey} */
    protected function makeSite(Organization $organization, string $domain): array
    {
        return $this->tenant()->runAs($organization, fn () => app(SiteService::class)->create($domain));
    }
}
