<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\ClientService;
use App\Models\MonthlyReport;
use App\Models\Site;
use App\Models\SiteBackup;
use App\Models\SiteCommand;
use App\Models\SiteIssue;
use App\Models\WorkLog;
use Illuminate\Support\Carbon;

/** Datele raportului lunar al firmei curente (cifre pe servicii comparate cu luna trecută, lucrări, site-uri). */
final class ReportBuilder
{
    /** @return array<string, mixed> */
    public function build(string $period, ?MonthlyReport $report): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $previousPeriod = $start->copy()->subMonth()->format('Y-m');
        $previous = MonthlyReport::query()->where('period', $previousPeriod)->first();
        $services = ClientService::query()->where('status', 'active')->pluck('service')->all();
        $logs = WorkLog::query()->visible()->with('site')->whereBetween('performed_at', [$start, $end])->orderBy('performed_at')->get();

        $sections = [];
        foreach (array_keys(ServiceCatalog::SERVICES) as $service) {
            if (! in_array($service, $services, true)) {
                continue;
            }
            $current = $service === 'maintenance' ? $this->maintenance($start, $end) : ($report?->data[$service]['metrics'] ?? []);
            $before = $service === 'maintenance' ? $this->maintenance($start->copy()->subMonth(), $start->copy()->subSecond()) : ($previous?->data[$service]['metrics'] ?? []);
            $definitions = $service === 'maintenance' ? self::MAINTENANCE : ServiceCatalog::metrics($service);
            $rows = [];
            foreach ($definitions as $key => [$label, $unit, $direction]) {
                $value = $current[$key] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $old = $before[$key] ?? null;
                $delta = is_numeric($value) && is_numeric($old) ? (float) $value - (float) $old : null;
                $rows[] = [
                    'label' => $label, 'value' => $value, 'unit' => $unit, 'previous' => $old, 'delta' => $delta,
                    'good' => $delta === null || $delta == 0 || $direction === 'neutral' ? null : (($delta > 0) === ($direction === 'up')),
                ];
            }
            $sections[] = [
                'service' => $service,
                'label' => ServiceCatalog::label($service),
                'rows' => $rows,
                'summary' => $report?->data[$service]['summary'] ?? null,
                'logs' => $logs->filter(fn (WorkLog $l) => in_array($l->category->value, ServiceCatalog::categories($service), true))->values(),
            ];
        }

        return [
            'period' => $period,
            'label' => ucfirst($start->locale('ro')->translatedFormat('F Y')),
            'sections' => $sections,
            'summary' => $report?->summary,
            'logs' => $logs,
            'minutes' => (int) $logs->sum('duration_minutes'),
            'sites' => Site::query()->orderBy('domain')->get(),
        ];
    }

    /** Mentenanța: cifre calculate din activitatea reală pe site-uri. */
    private const MAINTENANCE = [
        'updates' => ['Actualizări aplicate', '', 'up'],
        'backups' => ['Backup-uri verificate', '', 'up'],
        'issues_resolved' => ['Probleme rezolvate', '', 'up'],
        'fixes' => ['Remedieri aplicate din panou', '', 'up'],
        'tasks' => ['Lucrări înregistrate', '', 'up'],
    ];

    /** @return array<string, int> */
    private function maintenance(Carbon $start, Carbon $end): array
    {
        return [
            'updates' => WorkLog::query()->visible()->where('category', 'updates')->whereBetween('performed_at', [$start, $end])->count(),
            'backups' => SiteBackup::query()->where('status', 'ok')->where('verified', true)->whereBetween('started_at', [$start, $end])->count(),
            'issues_resolved' => SiteIssue::query()->where('status', 'resolved')->whereBetween('resolved_at', [$start, $end])->count(),
            'fixes' => SiteCommand::query()->where('status', 'done')->where('action', '!=', 'scan')->whereBetween('created_at', [$start, $end])->count(),
            'tasks' => WorkLog::query()->visible()->whereBetween('performed_at', [$start, $end])->count(),
        ];
    }
}
