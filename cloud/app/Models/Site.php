<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SitePlatform;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'domain', 'allowed_origins', 'platform', 'status', 'ai_enabled', 'widget_config', 'cookie_config'])]
class Site extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'allowed_origins' => 'array',
            'platform' => SitePlatform::class,
            'ai_enabled' => 'boolean',
            'widget_config' => 'array',
            'cookie_config' => 'array',
            'health' => 'array',
            'last_seen_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'last_scan_at' => 'datetime',
            'last_audit_at' => 'datetime',
            'scores' => 'array',
        ];
    }

    /** Conectorul a raportat în ultimele 3 ore (heartbeat-ul e la oră). */
    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subHours(3));
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
        $scheme = $parts['scheme'] ?? '';
        // doar https; http e acceptat numai pe mediul local de dezvoltare (site-uri de test)
        if (($scheme !== 'https' && ($scheme !== 'http' || ! app()->environment('local'))) || empty($parts['host'])) {
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

    /** @return HasMany<SiteIssue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(SiteIssue::class);
    }

    /** @return HasMany<SiteCommand, $this> */
    public function commands(): HasMany
    {
        return $this->hasMany(SiteCommand::class)->latest('id');
    }

    /** @return HasMany<SiteBackup, $this> */
    public function backups(): HasMany
    {
        return $this->hasMany(SiteBackup::class)->latest('started_at');
    }
}
