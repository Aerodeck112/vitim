<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Monitoring\FleetMonitor;
use App\Office\Office;
use App\Tenancy\TenantContext;
use Illuminate\View\View;

/**
 * Biroul echipei VITIM: aceiași agenți, văzuți peste toți clienții. Cod de platformă: fiecare firmă e citită
 * în contextul ei (runAs), iar pagina arată doar stări și numere.
 */
final class OfficeController extends Controller
{
    public function __invoke(TenantContext $context, Office $office): View
    {
        $agents = [];
        foreach (Organization::query()->where('status', 'active')->orderBy('name')->get() as $organization) {
            foreach ($context->runAs($organization, fn () => $office->desks()) as $desk) {
                $agents[$desk['key']] ??= ['name' => $desk['name'], 'role' => $desk['role'], 'zone' => $desk['zone'], 'counts' => array_fill_keys(array_keys(Office::STATES), 0), 'attention' => []];
                $agents[$desk['key']]['counts'][$desk['state']]++;
                if (in_array($desk['state'], ['problem', 'needs'], true)) {
                    $agents[$desk['key']]['attention'][] = ['org' => $organization->name, 'slug' => $organization->slug, 'state' => $desk['state'], 'line' => $desk['line']];
                }
            }
        }
        foreach ($agents as &$agent) {
            usort($agent['attention'], fn (array $a, array $b) => [$a['state'] === 'problem' ? 0 : 1, $a['org']] <=> [$b['state'] === 'problem' ? 0 : 1, $b['org']]);
            $agent['state'] = match (true) {
                $agent['counts']['problem'] > 0 => 'problem', $agent['counts']['needs'] > 0 => 'needs', $agent['counts']['working'] > 0 => 'working', $agent['counts']['idle'] > 0 => 'idle', default => 'off'
            };
        }

        return view('admin.office', ['agents' => $agents, 'monitor' => (array) PlatformSetting::get('monitor.last', []), 'monitorOn' => FleetMonitor::settings()['enabled']]);
    }
}
