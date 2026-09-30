<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'domain', 'allowed_origins', 'platform', 'connector_version', 'last_seen_at', 'status'])]
class Site extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'allowed_origins' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Widgetul poate fi încărcat doar de pe domeniul site-ului (cu sau fără www)
     * și de pe gazdele adăugate explicit. Doar https.
     */
    public function allowsOrigin(?string $origin): bool
    {
        if ($origin === null || $origin === '') {
            return false;
        }
        $parts = parse_url($origin);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }
        $host = strtolower($parts['host']);
        $allowed = array_map('strtolower', [$this->domain, 'www.'.$this->domain, ...($this->allowed_origins ?? [])]);

        return in_array($host, $allowed, true);
    }

    /** @return HasMany<SiteKey, $this> */
    public function keys(): HasMany
    {
        return $this->hasMany(SiteKey::class);
    }
}
