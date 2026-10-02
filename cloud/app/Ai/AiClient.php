<?php

declare(strict_types=1);

namespace App\Ai;

/**
 * Apelul către modelul AI. Lucrează cu formatul de pe fir al Messages API (chei snake_case):
 * istoricul se salvează exact așa și se retrimite neschimbat.
 */
interface AiClient
{
    /**
     * @param  array{model: string, max_tokens: int, effort: string, system: string, messages: list<array<string, mixed>>, tools: list<array<string, mixed>>}  $request
     * @return array<string, mixed> răspunsul complet (id, content, stop_reason, usage...)
     *
     * @throws AiUnavailable
     */
    public function create(array $request): array;

    /** Există o cheie configurată (altfel agentul răspunde din informațiile firmei, fără model AI). */
    public function configured(): bool;
}
