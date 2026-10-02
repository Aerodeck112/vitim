<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrgRole;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Site;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Invitations;
use App\Services\OrganizationService;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class OrganizationController extends Controller
{
    public function create(): View
    {
        return view('admin.organizations.create', ['plans' => array_keys(config('plans.plans'))]);
    }

    public function store(Request $request, OrganizationService $organizations): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'plan' => ['required', Rule::in(array_keys(config('plans.plans')))],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:190'],
            'company_name' => ['nullable', 'string', 'max:190'],
            'vat_id' => ['nullable', 'string', 'max:32'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'default_language' => ['required', 'string', 'size:2', 'alpha'],
        ]);
        $email = Str::lower($data['owner_email']);
        $owner = User::where('email', $email)->first();
        $isNew = $owner === null;
        if ($isNew) {
            // parolă aleatoare, nefolosibilă; proprietarul își setează parola din linkul primit pe email
            $owner = User::create(['name' => $data['owner_name'], 'email' => $email, 'password' => Str::password(40)]);
        }
        $organization = $organizations->create($data['name'], $data['plan'], $owner, $data);
        if ($isNew) {
            app(Invitations::class)->send($owner, $organization->name);
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
            'sites' => Site::query()->with(['keys' => fn ($q) => $q->latest(), 'issues'])->orderBy('domain')->get(),
            'members' => Membership::query()->with('user')->get(),
            'agents' => Agent::query()->with('site')->orderBy('name')->get(),
            'audit' => AuditLog::query()->latest('id')->limit(15)->get(),
            'roles' => OrgRole::cases(),
        ]);
    }

    /** Retrimite invitația unui utilizator al clientului care nu și-a setat încă parola. */
    public function resendInvite(TenantContext $context, Invitations $invitations, AuditLogger $audit, int $member): RedirectResponse
    {
        $membership = Membership::query()->with('user')->findOrFail($member); // doar membrii firmei din context
        $invitations->send($membership->user, $context->organization()->name);
        $audit->record('user.invite_resent', $membership);

        return redirect()->route('admin.organizations.show', $context->organization()->slug)
            ->with('ok', 'Invitația a fost retrimisă la '.$membership->user->email.'. Linkul e valabil 7 zile.');
    }
}
