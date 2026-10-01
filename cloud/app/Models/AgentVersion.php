<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Instantaneu al configurației unui agent, salvat la fiecare modificare. Append-only. */
#[Fillable(['agent_id', 'version', 'model_configuration', 'system_configuration', 'created_by'])]
class AgentVersion extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['model_configuration' => 'array', 'system_configuration' => 'array'];
    }
}
