<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Site */
final class SiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'domain' => $this->domain,
            'platform' => $this->platform->value,
            'status' => $this->status,
            'verification_status' => $this->verification_status,
            'allowed_origins' => $this->allowed_origins ?? [],
            // doar cheia publică (apare oricum în pagina clientului); secretul nu se întoarce niciodată
            'public_key' => $this->whenLoaded('keys', fn () => $this->keys->firstWhere('revoked_at', null)?->public_key),
            'connector_version' => $this->connector_version,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'last_sync_at' => $this->last_sync_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
