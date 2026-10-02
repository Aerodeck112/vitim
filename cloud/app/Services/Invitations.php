<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Notifications\Invitation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/** Invitarea utilizatorilor noi (prima parolă) și retrimiterea invitației când linkul a expirat. */
final class Invitations
{
    public function send(User $user, ?string $organization = null): void
    {
        $status = Password::broker('invites')->sendResetLink(['email' => $user->email], function (User $user, string $token) use ($organization): void {
            $user->notify(new Invitation($token, $organization));
        });
        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages(['invite' => 'Invitația tocmai a fost trimisă. Încearcă din nou peste un minut.']);
        }
    }

    /** null = nicio invitație; altfel data trimiterii, data expirării și dacă a expirat. @return array{sent: Carbon, expires: Carbon, expired: bool}|null */
    public static function status(User $user): ?array
    {
        $created = DB::table('invitation_tokens')->where('email', $user->email)->value('created_at');
        if ($created === null) {
            return null;
        }
        $sent = Carbon::parse($created);
        $expires = $sent->copy()->addMinutes((int) config('auth.passwords.invites.expire'));

        return ['sent' => $sent, 'expires' => $expires, 'expired' => $expires->isPast()];
    }

    /** Utilizatorul nu și-a setat încă parola (nu s-a autentificat niciodată). */
    public static function pending(User $user): bool
    {
        return $user->last_login_at === null;
    }
}
