<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AgentStatus;
use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agentul AI al unei firme. Configurația (ton, limbi, program, reguli de handoff și lead, acțiuni permise,
 * surse de cunoștințe, fallback) e normalizată de AgentConfiguration; nu conține secrete.
 */
#[Fillable(['site_id', 'name', 'status', 'default_language', 'model_configuration', 'system_configuration'])]
class Agent extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return [
            'status' => AgentStatus::class,
            'model_configuration' => 'array',
            'system_configuration' => 'array',
        ];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
