<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Site;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class HomeController extends Controller
{
    /** Unde ajunge fiecare după autentificare. */
    public function home(Request $request): RedirectResponse|View
    {
        $user = $request->user();
        if ($user->isPlatformStaff()) {
            return redirect()->route('admin.organizations.index');
        }
        $organizations = $user->organizations();
        if ($organizations->count() === 1) {
            return redirect()->route('portal.home', $organizations->first()->slug);
        }

        return view('portal.choose', ['organizations' => $organizations]);
    }

    /** Portalul clientului (MVP: site-urile și codul de instalare; rapoartele vin în Faza 7). */
    public function portal(TenantContext $context): View
    {
        return view('portal.home', [
            'organization' => $context->organization(),
            'sites' => Site::query()->with(['keys' => fn ($q) => $q->whereNull('revoked_at')])->orderBy('domain')->get(),
            'canManageSites' => Gate::allows(Permission::ManageSites->value),
        ]);
    }
}
