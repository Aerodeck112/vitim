<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ContactEvent;
use App\Models\Organization;
use App\Services\Predictions;
use App\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/** Predicțiile pe clienți (valoare estimată, următoarea comandă, risc de pierdere), recalculate zilnic. */
#[Signature('vitim:predictions')]
#[Description('Recalculează predicțiile pe clienți din comenzile magazinelor')]
final class RefreshPredictions extends Command
{
    public function handle(TenantContext $context, Predictions $predictions): int
    {
        // cod de platformă: doar firmele cu comenzi; fiecare se calculează în contextul ei
        $orgIds = ContactEvent::withoutTenancy()->where('type', 'placed_order')->distinct()->pluck('organization_id');
        foreach (Organization::query()->whereIn('id', $orgIds)->get() as $org) {
            try {
                $n = $context->runAs($org, fn () => $predictions->refresh());
                $this->line("{$org->slug}: {$n} clienți");
            } catch (Throwable $e) {
                report($e);
                $this->error("{$org->slug}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
