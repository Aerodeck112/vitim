<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Flow;
use App\Models\FlowRun;
use App\Models\Organization;
use App\Services\FlowRunner;
use App\Services\FlowTriggers;
use App\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/** Automatizările: intrările pe segment / dată și pașii scadenți ai rulărilor (din minut în minut). */
#[Signature('vitim:flows {--batch=200}')]
#[Description('Rulează automatizările de marketing')]
final class RunFlows extends Command
{
    public function handle(TenantContext $context, FlowTriggers $triggers, FlowRunner $runner): int
    {
        // cod de platformă: firmele cu fluxuri pornite; fiecare rulează în contextul firmei ei
        $orgIds = Flow::withoutTenancy()->where('status', 'live')->distinct()->pluck('organization_id');
        foreach (Organization::query()->whereIn('id', $orgIds)->get() as $org) {
            if (! $org->isActive()) {
                continue;
            }
            try {
                $context->runAs($org, function () use ($triggers, $runner): void {
                    $triggers->checkSegments();
                    $triggers->checkDates();
                    $due = FlowRun::query()->where('status', 'active')->where('wake_at', '<=', now())->with(['flow', 'contact'])->orderBy('wake_at')->limit((int) $this->option('batch'))->get();
                    foreach ($due as $run) {
                        $runner->advance($run);
                    }
                });
            } catch (Throwable $e) {
                report($e);
                $this->error("{$org->slug}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
