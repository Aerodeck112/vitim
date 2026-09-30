<?php

declare(strict_types=1);

namespace Tests\Feature\Organizations;

use App\Enums\PlatformRole;
use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\Site;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class OrganizationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // rută de probă în spatele middleware-ului portalului
        Route::middleware(['web', 'auth', 'org'])->get('/app/{organization}/probe', fn () => [
            'org' => app(TenantContext::class)->id(),
            'sites' => Site::query()->pluck('domain'),
        ]);
        if (! Route::has('login')) {
            Route::get('/login', fn () => 'login')->name('login');
            Route::getRoutes()->refreshNameLookups();
        }
    }

    public function test_new_organization_has_trial_subscription_and_owner(): void
    {
        $owner = User::factory()->create();
        $org = $this->makeOrganization('Auto Demo SRL', $owner);

        $this->assertSame('auto-demo-srl', $org->slug);
        $this->tenant()->runAs($org, function () use ($org): void {
            $this->assertSame(SubscriptionStatus::Trial, $org->subscription->status);
            $this->assertTrue($org->subscription->isServiceable());
            $this->assertSame(1, $org->subscription->limit('sites'));
        });
        $this->assertSame('org_owner', $owner->roleIn($org)?->value);
        $this->assertSame('auto-demo-srl-2', $this->makeOrganization('Auto Demo SRL')->slug);
    }

    public function test_expired_trial_is_not_serviceable(): void
    {
        $org = $this->makeOrganization('Firma A');
        $this->tenant()->runAs($org, function () use ($org): void {
            $org->subscription->update(['trial_ends_at' => now()->subDay()]);
            $this->assertFalse($org->subscription->fresh()->isServiceable());
        });
    }

    public function test_member_sees_only_own_organization_data(): void
    {
        $user = User::factory()->create();
        $a = $this->makeOrganization('Firma A', $user);
        $b = $this->makeOrganization('Firma B');
        $this->makeSite($a, 'firma-a.ro');
        $this->makeSite($b, 'firma-b.ro');

        $this->actingAs($user)->get("/app/{$a->slug}/probe")
            ->assertOk()->assertJson(['org' => $a->id, 'sites' => ['firma-a.ro']]);
        $this->actingAs($user)->get("/app/{$b->slug}/probe")->assertNotFound();
        $this->actingAs($user)->get('/app/nu-exista/probe')->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $org = $this->makeOrganization('Firma A');

        $this->get("/app/{$org->slug}/probe")->assertRedirect('/login');
    }

    public function test_platform_staff_access_is_audited_once_per_session(): void
    {
        $staff = User::factory()->create();
        $staff->forceFill(['platform_role' => PlatformRole::VitimAdmin])->save();
        $org = $this->makeOrganization('Firma A');

        $this->actingAs($staff)->get("/app/{$org->slug}/probe")->assertOk();
        $this->actingAs($staff)->get("/app/{$org->slug}/probe")->assertOk();

        $this->assertSame(1, AuditLog::withoutTenancy()->where('action', 'platform.accessed_organization')->count());
    }
}
