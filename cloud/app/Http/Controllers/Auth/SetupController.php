<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Primul administrator VITIM, fără terminal: doar cât timp nu există utilizatori
 * și doar cu SETUP_TOKEN din .env. Altfel 404.
 */
final class SetupController extends Controller
{
    public function show(): View
    {
        $this->ensureAvailable();

        return view('auth.setup');
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->ensureAvailable();
        $data = $request->validate([
            'token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)],
        ]);
        if (! hash_equals((string) config('vitim.setup_token'), $data['token'])) {
            throw ValidationException::withMessages(['token' => 'Token greșit.']);
        }
        $user = User::create(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password']]);
        $user->forceFill(['platform_role' => PlatformRole::Admin])->save();
        Auth::login($user);
        $request->session()->regenerate();
        $audit->record('platform.first_admin_created', $user, [], 'platform');

        return redirect()->route('2fa.setup');
    }

    private function ensureAvailable(): void
    {
        if (strlen((string) config('vitim.setup_token')) < 16 || User::query()->exists()) {
            abort(404);
        }
    }
}
