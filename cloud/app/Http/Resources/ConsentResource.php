<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ContactConsent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContactConsent */
final class ConsentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contact_id' => $this->contact_id,
            'channel' => $this->channel->value,
            'purpose' => $this->purpose->value,
            'status' => $this->status->value,
            'source' => $this->source,
            'ip_address' => $this->ip_address,
            'metadata' => $this->metadata ?? (object) [],
            'recorded_by_user_id' => $this->recorded_by_user_id,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
        ];
    }
}
