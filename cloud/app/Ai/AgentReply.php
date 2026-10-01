<?php

declare(strict_types=1);

namespace App\Ai;

use App\Models\ToolExecution;

/**
 * Rezultatul unui schimb de replici.
 * status: ok | refused | unavailable (eroare / cheie lipsă) | capped (plafon de cost sau de conversații) | inactive
 */
final readonly class AgentReply
{
    /** @param list<ToolExecution> $tools */
    public function __construct(
        public string $text,
        public string $status = 'ok',
        public array $tools = [],
        public int $costMicroUsd = 0,
    ) {}

    public function answeredByAi(): bool
    {
        return $this->status === 'ok';
    }
}
