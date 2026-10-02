<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrgRole;
use App\Models\Membership;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Utilizatorii unei firme: invitare, schimbare de rol, eliminare. Un admin nu poate crea proprietari. */
final class MembershipService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    public function invite(User $actor, string $email, string $name, OrgRole $role): Membership
    {
        $this->assertCanAssign($actor, $role);
        $email = Str::lower(trim($email));
        $limit = $this->context->organization()->subscription?->limit('users');
        if ($limit !== null && Membership::query()->count() >= $limit) {
            throw ValidationException::withMessages(['email' => 'Planul curent nu permite mai mulți utilizatori.']);
        }
        $user = User::where('email', $email)->first();
        if ($user && Membership::query()->where('user_id', $user->getKey())->exists()) {
            throw ValidationException::withMessages(['email' => 'Utilizatorul face deja parte din firmă.']);
        }

        $isNew = $user === null;
        $membership = DB::transaction(function () use (&$user, $email, $name, $role): Membership {
            // parolă aleatoare nefolosibilă; utilizatorul își setează parola din linkul primit
            $user ??= User::create(['name' => $name, 'email' => $email, 'password' => Str::password(40)]);
            $membership = Membership::create(['user_id' => $user->getKey(), 'role' => $role]);
            $this->audit->record('user.invited', $membership, ['role' => $role->value]);

            return $membership;
        });
        if ($isNew) {
            app(Invitations::class)->send($user, $this->context->organization()->name);
        }

        return $membership;
    }

    public function changeRole(User $actor, Membership $membership, OrgRole $role): Membership
    {
        $this->assertCanAssign($actor, $membership->role);
        $this->assertCanAssign($actor, $role);
        if ($membership->role === OrgRole::Owner && $role !== OrgRole::Owner) {
            $this->assertNotLastOwner();
        }
        $from = $membership->role;
        $membership->update(['role' => $role]);
        $this->audit->record('role.changed', $membership, ['from' => $from->value, 'to' => $role->value]);

        return $membership;
    }

    public function remove(User $actor, Membership $membership): void
    {
        $this->assertCanAssign($actor, $membership->role);
        if ($membership->role === OrgRole::Owner) {
            $this->assertNotLastOwner();
        }
        $this->audit->record('user.removed', $membership, ['role' => $membership->role->value]);
        $membership->delete();
    }

    private function assertCanAssign(User $actor, OrgRole $role): void
    {
        if ($actor->isPlatformStaff()) {
            return;
        }
        if (! ($actor->roleIn($this->context->organization())?->canAssign($role) ?? false)) {
            throw ValidationException::withMessages(['role' => 'Nu poți atribui sau modifica acest rol.']);
        }
    }

    private function assertNotLastOwner(): void
    {
        if (Membership::query()->where('role', OrgRole::Owner->value)->count() <= 1) {
            throw ValidationException::withMessages(['role' => 'Firma trebuie să aibă cel puțin un proprietar.']);
        }
    }
}
