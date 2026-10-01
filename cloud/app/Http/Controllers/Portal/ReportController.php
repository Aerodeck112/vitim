<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Models\MonthlyReport;
use App\Reports\ReportBuilder;
use Illuminate\View\View;

/** Clientul: rapoartele lunare publicate de VITIM. */
final class ReportController extends PortalController
{
    public function index(): View
    {
        return view('portal.reports.index', [
            'organization' => $this->organization(),
            'reports' => MonthlyReport::query()->whereNotNull('published_at')->orderByDesc('period')->get(),
        ]);
    }

    public function show(ReportBuilder $builder, string $period): View
    {
        $report = MonthlyReport::query()->where('period', $period)->whereNotNull('published_at')->firstOrFail();

        return view('portal.reports.show', ['organization' => $this->organization(), 'r' => $builder->build($period, $report), 'staffPreview' => false]);
    }
}
