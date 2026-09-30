<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Un eveniment de consimțământ (acordat / retras). Append-only: istoricul dovedește cine, când, prin ce sursă
 * și pentru ce scop. Starea curentă = ultimul rând pe (contact, canal, scop). Se scrie doar prin ConsentService.
 */
#[Fillable(['contact_id', 'channel', 'purpose', 'status', 'source', 'ip_address', 'user_agent', 'metadata', 'recorded_by_user_id', 'occurred_at'])]
class ContactConsent extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Istoricul de consimțământ nu se modifică; înregistrează un eveniment nou.'));
    }

    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'purpose' => ConsentPurpose::class,
            'status' => ConsentStatus::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
