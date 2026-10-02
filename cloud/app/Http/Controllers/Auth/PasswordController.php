<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Parolă uitată + setarea parolei pentru conturile noi (același link de resetare). */
final class PasswordController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:190']]);
        Password::sendResetLink(['email' => strtolower((string) $request->input('email'))]);

        // același răspuns indiferent dacă adresa există (nu confirmăm conturi)
        return back()->with('ok', 'Dacă adresa are cont, am trimis un link pentru setarea parolei.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email', ''), 'invite' => $request->boolean('invitatie')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)],
        ]);
        // linkurile din invitații (7 zile) și cele de resetare (60 de minute) sunt în tabele separate
        $status = Password::broker($request->boolean('invite') ? 'invites' : null)->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->setRememberToken(null);
                $user->save();
                // un singur link valabil: după setarea parolei, invitația și resetarea nu mai pot fi folosite
                Password::broker('invites')->deleteToken($user);
                Password::broker()->deleteToken($user);
            }
        );
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => $request->boolean('invite')
                ? 'Invitația a expirat sau a fost deja folosită. Cere echipei VITIM să o retrimită, sau folosește „Am uitat parola”.'
                : 'Linkul nu mai este valid. Cere unul nou.']);
        }

        return redirect()->route('login')->with('ok', 'Parola a fost setată. Te poți autentifica.');
    }
}
