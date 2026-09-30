<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Lead */
final class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'contact_id' => $this->contact_id,
            'contact' => $this->whenLoaded('contact', fn () => ['id' => $this->contact->id, 'name' => $this->contact->displayName()]),
            'site_id' => $this->site_id,
            'agent_id' => $this->agent_id,
            'conversation_id' => $this->conversation_id,
            'source' => $this->source,
            'status' => $this->status->value,
            'intent' => $this->intent->value,
            'score' => $this->score,
            'assigned_to' => $this->assigned_to,
            'summary' => $this->summary,
            'value_amount' => $this->value_amount,
            'currency' => $this->currency,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
