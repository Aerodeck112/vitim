<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\Permission;
use App\Knowledge\SiteKnowledge;
use App\Knowledge\SiteLearner;
use App\Models\Agent;
use App\Models\Product;
use App\Models\SitePage;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Ce știe agentul din site: paginile citite, produsele magazinului, citire la cerere și un test de căutare. */
final class AgentKnowledgeController extends PortalController
{
    public function show(Request $request, SiteKnowledge $knowledge, int $agent): View
    {
        $model = Agent::query()->with('site')->findOrFail($agent);
        $site = $model->site;
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 200);
        $filter = (string) $request->query('f', '');
        $pages = $site ? SitePage::query()->where('site_id', $site->id)->whereIn('status', ['ok', 'pending'])
            ->when($filter === 'product', fn ($x) => $x->where('kind', 'product'))
            ->when($filter === 'excluded', fn ($x) => $x->where('excluded', true))
            ->orderByRaw("case when status = 'ok' then 0 else 1 end")->orderBy('kind')->orderBy('url')->paginate(50)->withQueryString() : null;

        return view('portal.agents.knowledge', [
            'organization' => $this->organization(),
            'agent' => $model,
            'site' => $site,
            'pages' => $pages,
            'filter' => $filter,
            'counts' => $site ? [
                'ok' => SitePage::query()->where('site_id', $site->id)->where('status', 'ok')->where('excluded', false)->count(),
                'products' => SitePage::query()->where('site_id', $site->id)->where('status', 'ok')->where('kind', 'product')->count(),
                'excluded' => SitePage::query()->where('site_id', $site->id)->where('excluded', true)->count(),
                'pending' => SitePage::query()->where('site_id', $site->id)->where('run', $site->learn_run)->where('status', 'pending')->count(),
                'shop' => Product::query()->where('site_id', $site->id)->count(),
            ] : null,
            'q' => $q,
            'results' => $site && $q !== '' ? $knowledge->search($site->id, $q) : [],
            'products' => $site && $q !== '' ? $knowledge->products($site->id, $q) : [],
            'canManage' => Gate::allows(Permission::ManageKnowledge->value),
        ]);
    }

    public function learn(SiteLearner $learner, AuditLogger $audit, int $agent): RedirectResponse
    {
        $model = Agent::query()->with('site')->findOrFail($agent);
        abort_if($model->site === null, 422, 'Alege întâi site-ul agentului.');
        if (in_array($model->site->learn_status, ['queued', 'running'], true)) {
            return $this->to('portal.agents.knowledge', ['agent' => $model->id], 'Site-ul se citește deja.');
        }
        $learner->queue($model->site);
        $audit->record('site.learn_requested', $model->site);

        return $this->to('portal.agents.knowledge', ['agent' => $model->id], 'Am pornit citirea site-ului. Durează câteva minute; pagina se actualizează singură.');
    }

    public function page(Request $request, AuditLogger $audit, int $agent, int $page): RedirectResponse
    {
        $model = Agent::query()->findOrFail($agent);
        $row = SitePage::query()->where('site_id', $model->site_id)->findOrFail($page);
        $row->forceFill(['excluded' => $request->boolean('excluded')])->save();
        $audit->record($row->excluded ? 'site_page.excluded' : 'site_page.included', $row, ['url' => $row->url]);

        return back()->with('ok', $row->excluded ? 'Agentul nu mai folosește această pagină.' : 'Agentul folosește din nou această pagină.');
    }

    public function auto(Request $request, AuditLogger $audit, int $agent): RedirectResponse
    {
        $model = Agent::query()->with('site')->findOrFail($agent);
        abort_if($model->site === null, 422);
        $model->site->forceFill(['learn_auto' => $request->boolean('learn_auto')])->save();
        $audit->record('site.learn_auto', $model->site, ['on' => $model->site->learn_auto]);

        return back()->with('ok', $model->site->learn_auto ? 'Site-ul se recitește automat în fiecare săptămână.' : 'Site-ul se recitește doar la cerere.');
    }
}
