<?php

declare(strict_types=1);

namespace App\Ai;

/** Costul unui răspuns, din tokenii raportați de API, în micro-USD (1 USD = 1.000.000). */
final class Cost
{
    /** @param array<string, mixed> $usage */
    public static function microUsd(string $model, array $usage): int
    {
        $p = config("vitim.ai.prices.{$model}") ?? config('vitim.ai.default_price');

        // preț per milion de tokeni → micro-USD per token = preț
        return (int) ceil(
            (int) ($usage['input_tokens'] ?? 0) * $p['input']
            + (int) ($usage['output_tokens'] ?? 0) * $p['output']
            + (int) ($usage['cache_creation_input_tokens'] ?? 0) * $p['cache_write']
            + (int) ($usage['cache_read_input_tokens'] ?? 0) * $p['cache_read']
        );
    }
}
