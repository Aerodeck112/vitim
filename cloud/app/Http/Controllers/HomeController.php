<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
}
