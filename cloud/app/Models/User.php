<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrgRole;
use App\Enums\PlatformRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

/**
 * Utilizatorii sunt globali (un om poate lucra pentru mai multe firme); accesul la o firmă
 * vine din Membership. platform_role nu e fillable: se setează doar explicit, din cod de platformă.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'totp_secret'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'totp_secret' => 'encrypted',
            'totp_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'platform_role' => PlatformRole::class,
        ];
    }

    public function isPlatformStaff(): bool
    {
        return $this->platform_role !== null;
    }

    public function hasTwoFactor(): bool
    {
        return $this->totp_confirmed_at !== null && $this->totp_secret !== null;
    }

    /** Echipa VITIM are acces la datele tuturor clienților: autentificarea în doi pași e obligatorie. */
    public function requiresTwoFactor(): bool
    {
        return $this->isPlatformStaff();
    }

    /** Rolul în organizație sau null dacă utilizatorul nu e membru. */
    public function roleIn(Organization $organization): ?OrgRole
    {
        $membership = Membership::withoutTenancy()
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $this->getKey())
            ->first();

        return $membership?->role;
    }

    /** @return Collection<int, Organization> */
    public function organizations(): Collection
    {
        $ids = Membership::withoutTenancy()->where('user_id', $this->getKey())->pluck('organization_id');

        return Organization::query()->whereIn('id', $ids)->orderBy('name')->get();
    }
}
