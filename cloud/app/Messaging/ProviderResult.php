<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Enums\MessageStatus;

final readonly class ProviderResult
{
    public function __construct(
        public MessageStatus $status,
        public ?string $externalId = null,
        public ?string $error = null,
    ) {}
}
