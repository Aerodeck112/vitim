<?php

declare(strict_types=1);

namespace App\Ai;

use RuntimeException;

/** Modelul AI nu poate răspunde acum. `reason`: not_configured | auth | rate_limited | unavailable. */
final class AiUnavailable extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct($message ?: $reason, 0, $previous);
    }
}
