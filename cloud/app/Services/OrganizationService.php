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

    /** Firmă nouă, cu abonament de probă și (opțional) proprietar. */
    public function create(string $name, string $plan = 'start', ?User $owner = null): Organization
    {
        $limits = config("plans.plans.{$plan}");
        if (! is_array($limits)) {
            throw new InvalidArgumentException("Plan necunoscut: {$plan}");
        }

        return DB::transaction(function () use ($name, $plan, $limits, $owner): Organization {
            $organization = Organization::create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
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
