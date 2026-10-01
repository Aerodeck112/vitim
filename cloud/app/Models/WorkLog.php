<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WorkCategory;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** O lucrare făcută de echipa VITIM pentru client. Se scrie doar prin WorkLogService. */
#[Fillable(['site_id', 'performed_by', 'performed_at', 'category', 'title', 'description', 'duration_minutes', 'visible_to_client', 'source', 'external_ref'])]
class WorkLog extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
            'category' => WorkCategory::class,
            'visible_to_client' => 'boolean',
        ];
    }

    /** @param Builder<WorkLog> $query */
    public function scopeVisible(Builder $query): void
    {
        $query->where('visible_to_client', true);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
