<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** O filă deschisă pe site-ul firmei (anonim): pagina curentă și ultima dată când a dat semn. */
#[Fillable(['site_id', 'visitor', 'page', 'title', 'started_at', 'seen_at'])]
class SiteVisitor extends Model
{
    use BelongsToOrganization;

    /** Fără semn de viață în acest interval = a plecat (fila trimite un semn la 30 de secunde cât e vizibilă). */
    public const ACTIVE_SECONDS = 75;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'seen_at' => 'datetime'];
    }

    /** @param Builder<SiteVisitor> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('seen_at', '>=', now()->subSeconds(self::ACTIVE_SECONDS));
    }
}
