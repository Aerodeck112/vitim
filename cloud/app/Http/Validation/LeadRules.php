<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Enums\LeadIntent;
use App\Enums\LeadStatus;
use Illuminate\Validation\Rule;

final class LeadRules
{
    /** @return array<string, mixed> */
    public static function lead(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(LeadStatus::class)],
            'intent' => ['sometimes', Rule::enum(LeadIntent::class)],
            'source' => ['sometimes', 'string', 'max:24', 'alpha_dash'],
            'score' => ['sometimes', 'nullable', 'integer', 'between:0,100'],
            'assigned_to' => ['sometimes', 'nullable', 'integer'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'site_id' => ['sometimes', 'nullable', 'integer'],
            'agent_id' => ['sometimes', 'nullable', 'integer'],
            'value_amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3', 'alpha'],
        ];
    }
}
