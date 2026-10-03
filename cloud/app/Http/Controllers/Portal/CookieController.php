<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Models\CookieConsent;
use App\Models\Site;
use App\Services\AuditLogger;
use App\Services\CookieSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Bannerul de cookie-uri al site-urilor firmei: setări, lista cookie-urilor și registrul consimțămintelor. */
final class CookieController extends PortalController
{
    public function show(Request $request): View
    {
        $sites = Site::query()->orderBy('domain')->get();
        $site = $sites->firstWhere('id', (int) $request->query('site')) ?? $sites->first();
        $stats = $site ? CookieConsent::query()->where('site_id', $site->id)->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('action, count(*) as n, sum(case when marketing then 1 else 0 end) as m, sum(case when statistics then 1 else 0 end) as s')->groupBy('action')->get()->keyBy('action') : collect();

        return view('portal.cookies', [
            'organization' => $this->organization(),
            'sites' => $sites, 'site' => $site,
            'settings' => $site ? CookieSettings::for($site) : CookieSettings::DEFAULTS,
            'table' => $site ? CookieSettings::table($site) : [],
            'stats' => $stats,
            'recent' => $site ? CookieConsent::query()->where('site_id', $site->id)->latest('id')->limit(20)->get() : collect(),
            'snippet' => CookieSettings::headSnippet(),
        ]);
    }

    public function update(Request $request, AuditLogger $audit, int $site): RedirectResponse
    {
        $model = Site::query()->findOrFail($site);
        $request->validate(['privacy_url' => ['nullable', 'url', 'max:300'], 'policy_url' => ['nullable', 'url', 'max:300']],
            ['privacy_url.url' => 'Adresa trebuie să înceapă cu https://', 'policy_url.url' => 'Adresa trebuie să înceapă cu https://']);
        $settings = CookieSettings::normalize($request->all(), CookieSettings::for($model));
        $model->forceFill(['cookie_config' => $settings])->save();
        $audit->record('cookie_banner.updated', $model, ['enabled' => $settings['enabled'], 'version' => $settings['version']]);

        return redirect()->route('portal.cookies', ['organization' => $this->organization()->slug, 'site' => $model->id])
            ->with('ok', $settings['enabled'] ? 'Salvat. Bannerul apare pe '.$model->domain.' imediat.'.($request->boolean('reconsent') ? ' Vizitatorii vor fi întrebați din nou.' : '') : 'Salvat. Bannerul VITIM este oprit pe '.$model->domain.'.');
    }

    /** Registrul consimțămintelor (dovada cerută la un control), ca fișier CSV. */
    public function export(int $site): StreamedResponse
    {
        $model = Site::query()->findOrFail($site);

        return response()->streamDownload(function () use ($model): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['data', 'id_consimtamant', 'actiune', 'preferinte', 'statistici', 'marketing', 'versiune_politica', 'pagina', 'browser', 'ip_hash'], ';');
            CookieConsent::query()->where('site_id', $model->id)->orderBy('id')->chunk(1000, function ($rows) use ($out): void {
                foreach ($rows as $r) {
                    fputcsv($out, [$r->created_at->setTimezone('Europe/Bucharest')->format('Y-m-d H:i:s'), $r->consent_id, $r->action,
                        $r->preferences ? 'da' : 'nu', $r->statistics ? 'da' : 'nu', $r->marketing ? 'da' : 'nu', $r->policy_version, $r->page, $r->user_agent, $r->ip_hash], ';');
                }
            });
            fclose($out);
        }, 'consimtaminte-cookie-'.$model->domain.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
