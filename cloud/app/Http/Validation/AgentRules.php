<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Enums\AgentStatus;
use Illuminate\Validation\Rule;

final class AgentRules
{
    /** Conținutul configurațiilor e validat în detaliu de AgentConfiguration. @return array<string, mixed> */
    public static function agent(bool $creating): array
    {
        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:160'],
            'site_id' => ['sometimes', 'nullable', 'integer'],
            'status' => ['sometimes', Rule::enum(AgentStatus::class)],
            'default_language' => ['sometimes', 'string', 'size:2', 'alpha'],
            'model_configuration' => ['sometimes', 'array'],
            'system_configuration' => ['sometimes', 'array'],
        ];
    }
}
