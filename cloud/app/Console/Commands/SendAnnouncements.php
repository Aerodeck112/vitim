<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Models\PlatformSetting;
use App\Services\Announcements;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/** Noutățile VITIM: ciorna din CHANGELOG la o versiune nouă, rezumatul lunar și trimiterea în tranșe (la 5 minute). */
#[Signature('vitim:announcements')]
#[Description('Trimite noutățile VITIM către clienți')]
final class SendAnnouncements extends Command
{
    public function handle(Announcements $announcements): int
    {
        try {
            // ciorna „Ce e nou” doar când versiunea se schimbă; la prima rulare versiunea curentă e doar punctul de pornire
            $version = trim((string) @file_get_contents(base_path('VERSION')));
            $known = PlatformSetting::get('announcements_version');
            if ($known !== null && $known !== $version) {
                $announcements->draftFromChangelog($version, (string) @file_get_contents(base_path('CHANGELOG.md')));
            }
            if ($known !== $version && $version !== '') {
                PlatformSetting::put('announcements_version', $version);
            }
            $announcements->monthlyDigest();
        } catch (Throwable $e) {
            report($e);
        }
        $budget = Announcements::PER_RUN;
        foreach (Announcement::query()->whereIn('status', ['scheduled', 'sending'])->where(fn ($q) => $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))->orderBy('id')->get() as $a) {
            if ($budget <= 0) {
                break;
            }
            try {
                $budget -= $announcements->process($a, $budget);
            } catch (Throwable $e) {
                report($e);
                $this->error("#{$a->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
