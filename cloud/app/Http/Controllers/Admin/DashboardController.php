<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AgentStatus;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Site;
use App\Models\SiteIssue;
use App\Models\Subscription;
use App\Models\UsageRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Dashboardul echipei VITIM. Cod de platformă: agregările peste toate organizațiile sunt intenționate
 * (withoutTenancy) și întorc doar numere, nu date ale clienților.
 */
final class DashboardController extends Controller
{
    public function index(): View
    {
        $count = fn (string $model) => $model::withoutTenancy()->select('organization_id', DB::raw('count(*) as n'))->groupBy('organization_id')->pluck('n', 'organization_id');
        $organizations = Organization::query()->orderBy('name')->get();
        $counts = ['sites' => $count(Site::class), 'agents' => $count(Agent::class), 'members' => $count(Membership::class),
            'contacts' => $count(Contact::class), 'leads' => $count(Lead::class)];

        return view('admin.dashboard', [
            'organizations' => $organizations,
            'counts' => $counts,
            'subscriptions' => Subscription::withoutTenancy()->get()->keyBy('organization_id'),
            'kpi' => [
                ['Organizații', $organizations->count(), null],
                ['Agenți activi', Agent::withoutTenancy()->where('status', AgentStatus::Active->value)->count(), 'Agentul AI răspunde vizitatorilor din Faza 2.'],
                ['Conversații', Conversation::withoutTenancy()->count(), 'Widgetul de chat vine în Faza 4.'],
                ['Lead-uri', (int) $counts['leads']->sum(), null],
                ['Contacte', (int) $counts['contacts']->sum(), null],
            ],
            'usage' => UsageRecord::withoutTenancy()->where('period_date', '>=', now()->startOfMonth()->toDateString())
                ->select('metric', DB::raw('sum(quantity) as total'))->groupBy('metric')->orderBy('metric')->pluck('total', 'metric'),
        ]);
    }

    public function sites(): View
    {
        return view('admin.sites.index', [
            // problemele deschise: doar numărul, peste toate firmele (cod de platformă)
            'sites' => Site::withoutTenancy()->with('organization')->orderBy('domain')->paginate(50),
            'openIssues' => SiteIssue::withoutTenancy()->where('status', 'open')
                ->select('site_id', DB::raw("sum(case when severity = 'critical' then 1 else 0 end) as critical"), DB::raw('count(*) as total'))
                ->groupBy('site_id')->get()->keyBy('site_id'),
        ]);
    }

    public function agents(): View
    {
        return view('admin.agents', [
            'agents' => Agent::withoutTenancy()->with(['organization', 'site' => fn ($q) => $q->withoutGlobalScopes()])->orderBy('name')->paginate(50),
        ]);
    }

    public function users(): View
    {
        $memberships = Membership::withoutTenancy()->select('user_id', DB::raw('count(*) as n'))->groupBy('user_id')->pluck('n', 'user_id');

        return view('admin.users', [
            'users' => User::query()->orderBy('name')->paginate(50),
            'memberships' => $memberships,
        ]);
    }
}
