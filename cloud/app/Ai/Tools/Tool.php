<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Agent;

/**
 * O acțiune pe care agentul o poate cere. Contextul (firmă, conversație, agent) vine de la server,
 * niciodată din argumentele modelului; argumentele sunt doar datele introduse de vizitator.
 */
interface Tool
{
    public function name(): string;

    /** @return array{name: string, description: string, input_schema: array<string, mixed>} */
    public function definition(Agent $agent): array;

    /** @param array<string, mixed> $input */
    public function handle(ToolContext $context, array $input): ToolResult;
}
