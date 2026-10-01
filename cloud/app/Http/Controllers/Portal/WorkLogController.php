<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Models\Site;
use App\Models\WorkLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Clientul vede lucrările făcute de VITIM pentru el (doar cele marcate vizibile). */
final class WorkLogController extends PortalController
{
    public function index(Request $request): View
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('luna')) ? (string) $request->query('luna') : null;
        $siteId = $request->integer('site') ?: null;

        $query = WorkLog::query()->visible()
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->when($month, fn ($q) => $q->whereBetween('performed_at', [
                Carbon::createFromFormat('Y-m', $month)->startOfMonth(), Carbon::createFromFormat('Y-m', $month)->endOfMonth(),
            ]));

        return view('portal.worklogs', [
            'organization' => $this->organization(),
            'logs' => (clone $query)->with('site')->latest('performed_at')->latest('id')->paginate(50)->withQueryString(),
            'total' => (clone $query)->count(),
            'minutes' => (int) (clone $query)->sum('duration_minutes'),
            'sites' => Site::query()->orderBy('domain')->get(),
            'months' => WorkLog::query()->visible()->pluck('performed_at')->map(fn ($d) => $d->format('Y-m'))->unique()->sortDesc()->values(),
            'month' => $month,
            'siteId' => $siteId,
        ]);
    }
}
