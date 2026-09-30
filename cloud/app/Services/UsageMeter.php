<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UsageRecord;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/** Contoare zilnice de consum per organizație (conversații, mesaje, tokeni AI, contacte, lead-uri...). */
final class UsageMeter
{
    public function __construct(private readonly TenantContext $context) {}

    public function increment(string $metric, int $quantity = 1): void
    {
        if ($quantity <= 0) {
            return;
        }
        $now = now();
        // atomic: un singur rând pe (organizație, metrică, zi); organization_id vine doar din context
        DB::table('usage_records')->upsert(
            [['organization_id' => $this->context->id(), 'metric' => $metric, 'period_date' => $now->toDateString(),
                'quantity' => $quantity, 'created_at' => $now, 'updated_at' => $now]],
            ['organization_id', 'metric', 'period_date'],
            ['quantity' => DB::raw('quantity + '.$quantity), 'updated_at' => $now],
        );
    }

    /** Totalul lunii curente pentru organizația curentă. */
    public function thisMonth(string $metric): int
    {
        return (int) UsageRecord::query()->where('metric', $metric)
            ->where('period_date', '>=', now()->startOfMonth()->toDateString())->sum('quantity');
    }
}
