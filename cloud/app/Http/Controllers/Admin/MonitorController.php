<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
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
}
