<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\Site;
use App\Monitoring\FleetMonitor;
use App\Monitoring\MonitorAgent;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Agentul de monitorizare (doar echipa VITIM): starea tuturor clienților, setările rezumatului și trimiterea la cerere. */
final class MonitorController extends Controller
{
    public function __construct(private readonly MonitorAgent $agent, private readonly AuditLogger $audit) {}

    public function show(FleetMonitor $monitor): View
    {
        $state = (array) PlatformSetting::get('monitor.state', []);
        $report = $monitor->report((array) ($state['scores'] ?? []));

        return view('admin.monitor', [
            'report' => $report,
            'groups' => collect($report['items'])->groupBy('org'),
            'settings' => FleetMonitor::settings(),
            'recipients' => $this->agent->recipients(),
            'lastDigest' => $state['digest_on'] ?? null,
            // cod de platformă: doar numărul de site-uri WordPress cu actualizări automate
            'wpSites' => Site::withoutTenancy()->whereIn('platform', ['wordpress', 'woocommerce'])->where('status', 'active')->count(),
            'wpAuto' => Site::withoutTenancy()->whereIn('platform', ['wordpress', 'woocommerce'])->where('status', 'active')->where('update_autopilot', true)->count(),
        ]);
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate(['hour' => ['required', 'integer', 'min:0', 'max:23'], 'extra' => ['nullable', 'string', 'max:1000']]);
        $extra = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,;]+/', strtolower((string) ($data['extra'] ?? ''))) ?: []))));
        validator(['extra' => $extra], ['extra' => ['array', 'max:10'], 'extra.*' => ['email:rfc']], ['extra.*.email' => 'Una dintre adrese nu e validă.'])->validate();
        PlatformSetting::put('monitor', ['enabled' => $request->boolean('enabled'), 'hour' => (int) $data['hour'],
            'urgent' => $request->boolean('urgent'), 'quiet' => ! $request->boolean('always'), 'extra' => $extra]);
        $this->audit->record('monitor.settings', null, ['enabled' => $request->boolean('enabled'), 'hour' => (int) $data['hour'], 'extra' => count($extra)]);

        return redirect()->route('admin.monitor')->with('ok', 'Setările agentului de monitorizare au fost salvate.');
    }

    public function send(): RedirectResponse
    {
        $this->agent->digest(true);
        $this->audit->record('monitor.sent', null);

        return redirect()->route('admin.monitor')->with('ok', 'Rezumatul a fost trimis către '.count($this->agent->recipients()).' adrese.');
    }

    /** Pornește sau oprește actualizările automate pe toate site-urile WordPress conectate (doar super admin). */
    public function updates(Request $request): RedirectResponse
    {
        abort_unless($request->user()->platform_role === PlatformRole::SuperAdmin, 403);
        $on = $request->boolean('on');
        // cod de platformă: comutatorul se aplică site-urilor WordPress ale tuturor firmelor
        $count = Site::withoutTenancy()->whereIn('platform', ['wordpress', 'woocommerce'])->where('update_autopilot', ! $on)->update(['update_autopilot' => $on]);
        $this->audit->record('monitor.update_autopilot', null, ['on' => $on, 'sites' => $count]);

        return redirect()->route('admin.monitor')->with('ok', $on ? "Actualizările automate au fost pornite pe {$count} site-uri." : "Actualizările automate au fost oprite pe {$count} site-uri.");
    }
}
