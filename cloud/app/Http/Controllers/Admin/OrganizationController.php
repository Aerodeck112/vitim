<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrgRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\OrganizationService;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class OrganizationController extends Controller
{
    /** Lista clienților. Cod de platformă: agregările peste toate organizațiile sunt intenționate. */
    public function index(): View
    {
        $organizations = Organization::query()->orderBy('name')->get();
        $sites = Site::withoutTenancy()->select('organization_id', DB::raw('count(*) as n'))->groupBy('organization_id')->pluck('n', 'organization_id');
        $members = Membership::withoutTenancy()->select('organization_id', DB::raw('count(*) as n'))->groupBy('organization_id')->pluck('n', 'organization_id');
        $subscriptions = Subscription::withoutTenancy()->get()->keyBy('organization_id');

        return view('admin.organizations.index', [
            'organizations' => $organizations,
            'sites' => $sites,
            'members' => $members,
            'subscriptions' => $subscriptions,
            'kpi' => [
                'Clienți' => $organizations->count(),
                'Site-uri' => (int) $sites->sum(),
                'Abonamente active' => $subscriptions->filter(fn (Subscription $s) => $s->isServiceable())->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.organizations.create', ['plans' => array_keys(config('plans.plans'))]);
    }

    public function store(Request $request, OrganizationService $organizations, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'plan' => ['required', Rule::in(array_keys(config('plans.plans')))],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:190'],
        ]);
        $email = Str::lower($data['owner_email']);
        $owner = User::where('email', $email)->first();
        $isNew = $owner === null;
        if ($isNew) {
            // parolă aleatoare, nefolosibilă; proprietarul își setează parola din linkul primit pe email
            $owner = User::create(['name' => $data['owner_name'], 'email' => $email, 'password' => Str::password(40)]);
        }
        $organization = $organizations->create($data['name'], $data['plan'], $owner);
        if ($isNew) {
            Password::sendResetLink(['email' => $email]);
        }

        return redirect()->route('admin.organizations.show', $organization->slug)
            ->with('ok', $isNew ? "Client creat. {$email} a primit linkul de setare a parolei." : 'Client creat; utilizatorul existent a devenit proprietar.');
    }

    /** Rulează în contextul organizației (middleware `org`, acces auditat). */
    public function show(TenantContext $context): View
    {
        $organization = $context->organization();

        return view('admin.organizations.show', [
            'organization' => $organization,
            'subscription' => $organization->subscription,
            'sites' => Site::query()->with(['keys' => fn ($q) => $q->latest()])->orderBy('domain')->get(),
            'members' => Membership::query()->with('user')->get(),
            'audit' => AuditLog::query()->latest('id')->limit(15)->get(),
            'roles' => OrgRole::cases(),
        ]);
    }
}
