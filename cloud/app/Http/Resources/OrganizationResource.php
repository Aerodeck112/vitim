<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Organization */
final class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'company_name' => $this->company_name,
            'vat_id' => $this->vat_id,
            'country' => $this->country,
            'timezone' => $this->timezone,
            'default_language' => $this->default_language,
            'subscription' => $this->when(
                $this->resource->relationLoaded('subscriptionWithoutTenancy') || $this->resource->relationLoaded('subscription'),
                function () {
                    $s = $this->resource->relationLoaded('subscriptionWithoutTenancy') ? $this->subscriptionWithoutTenancy : $this->subscription;

                    return $s ? ['plan' => $s->plan, 'status' => $s->status->value, 'trial_ends_at' => $s->trial_ends_at?->toIso8601String()] : null;
                }
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
