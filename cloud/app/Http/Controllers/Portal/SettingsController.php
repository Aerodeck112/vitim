<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\OrgRole;
use App\Enums\Permission;
use App\Enums\SitePlatform;
use App\Models\Membership;
use App\Models\Site;
use App\Services\AuditLogger;
use App\Services\Invitations;
use App\Services\MembershipService;
use App\Services\OrganizationService;
use App\Services\SiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class SettingsController extends PortalController
{
    public function show(Request $request): View
    {
        $organization = $this->organization();
        $actor = $request->user();
        $actorRole = $actor->roleIn($organization);

        return view('portal.settings', [
            'organization' => $organization,
            'subscription' => $organization->subscription,
            'members' => Membership::query()->with('user')->orderBy('id')->get(),
            'sites' => Site::query()->with(['keys' => fn ($q) => $q->whereNull('revoked_at')])->orderBy('name')->get(),
            'roles' => array_values(array_filter(OrgRole::cases(), fn (OrgRole $r) => $actor->isPlatformStaff() || ($actorRole?->canAssign($r) ?? false))),
            'platforms' => SitePlatform::cases(),
            'can' => [
                'organization' => Gate::allows(Permission::ManageOrganization->value),
                'users' => Gate::allows(Permission::ManageUsers->value),
                'sites' => Gate::allows(Permission::ManageSites->value),
            ],
        ]);
    }

    public function updateProfile(Request $request, OrganizationService $organizations): RedirectResponse
    {
        $organizations->update($this->organization(), $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'company_name' => ['nullable', 'string', 'max:190'],
            'vat_id' => ['nullable', 'string', 'max:32'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'timezone' => ['required', 'timezone:all'],
            'default_language' => ['required', 'string', 'size:2', 'alpha'],
        ]));

        return $this->to('portal.settings', [], 'Datele firmei au fost salvate.');
    }

    public function invite(Request $request, MembershipService $members): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190'], 'name' => ['required', 'string', 'max:120'], 'role' => ['required', Rule::enum(OrgRole::class)]]);
        $members->invite($request->user(), $data['email'], $data['name'], OrgRole::from($data['role']));

        return $this->to('portal.settings', [], "Invitație trimisă la {$data['email']}.");
    }

    public function changeRole(Request $request, MembershipService $members, int $member): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::enum(OrgRole::class)]]);
        $members->changeRole($request->user(), Membership::query()->findOrFail($member), OrgRole::from($data['role']));

        return $this->to('portal.settings', [], 'Rol actualizat.');
    }

    public function removeMember(Request $request, MembershipService $members, int $member): RedirectResponse
    {
        $members->remove($request->user(), Membership::query()->findOrFail($member));

        return $this->to('portal.settings', [], 'Utilizator eliminat din firmă.');
    }

    /** Retrimite invitația unui coleg care nu și-a setat încă parola (linkul expirase). */
    public function resendInvite(Invitations $invitations, AuditLogger $audit, int $member): RedirectResponse
    {
        $membership = Membership::query()->with('user')->findOrFail($member);
        abort_unless(Invitations::pending($membership->user), 422, 'Utilizatorul are deja parolă.');
        $invitations->send($membership->user, $this->organization()->name);
        $audit->record('user.invite_resent', $membership);

        return $this->to('portal.settings', [], 'Invitația a fost retrimisă la '.$membership->user->email.'.');
    }

    public function storeSite(Request $request, SiteService $sites): RedirectResponse
    {
        $data = $request->validate(['domain' => ['required', 'string', 'max:253'], 'name' => ['nullable', 'string', 'max:160'], 'platform' => ['required', Rule::enum(SitePlatform::class)]]);
        [, $issued] = $sites->create($data['domain'], SitePlatform::from($data['platform']), [], $data['name'] ?? null);

        return $this->to('portal.settings', [], 'Site adăugat.')->with('issued', ['public' => $issued->publicKey, 'secret' => $issued->secret]);
    }
}
