<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrgRole;
use App\Enums\SubscriptionStatus;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class OrganizationService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    public const PROFILE_FIELDS = ['name', 'company_name', 'vat_id', 'country', 'timezone', 'default_language', 'status'];

    /**
     * Firmă nouă, cu abonament de probă și (opțional) proprietar.
     *
     * @param  array<string, mixed>  $profile  câmpuri din PROFILE_FIELDS (țară, fus orar, limbă, date firmă)
     */
    public function create(string $name, string $plan = 'start', ?User $owner = null, array $profile = []): Organization
    {
        $limits = config("plans.plans.{$plan}");
        if (! is_array($limits)) {
            throw new InvalidArgumentException("Plan necunoscut: {$plan}");
        }

        return DB::transaction(function () use ($name, $plan, $limits, $owner, $profile): Organization {
            $organization = Organization::create(array_intersect_key($profile, array_flip(self::PROFILE_FIELDS)) + [
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'country' => 'RO',
                'timezone' => 'Europe/Bucharest',
                'default_language' => 'ro',
                'status' => 'active',
            ]);

            $this->context->runAs($organization, function () use ($organization, $plan, $limits, $owner): void {
                Subscription::create([
                    'plan' => $plan,
                    'status' => SubscriptionStatus::Trial,
                    'limits' => $limits,
                    'trial_ends_at' => now()->addDays((int) config('plans.trial_days', 14)),
                ]);
                if ($owner) {
                    Membership::create(['user_id' => $owner->getKey(), 'role' => OrgRole::Owner]);
                }
                $this->audit->record('organization.created', $organization, ['plan' => $plan]);
            });

            return $organization;
        });
    }

    /** @param array<string, mixed> $profile */
    public function update(Organization $organization, array $profile): Organization
    {
        $organization->fill(array_intersect_key($profile, array_flip(self::PROFILE_FIELDS)));
        $changed = array_keys($organization->getDirty());
        $organization->save();
        if ($changed) {
            $this->context->runAs($organization, fn () => $this->audit->record('organization.updated', $organization, ['fields' => implode(',', $changed)]));
        }

        return $organization;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'firma';
        $slug = $base;
        for ($i = 2; Organization::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
