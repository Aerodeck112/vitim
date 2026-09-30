<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureTwoFactor;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:200'],
        ]);
        $key = 'login:'.Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Prea multe încercări. Reîncearcă peste '.RateLimiter::availableIn($key).' secunde.']);
        }
        if (! Auth::attempt(['email' => Str::lower($data['email']), 'password' => $data['password']])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Email sau parolă greșită.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->forget(EnsureTwoFactor::SESSION_KEY);
        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();
        $audit->record('auth.login', $user);

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
