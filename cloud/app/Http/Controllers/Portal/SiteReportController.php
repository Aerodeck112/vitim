<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Models\Site;
use App\Models\SiteIssue;
use App\Models\WorkLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Raportul unui site pentru client: scoruri, ce s-a rezolvat, ce mai e deschis, lucrările VITIM pe site. */
final class SiteReportController extends PortalController
{
    public function show(Request $request, int $site): View
    {
        $model = Site::query()->findOrFail($site);
        $order = "CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END";

        return view('portal.site-report', [
            'organization' => $this->organization(),
            'staff' => $request->user()->isPlatformStaff(),
            'site' => $model,
            'open' => SiteIssue::query()->where('site_id', $model->id)->where('status', 'open')->orderByRaw($order)->get(),
            'resolved' => SiteIssue::query()->where('site_id', $model->id)->where('status', 'resolved')
                ->where('resolved_at', '>=', now()->subDays(90))->latest('resolved_at')->get(),
            'backups' => $model->backups()->limit(7)->get(),
            'work' => WorkLog::query()->visible()->where('site_id', $model->id)->latest('performed_at')->limit(15)->get(),
        ]);
    }
}
