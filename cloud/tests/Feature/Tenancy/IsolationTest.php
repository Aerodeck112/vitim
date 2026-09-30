<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\User;
use App\Tenancy\TenancyViolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Garanția de bază a platformei: o firmă nu poate citi sau scrie datele alteia.
 */
final class IsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_queries_without_organization_fail_closed(): void
    {
        $a = $this->makeOrganization('Firma A');
        $this->makeSite($a, 'firma-a.ro');

        $this->expectException(TenancyViolation::class);
        Site::query()->get();
    }

    public function test_reads_only_see_current_organization(): void
    {
        $a = $this->makeOrganization('Firma A');
        $b = $this->makeOrganization('Firma B');
        [$siteA] = $this->makeSite($a, 'firma-a.ro');
        [$siteB] = $this->makeSite($b, 'firma-b.ro');

        $this->tenant()->runAs($a, function () use ($siteA, $siteB): void {
            $this->assertSame([$siteA->id], Site::query()->pluck('id')->all());
            $this->assertNull(Site::query()->find($siteB->id));
            $this->assertSame(1, Subscription::query()->count());
            $this->assertSame(0, $siteA->organization->sites()->where('id', $siteB->id)->count());
        });
    }

    public function test_relation_of_other_organization_is_still_filtered_by_current_context(): void
    {
        $a = $this->makeOrganization('Firma A');
        $b = $this->makeOrganization('Firma B');
        $this->makeSite($b, 'firma-b.ro');

        // chiar dacă cineva ajunge la obiectul firmei B, în contextul A nu vede nimic din B
        $this->tenant()->runAs($a, fn () => $this->assertCount(0, $b->sites()->get()));
    }

    public function test_create_takes_organization_from_context(): void
    {
        $a = $this->makeOrganization('Firma A');
        [$site] = $this->makeSite($a, 'firma-a.ro');

        $this->assertSame($a->id, $site->organization_id);
    }

    public function test_writing_into_another_organization_is_rejected(): void
    {
        $a = $this->makeOrganization('Firma A');
        $b = $this->makeOrganization('Firma B');

        // prin atribuire în masă organization_id e ignorat (nu e fillable) → rândul ajunge în firma curentă
        $site = $this->tenant()->runAs($a, fn () => Site::create(['organization_id' => $b->id, 'domain' => 'masa.ro', 'allowed_origins' => []]));
        $this->assertSame($a->id, $site->organization_id);

        // setat explicit (forceFill), scrierea în altă firmă e oprită de model
        $this->expectException(TenancyViolation::class);
        $this->tenant()->runAs($a, fn () => (new Site)->forceFill([
            'organization_id' => $b->id,
            'domain' => 'atac.ro',
            'allowed_origins' => [],
        ])->save());
    }

    public function test_create_without_context_is_rejected(): void
    {
        $this->expectException(TenancyViolation::class);
        Site::create(['domain' => 'fara-context.ro', 'allowed_origins' => []]);
    }

    public function test_organization_id_cannot_be_changed(): void
    {
        $a = $this->makeOrganization('Firma A');
        $b = $this->makeOrganization('Firma B');
        [$site] = $this->makeSite($a, 'firma-a.ro');

        $this->tenant()->runAs($a, fn () => $site->update(['organization_id' => $b->id])); // ignorat (nu e fillable)
        $this->assertSame($a->id, Site::withoutTenancy()->find($site->id)->organization_id);

        $this->expectException(TenancyViolation::class);
        $this->tenant()->runAs($a, fn () => $site->forceFill(['organization_id' => $b->id])->save());
    }

    public function test_run_as_restores_previous_context(): void
    {
        $a = $this->makeOrganization('Firma A');
        $b = $this->makeOrganization('Firma B');

        $this->tenant()->runAs($a, function () use ($a, $b): void {
            $this->tenant()->runAs($b, fn () => $this->assertSame($b->id, $this->tenant()->id()));
            $this->assertSame($a->id, $this->tenant()->id());
        });
        $this->assertFalse($this->tenant()->has());
    }

    public function test_platform_audit_rows_are_invisible_to_tenants(): void
    {
        $a = $this->makeOrganization('Firma A'); // scrie „organization.created” în contextul A
        AuditLog::create(['actor_type' => 'platform', 'action' => 'platform.test']); // fără organizație

        $this->tenant()->runAs($a, function (): void {
            $this->assertSame(['organization.created'], AuditLog::query()->pluck('action')->all());
        });
        $this->assertSame(1, AuditLog::withoutTenancy()->whereNull('organization_id')->count());
    }

    public function test_memberships_are_scoped(): void
    {
        $owner = User::factory()->create();
        $a = $this->makeOrganization('Firma A', $owner);
        $b = $this->makeOrganization('Firma B', User::factory()->create());

        $this->tenant()->runAs($a, fn () => $this->assertSame([$owner->id], Membership::query()->pluck('user_id')->all()));
        $this->assertNull($owner->roleIn($b));
    }
}
