<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Validarea listei de probleme trimise de plugin (la scanare sau în răspunsul unei remedieri). */
final class ScanPayload
{
    /** @return array<string, mixed> */
    public static function rules(string $prefix = 'issues'): array
    {
        return [
            $prefix => ['present', 'array', 'max:300'],
            "{$prefix}.*.code" => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._:\/-]+$/'],
            "{$prefix}.*.severity" => ['required', Rule::in(['critical', 'warning', 'info'])],
            "{$prefix}.*.title" => ['required', 'string', 'max:255'],
            "{$prefix}.*.details" => ['nullable', 'string', 'max:4000'],
            "{$prefix}.*.fix" => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @param  array<mixed>  $issues
     * @return list<array{code: string, severity: string, title: string, details?: ?string, fix?: ?string}>
     */
    public static function issues(array $issues): array
    {
        return Validator::make(['issues' => $issues], self::rules())->validate()['issues'];
    }
}
