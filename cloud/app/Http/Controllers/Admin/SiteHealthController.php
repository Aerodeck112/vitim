<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Audit\Guidance;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteIssue;
use App\Services\Remediation;
use App\Services\SiteAuditService;
use App\Services\SiteCommandService;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Echipa VITIM: problemele găsite pe un site și remedierea lor prin plugin (în contextul firmei, middleware `org`). */
final class SiteHealthController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function show(Request $request, int $site): View
    {
        $model = Site::query()->findOrFail($site);
        $order = "CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END";

        return view('admin.sites.health', [
            'organization' => $this->context->organization(),
            'site' => $model,
            'open' => SiteIssue::query()->where('site_id', $model->id)->where('status', 'open')->orderByRaw($order)->orderBy('title')->get(),
            'resolved' => SiteIssue::query()->where('site_id', $model->id)->where('status', 'resolved')->latest('resolved_at')->limit(10)->get(),
            'commands' => $model->commands()->with('requester')->limit(20)->get(),
            'backups' => $model->backups()->limit(10)->get(),
            'category' => array_key_exists((string) $request->query('categorie'), Guidance::CATEGORIES) ? (string) $request->query('categorie') : null,
        ]);
    }

    public function audit(SiteAuditService $audits, int $site): RedirectResponse
    {
        $model = Site::query()->findOrFail($site);
        $count = $audits->run($model);

        return redirect()->route('admin.sites.health', [$this->context->organization()->slug, $model->id])
            ->with('ok', "Audit terminat: {$count} constatări.");
    }

    public function command(Request $request, SiteCommandService $commands, SiteAuditService $audits, int $site): RedirectResponse
    {
        $model = Site::query()->findOrFail($site);
        $data = $request->validate(['fix' => ['required', 'string', 'max:200']]);
        $command = $commands->run($model, $data['fix'], $request->user());
        $label = Remediation::label($data['fix']);
        if ($command->status === 'done' && in_array($command->action, ['seo_fix', 'allow_indexing'], true)) {
            // auditul extern se reface imediat: problemele rezolvate trec la „Rezolvate recent”
            $audits->run($model->refresh());
        }

        return redirect()->route('admin.sites.health', [$this->context->organization()->slug, $model->id])
            ->with($command->status === 'done' ? 'ok' : 'error', "{$label}: {$command->result}");
    }
}
