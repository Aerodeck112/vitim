<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Knowledge\SiteLearner;
use App\Models\Agent;
use App\Models\Site;
use App\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Agenții învață din site-uri: citirea pornește singură pentru site-urile cu agent (prima dată și apoi săptămânal)
 * sau la cerere din panou, și rulează din cron în tranșe mici, ca să nu încarce hostingul.
 */
#[Signature('vitim:learn {--site= : ID-ul unui site anume} {--batch=3 : Câte site-uri pe rulare}')]
#[Description('Citește paginile site-urilor pentru agenții AI')]
final class LearnSites extends Command
{
    public function handle(TenantContext $context, SiteLearner $learner): int
    {
        // cod de platformă: alege site-urile tuturor firmelor; fiecare citire rulează apoi în contextul firmei lui
        if (! $this->option('site')) {
            $withAgent = Agent::withoutTenancy()->whereNotNull('site_id')->select('site_id');
            $due = Site::withoutTenancy()->where('status', 'active')->where('learn_auto', true)->whereIn('id', $withAgent)
                ->where(fn ($q) => $q->whereNull('learn_status')->orWhere(fn ($q) => $q->whereIn('learn_status', ['done', 'failed'])->where('learned_at', '<', now()->subWeek())))
                ->limit(20)->pluck('id');
            Site::withoutTenancy()->whereIn('id', $due)->update(['learn_status' => 'queued', 'learn_error' => null]);
        }

        $sites = Site::withoutTenancy()->with('organization')->whereIn('learn_status', ['queued', 'running'])
            ->when($this->option('site'), fn ($q, $id) => $q->whereKey($id))
            ->orderByRaw("learn_status = 'running' desc")->orderBy('id')->limit((int) $this->option('batch'))->get();
        foreach ($sites as $site) {
            if (! $site->organization?->isActive()) {
                continue;
            }
            try {
                $count = $context->runAs($site->organization, fn () => $learner->step($site));
                $this->line("{$site->domain}: {$count} pagini citite ({$site->learn_status})");
            } catch (Throwable $e) {
                report($e);
                $site->forceFill(['learn_status' => 'failed', 'learn_error' => 'Citirea s-a oprit din cauza unei erori. Încearcă din nou.'])->save();
                $this->error("{$site->domain}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
