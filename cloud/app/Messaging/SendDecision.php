<?php

declare(strict_types=1);

namespace App\Messaging;

final readonly class SendDecision
{
    private function __construct(public bool $allowed, public string $reason) {}

    public static function allow(): self
    {
        return new self(true, 'ok');
    }

    public static function deny(string $reason): self
    {
        return new self(false, $reason);
    }
}
