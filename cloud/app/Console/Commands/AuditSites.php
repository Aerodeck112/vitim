<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\SiteAuditService;
use App\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Auditul zilnic al site-urilor (SEO, securitate, legal). Rulează din cron în tranșe mici (câteva site-uri pe minut),
 * ca să nu depășească limitele hostingului; fiecare site e auditat cel mult o dată pe zi.
 */
#[Signature('vitim:audit {--site= : ID-ul unui site anume} {--batch=3 : Câte site-uri pe rulare}')]
#[Description('Auditul SEO / securitate / legal al site-urilor clienților')]
final class AuditSites extends Command
{
    public function handle(TenantContext $context, SiteAuditService $audits): int
    {
        // cod de platformă: alege site-urile tuturor firmelor; fiecare audit rulează apoi în contextul firmei lui
        $sites = Site::withoutTenancy()->with('organization')->where('status', 'active')
            ->when($this->option('site'), fn ($q, $id) => $q->whereKey($id), fn ($q) => $q->where(fn ($q) => $q->whereNull('last_audit_at')->orWhere('last_audit_at', '<', now()->subDay())))
            ->orderByRaw('last_audit_at IS NOT NULL')->orderBy('last_audit_at')->limit((int) $this->option('batch'))->get();

        foreach ($sites as $site) {
            if (! $site->organization->isActive()) {
                continue;
            }
            try {
                $count = $context->runAs($site->organization, fn () => $audits->run($site));
                $this->line("{$site->domain}: {$count} constatări");
            } catch (Throwable $e) {
                report($e);
                $this->error("{$site->domain}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
