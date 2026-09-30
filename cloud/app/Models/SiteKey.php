<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * public_key: apare în pagina clientului (identifică site-ul, nu e secret).
 * secret: doar pentru cereri server-to-server semnate HMAC (pluginul WordPress); stocat criptat.
 */
#[Fillable(['organization_id', 'site_id', 'public_key', 'secret', 'last_used_at', 'revoked_at'])]
#[Hidden(['secret'])]
class SiteKey extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
