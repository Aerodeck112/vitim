<?php

declare(strict_types=1);

namespace App\Services;

use App\Audit\SafeFetcher;
use App\Models\Site;
use App\Models\SiteIssue;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pilotul automat de actualizări: o dată pe săptămână, noaptea, actualizează pluginurile unui site WordPress.
 * Pașii, în ordine: verifică dacă site-ul se deschide, face backup, actualizează, verifică din nou. Fără backup
 * reușit nu actualizează nimic. Dacă site-ul nu se mai deschide după actualizare, deschide o problemă critică
 * (agentul de monitorizare anunță echipa imediat). Tema și nucleul WordPress rămân pe buton.
 */
final class UpdateAutopilot
{
    public const EVERY_DAYS = 7;

    public const BROKEN = 'update.site_broken';

    /** Mesajele WordPress pentru o eroare fatală de PHP (în engleză și română). */
    private const FATAL = ['There has been a critical error', 'A apărut o eroare critică', 'Fatal error:', 'Parse error:'];

    public function __construct(private readonly SiteCommandService $commands, private readonly SiteScanService $scans) {}

    public function due(Site $site): bool
    {
        return $site->update_autopilot && $site->isActive() && $site->isOnline() && SeoAutopilot::supported($site)
            && ($site->update_autopilot_at === null || $site->update_autopilot_at->lt(now()->subDays(self::EVERY_DAYS)));
    }

    /** Câte pluginuri au actualizări disponibile (raportate de plugin la ultima scanare). */
    public function pending(Site $site): int
    {
        return SiteIssue::query()->where('site_id', $site->id)->where('status', 'open')->where('code', 'like', 'plugin_update:%')->count();
    }

    /** Întoarce rezultatul pe scurt (apare în fișa site-ului). */
    public function run(Site $site): string
    {
        // o rulare pe săptămână, orice s-ar întâmpla: fără reîncercări în buclă pe un site cu probleme
        $site->forceFill(['update_autopilot_at' => now()])->save();
        $result = $this->work($site);
        $site->forceFill(['update_autopilot_result' => Str::limit($result, 480)])->save();

        return $result;
    }

    private function work(Site $site): string
    {
        $pending = $this->pending($site);
        if ($pending === 0) {
            return 'Toate pluginurile erau la zi.';
        }
        if (! $this->opens($site)) {
            return 'Site-ul nu se deschidea corect înainte de actualizare: nu am actualizat nimic.';
        }
        try {
            $backup = $this->commands->run($site, 'backup', null);
            if ($backup->status !== 'done') {
                return 'Backup-ul dinainte a eșuat, deci nu am actualizat nimic: '.$backup->result;
            }
            $update = $this->commands->run($site, 'update_all_plugins', null);
        } catch (Throwable $e) {
            report($e);

            return 'Nu am putut trimite comanda către site: '.Str::limit($e->getMessage(), 200);
        }
        if (! $this->opens($site)) {
            $this->scans->ingest($site, [['code' => self::BROKEN, 'severity' => 'critical',
                'title' => 'Site-ul nu se mai deschide corect după actualizarea automată a pluginurilor',
                'details' => 'Backup făcut înainte de actualizare. Rezultatul actualizării: '.$update->result]], 'updates');

            return 'ATENȚIE: site-ul nu se mai deschide corect după actualizare. Backup-ul dinainte există. '.$update->result;
        }
        $this->scans->ingest($site, [], 'updates');
        try {
            $this->commands->run($site, 'scan', null); // lista de actualizări disponibile se reîmprospătează
        } catch (Throwable $e) {
            report($e);
        }

        return ($update->status === 'done' ? '' : 'Parțial: ').$update->result.' Backup făcut înainte, site verificat după.';
    }

    /** Prima pagină răspunde cu 200 și nu arată o eroare fatală. */
    private function opens(Site $site): bool
    {
        $home = (new SafeFetcher($site->domain, 6))->get('https://'.$site->domain.'/');
        if ($home === null || $home['status'] !== 200) {
            return false;
        }
        foreach (self::FATAL as $marker) {
            if (stripos($home['body'], $marker) !== false) {
                return false;
            }
        }

        return true;
    }
}
