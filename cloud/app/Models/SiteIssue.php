<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** O problemă găsită de scanarea conectorului pe un site. Se închide singură când nu mai apare la scanare. */
#[Fillable(['site_id', 'code', 'category', 'source', 'severity', 'title', 'details', 'fix', 'status', 'first_seen_at', 'last_seen_at', 'resolved_at'])]
class SiteIssue extends Model
{
    use BelongsToOrganization;

    public const SEVERITIES = ['critical' => 'Critic', 'warning' => 'Atenție', 'info' => 'Info'];

    protected function casts(): array
    {
        return ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
