<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** O remediere cerută din panou și executată de pluginul site-ului. */
#[Fillable(['site_id', 'action', 'target', 'status', 'result', 'requested_by', 'duration_ms'])]
class SiteCommand extends Model
{
    use BelongsToOrganization;

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
