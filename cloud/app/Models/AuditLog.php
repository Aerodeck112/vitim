<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Jurnal de audit. Rândurile fără organizație sunt evenimente ale platformei (ex. login al echipei VITIM).
 * Se scrie doar prin AuditLogger. Nu conține conținut de conversații sau date de lead-uri.
 */
#[Fillable(['organization_id', 'actor_user_id', 'actor_type', 'action', 'target_type', 'target_id', 'ip', 'meta'])]
class AuditLog extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    protected static function allowsPlatformRows(): bool
    {
        return true;
    }
}
