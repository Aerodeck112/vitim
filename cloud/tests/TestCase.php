<?php

declare(strict_types=1);

namespace Tests;

use App\Enums\PlatformRole;
use App\Http\Middleware\EnsureTwoFactor;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Security\Totp;
use App\Services\IssuedSiteKey;
use App\Services\OrganizationService;
use App\Services\SiteService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Utilizator al echipei VITIM, cu 2FA activ și deja verificat în sesiune. */
    protected function staff(PlatformRole $role = PlatformRole::SuperAdmin): User
    {
        $user = User::factory()->create();
        $user->forceFill(['platform_role' => $role, 'totp_secret' => Totp::secret(), 'totp_confirmed_at' => now()])->save();
        $this->withSession([EnsureTwoFactor::SESSION_KEY => true]);

        return $user;
    }

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
