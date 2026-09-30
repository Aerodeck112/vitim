<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Agent */
final class AgentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'site_id' => $this->site_id,
            'name' => $this->name,
            'status' => $this->status->value,
            'default_language' => $this->default_language,
            'model_configuration' => $this->model_configuration,
            'system_configuration' => $this->system_configuration,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
