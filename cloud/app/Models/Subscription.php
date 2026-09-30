<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'plan', 'status', 'limits', 'trial_ends_at', 'current_period_start', 'current_period_end'])]
class Subscription extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'limits' => 'array',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
        ];
    }

    public function isServiceable(): bool
    {
        if ($this->status === SubscriptionStatus::Trial && $this->trial_ends_at?->isPast()) {
            return false;
        }

        return $this->status->isServiceable();
    }

    /** Limita planului; null = nelimitat (negociat). */
    public function limit(string $key): ?int
    {
        $value = $this->limits[$key] ?? null;

        return $value === null ? null : (int) $value;
    }
}
