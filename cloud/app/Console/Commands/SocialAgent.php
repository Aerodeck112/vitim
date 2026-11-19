<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OrgRole;
use App\Mail\TeamAlert;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Site;
use App\Models\SocialConnection;
use App\Models\SocialPost;
use App\Social\DriveError;
use App\Social\DriveLibrary;
use App\Social\MediaLibrary;
use App\Social\MetaError;
use App\Social\MetaResearch;
use App\Social\SocialPlanner;
use App\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Agentul de social media, la oră: duminica după-amiază scrie planul săptămânii următoare (firmele care au pornit
 * planul automat), iar în fiecare dimineață trimite firmei postarea zilei.
 */
#[Signature('vitim:social')]
#[Description('Scrie planul săptămânal de social media și trimite postarea zilei')]
final class SocialAgent extends Command
{
    public function handle(TenantContext $context, SocialPlanner $planner): int
    {
        $now = now()->setTimezone(SocialPlanner::TIMEZONE);
        // cod de platformă: trece prin toate firmele active; fiecare lucrează apoi în contextul ei
        foreach (Organization::query()->where('status', 'active')->whereNotNull('social')->get() as $organization) {
            $settings = SocialPlanner::settings($organization);
            try {
                $context->runAs($organization, function () use ($organization, $settings, $planner, $now): void {
                    if (! $organization->subscription?->isServiceable()) {
                        return;
                    }
                    if ($settings['auto'] && $now->isSunday() && $now->hour >= 16) {
                        $next = SocialPlanner::monday()->addWeek();
                        $exists = SocialPost::query()->betweenDays($next->toDateString(), $next->copy()->addDays(6)->toDateString())->exists();
                        if (! $exists && $planner->available()) {
                            $this->research();
                            $this->line("{$organization->name}: {$planner->plan($next)} postări pentru săptămâna ".$next->format('d.m'));
                        }
                    }
                    if ($settings['remind'] && $now->hour >= 8 && $now->hour < 12) {
                        $this->remind($organization, $now->toDateString());
                    }
                });
            } catch (Throwable $e) {
                report($e);
                $this->error("{$organization->name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    /** Înainte de plan: citește din nou pagina de Meta și pozele din site. O eroare aici nu oprește planul. */
    private function research(): void
    {
        try {
            if ($connection = SocialConnection::query()->where('network', 'meta')->first()) {
                app(MetaResearch::class)->sync($connection);
            }
        } catch (MetaError) {
            // rămâne notată pe legătură; firma o vede în Materiale
        }
        try {
            if ($drive = SocialConnection::query()->where('network', 'drive')->first()) {
                app(DriveLibrary::class)->sync($drive);
            }
        } catch (DriveError) {
            // rămâne notată pe legătură
        }
        if ($site = Site::query()->where('status', 'active')->orderBy('id')->first()) {
            app(MediaLibrary::class)->collectFromSite($site, 15);
        }
    }

    private function remind(Organization $organization, string $today): void
    {
        $posts = SocialPost::query()->whereDate('day', $today)->whereIn('status', ['draft', 'approved'])->whereNull('reminded_at')->get();
        foreach ($posts as $post) {
            $post->forceFill(['reminded_at' => now()])->save();
            $emails = Membership::query()->with('user')->whereIn('role', [OrgRole::Owner->value, OrgRole::Admin->value])->get()
                ->map(fn (Membership $m) => $m->user?->email)->filter()->unique();
            foreach ($emails as $email) {
                Mail::to($email)->send(new TeamAlert('Postarea de azi: '.Str::limit($post->hook, 80), [
                    $post->status === 'approved' ? 'Postarea este aprobată și gata de publicat.' : 'Postarea este ciornă: citește-o și aprob-o înainte de publicare.',
                    '', $post->fullText(),
                ], route('portal.social.show', [$organization->slug, $post->id])));
            }
        }
    }
}
