<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * După parolă: cine are 2FA activ trebuie să introducă codul; echipa VITIM trebuie să-l activeze
 * înainte de orice altceva.
 */
final class EnsureTwoFactor
{
    public const SESSION_KEY = 'two_factor_passed';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }
        if ($user->hasTwoFactor() && ! $request->session()->get(self::SESSION_KEY)) {
            return redirect()->route('2fa.challenge');
        }
        if (! $user->hasTwoFactor() && $user->requiresTwoFactor()) {
            return redirect()->route('2fa.setup');
        }

        return $next($request);
    }
}
