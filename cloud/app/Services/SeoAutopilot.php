<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Site;
use App\Models\SiteCommand;
use App\Models\SiteIssue;
use Throwable;

/**
 * Pilotul automat SEO: o dată pe săptămână, după audit, cere pluginului VITIM remedierile sigure pentru problemele
 * deschise (descrieri, titluri, sitemap, limbă, viewport, text alternativ, date structurate), apoi reface auditul.
 * Remedierile care pot schimba comportamentul întregului site (HTTPS, www, robots.txt, indexare, antete, compresie)
 * rămân la decizia echipei, pe buton.
 */
final class SeoAutopilot
{
    public const SAFE = ['meta', 'title', 'sitemap', 'lang', 'viewport', 'alt', 'schema'];

    public const EVERY_DAYS = 7;

    public function __construct(private readonly SiteCommandService $commands, private readonly SiteAuditService $audits) {}

    /** Site-ul poate primi remedieri automate: WordPress cu pluginul 1.4.0+, conectat, cu remedierile de la distanță permise. */
    public static function supported(Site $site): bool
    {
        return in_array($site->platform?->value, ['wordpress', 'woocommerce'], true)
            && $site->connector_version !== null && version_compare($site->connector_version, Remediation::SEO_PLUGIN_VERSION, '>=')
            && ! empty($site->health['command_url']) && ($site->health['remote_fixes'] ?? true) !== false;
    }

    public function due(Site $site): bool
    {
        return $site->seo_autopilot && $site->isActive() && $site->isOnline() && self::supported($site)
            && ($site->seo_autopilot_at === null || $site->seo_autopilot_at->lt(now()->subDays(self::EVERY_DAYS)));
    }

    /** @return list<string> remedierile sigure pentru problemele deschise acum */
    public function pending(Site $site): array
    {
        $fix = Remediation::combine(SiteIssue::query()->where('site_id', $site->id)->where('status', 'open')->pluck('fix'));

        return $fix ? array_values(array_intersect(explode('.', substr($fix, 8)), self::SAFE)) : [];
    }

    /** Rulează pilotul: întoarce comanda trimisă pluginului sau null dacă nu era nimic de reparat. */
    public function run(Site $site): ?SiteCommand
    {
        $targets = $this->pending($site);
        // o rulare pe săptămână, chiar dacă nu a fost nimic de reparat (sau remedierea a eșuat): fără reîncercări în buclă
        $site->forceFill(['seo_autopilot_at' => now()])->save();
        if ($targets === []) {
            return null;
        }
        try {
            $command = $this->commands->run($site, 'seo_fix:'.implode('.', $targets), null);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
        if ($command->status === 'done') {
            // verificarea: problemele rezolvate trec la „Rezolvate recent”
            $this->audits->run($site->refresh());
        }

        return $command;
    }
}
