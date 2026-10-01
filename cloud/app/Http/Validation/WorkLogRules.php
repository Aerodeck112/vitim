<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Enums\WorkCategory;
use Illuminate\Validation\Rule;

final class WorkLogRules
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'site_id' => ['nullable', 'integer'],
            'performed_at' => ['required', 'date'],
            'category' => ['required', Rule::enum(WorkCategory::class)],
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:5000'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'visible_to_client' => ['sometimes', 'boolean'],
        ];
    }
}
