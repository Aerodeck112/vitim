<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureTwoFactor;
use App\Security\Totp;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class TwoFactorController extends Controller
{
    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $request->user()->hasTwoFactor()) {
            return redirect()->route('home');
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        $this->checkCode($request, (string) $user->totp_secret, 'Cod incorect.');
        $request->session()->put(EnsureTwoFactor::SESSION_KEY, true);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function setup(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->hasTwoFactor()) {
            return redirect()->route('home');
        }
        $secret = $request->session()->get('totp_pending') ?? Totp::secret();
        $request->session()->put('totp_pending', $secret);

        return view('auth.two-factor-setup', [
            'secret' => trim(chunk_split($secret, 4, ' ')),
            'uri' => Totp::uri($secret, $user->email),
            'required' => $user->requiresTwoFactor(),
        ]);
    }

    public function confirm(Request $request, AuditLogger $audit): RedirectResponse
    {
        $secret = (string) $request->session()->get('totp_pending');
        if ($secret === '') {
            return redirect()->route('2fa.setup');
        }
        $this->checkCode($request, $secret, 'Cod incorect. Verifică ora telefonului și încearcă din nou.');
        $user = $request->user();
        $user->forceFill(['totp_secret' => $secret, 'totp_confirmed_at' => now()])->save();
        $request->session()->forget('totp_pending');
        $request->session()->put(EnsureTwoFactor::SESSION_KEY, true);
        $audit->record('auth.two_factor_enabled', $user);

        return redirect()->route('home')->with('ok', 'Autentificarea în doi pași este activă.');
    }

    private function checkCode(Request $request, string $secret, string $message): void
    {
        $request->validate(['code' => ['required', 'string', 'max:12']]);
        $key = '2fa:'.$request->user()->getKey();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Prea multe încercări. Așteaptă '.RateLimiter::availableIn($key).' secunde.']);
        }
        if (! Totp::verify($secret, (string) $request->input('code'))) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['code' => $message]);
        }
        RateLimiter::clear($key);
    }
}
