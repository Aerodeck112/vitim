<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Enums\Channel;

/** Ce primește un furnizor: destinatarul normalizat și conținutul deja pregătit. */
final readonly class OutboundMessage
{
    /** @param array<string, scalar|null> $metadata */
    public function __construct(
        public int $messageId,
        public Channel $channel,
        public string $to,
        public string $body,
        public ?string $subject = null,
        public array $metadata = [],
    ) {}
}
