<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Channel;
use App\Models\Campaign;

/** Conținutul unui mesaj de marketing (dintr-o campanie sau dintr-un pas de automatizare). */
final readonly class MessageContent
{
    /** @param array<string, mixed>|null $template @param list<array<string, mixed>>|null $blocks */
    public function __construct(
        public Channel $channel,
        public ?string $subject,
        public ?string $body,
        public ?array $template = null,
        public ?array $blocks = null,
        public ?string $preheader = null,
    ) {}

    public static function of(Campaign $campaign): self
    {
        return new self($campaign->channel, $campaign->subject, $campaign->body, $campaign->template, $campaign->blocks ?? null, $campaign->preheader ?? null);
    }

    /** @param array<string, mixed> $config configurația unui pas de automatizare */
    public static function fromArray(Channel $channel, array $config): self
    {
        return new self($channel, $config['subject'] ?? null, $config['body'] ?? null, $config['template'] ?? null, $config['blocks'] ?? null, $config['preheader'] ?? null);
    }
}
