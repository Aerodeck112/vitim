<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Eveniment de domeniu (outbox). payload conține ID-uri și valori tehnice, nu date personale.
 * Scris prin EventRecorder, procesat de `vitim:events` (sursa viitoare a automatizărilor).
 */
#[Fillable(['organization_id', 'type', 'subject_type', 'subject_id', 'payload', 'occurred_at', 'processed_at', 'attempts', 'last_error'])]
class DomainEvent extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    protected static function allowsPlatformRows(): bool
    {
        return true;
    }
}
