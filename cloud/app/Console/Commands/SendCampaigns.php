<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\CampaignService;
use App\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/** Trimite tranșa curentă a campaniilor aprobate (din minut în minut, cu limita pe oră a fiecărui cont). */
#[Signature('vitim:campaigns')]
#[Description('Trimiterea campaniilor de email / SMS / WhatsApp în tranșe')]
final class SendCampaigns extends Command
{
    public function handle(TenantContext $context, CampaignService $campaigns): int
    {
        // cod de platformă: campaniile tuturor firmelor; fiecare tranșă rulează în contextul firmei ei
        $due = Campaign::withoutTenancy()->with('organization')->whereIn('status', ['scheduled', 'sending'])
            ->where(fn ($q) => $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))->orderBy('id')->get();

        foreach ($due as $campaign) {
            if (! $campaign->organization->isActive()) {
                continue;
            }
            try {
                $sent = $context->runAs($campaign->organization, fn () => $campaigns->process($campaign));
                $this->line("#{$campaign->id} {$campaign->name}: {$sent} trimise");
            } catch (Throwable $e) {
                report($e);
                $this->error("#{$campaign->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
