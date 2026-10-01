<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\BackupMonitor;
use App\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Verifică la oră vechimea backup-urilor (și pentru site-urile care nu mai trimit nimic). */
#[Signature('vitim:backups-check')]
#[Description('Verifică backup-urile site-urilor și deschide problemele de backup')]
final class CheckBackups extends Command
{
    public function handle(TenantContext $context, BackupMonitor $monitor): int
    {
        // cod de platformă: site-urile cu backup automat din toate firmele, fiecare evaluat în contextul firmei lui
        Site::withoutTenancy()->with('organization')->whereNotNull('health')->where('status', 'active')
            ->each(function (Site $site) use ($context, $monitor): void {
                if (! empty($site->health['backup_schedule']) && $site->organization->isActive()) {
                    $context->runAs($site->organization, fn () => $monitor->evaluate($site));
                }
            });

        return self::SUCCESS;
    }
}
