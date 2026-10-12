<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** O pagină publică a site-ului firmei, citită de panou, din care agentul răspunde (servicii, produse, prețuri, condiții). */
#[Fillable(['site_id', 'url', 'url_hash', 'title', 'description', 'content', 'terms', 'kind', 'price', 'status', 'excluded', 'run', 'fetched_at'])]
class SitePage extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['excluded' => 'boolean', 'fetched_at' => 'datetime'];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public static function hash(string $url): string
    {
        return sha1($url);
    }
}
