<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Agent;
use App\Models\Conversation;

final readonly class ToolContext
{
    public function __construct(
        public Agent $agent,
        public Conversation $conversation,
        public ?string $ip = null,
        public ?string $userAgent = null,
    ) {}

    /** Conversațiile de test din panou nu creează date reale. */
    public function isTest(): bool
    {
        return $this->conversation->is_test;
    }
}
