<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Site;
use App\Monitoring\FleetMonitor;
use App\Services\UpdateAutopilot;
use App\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/** Actualizările automate ale pluginurilor: un site pe rulare, doar noaptea (1–5, ora României), fiecare o dată pe săptămână. */
#[Signature('vitim:maintain {--site= : ID-ul unui site anume, la orice oră}')]
#[Description('Actualizează automat pluginurile site-urilor (backup înainte, verificare după)')]
final class MaintainSites extends Command
{
    public function handle(TenantContext $context, UpdateAutopilot $autopilot): int
    {
        $forced = $this->option('site');
        $hour = now()->setTimezone(FleetMonitor::TIMEZONE)->hour;
        if (! $forced && ($hour < 1 || $hour >= 5)) {
            return self::SUCCESS;
        }
        // cod de platformă: alege site-urile tuturor firmelor; fiecare rulează apoi în contextul firmei lui
        $sites = Site::withoutTenancy()->with('organization')->where('status', 'active')
            ->when($forced, fn ($q, $id) => $q->whereKey($id), fn ($q) => $q->where('update_autopilot', true)
                ->where(fn ($q) => $q->whereNull('update_autopilot_at')->orWhere('update_autopilot_at', '<', now()->subDays(UpdateAutopilot::EVERY_DAYS))))
            ->orderByRaw('update_autopilot_at IS NOT NULL')->orderBy('update_autopilot_at')->limit(20)->get();

        foreach ($sites as $site) {
            if (! $site->organization->isActive() || (! $forced && ! $autopilot->due($site))) {
                continue;
            }
            try {
                $this->line("{$site->domain}: ".$context->runAs($site->organization, fn () => $autopilot->run($site)));
            } catch (Throwable $e) {
                report($e);
                $this->error("{$site->domain}: {$e->getMessage()}");
            }

            break; // un singur site pe rulare: backup + actualizare pot dura câteva minute
        }

        return self::SUCCESS;
    }
}
