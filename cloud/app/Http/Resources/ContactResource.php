<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Contact */
final class ContactResource extends JsonResource
{
    /** Starea curentă a consimțământului [canal][scop] (setată doar pe detaliul unui contact). */
    public ?array $consentMatrix = null;

    public function withConsents(array $matrix): self
    {
        $this->consentMatrix = $matrix;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'language' => $this->language,
            'source' => $this->source->value,
            'status' => $this->status,
            'custom_fields' => $this->custom_fields ?? (object) [],
            'identities' => $this->whenLoaded('identities', fn () => $this->identities->map(fn ($i) => [
                'type' => $i->type->value,
                'provider' => $i->provider ?: null,
                'value' => $i->value,
                'normalized_value' => $i->normalized_value,
                'verified' => $i->verified,
                'primary' => $i->is_primary,
            ])->values()),
            'consents' => $this->when($this->consentMatrix !== null, fn () => array_map(
                fn (array $purposes) => array_map(fn ($status) => $status->value, $purposes),
                $this->consentMatrix,
            )),
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
