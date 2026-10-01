<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Jurnalul apelurilor de tool-uri făcute de agent: ce a cerut modelul, ce s-a întâmplat. */
#[Fillable(['conversation_id', 'agent_id', 'tool', 'input', 'result', 'status', 'duration_ms'])]
class ToolExecution extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['input' => 'array'];
    }
}
