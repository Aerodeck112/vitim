<?php

declare(strict_types=1);

namespace App\Ai;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;

/** Implementarea cu SDK-ul oficial. Cache pe prefixul stabil (instrucțiuni + tool-uri) și fallback la refuz. */
final class AnthropicAiClient implements AiClient
{
    public function create(array $request): array
    {
        $key = (string) config('vitim.ai.api_key');
        if ($key === '') {
            throw new AiUnavailable('not_configured', 'ANTHROPIC_API_KEY lipsește din .env');
        }
        $client = new Client(
            apiKey: $key,
            baseUrl: config('vitim.ai.base_url') ?: null,
            requestOptions: ['timeout' => (float) config('vitim.ai.timeout'), 'maxRetries' => 2],
        );

        try {
            $response = $client->beta->messages->create(
                model: $request['model'],
                maxTokens: $request['max_tokens'],
                system: [['type' => 'text', 'text' => $request['system'], 'cacheControl' => ['type' => 'ephemeral']]],
                messages: array_map(self::toSdk(...), $request['messages']),
                tools: array_map(fn (array $t) => [
                    'name' => $t['name'], 'description' => $t['description'], 'strict' => true, 'inputSchema' => $t['input_schema'],
                ], $request['tools']) ?: null,
                outputConfig: ['effort' => $request['effort']],
                cacheControl: ['type' => 'ephemeral'],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException|PermissionDeniedException $e) {
            throw new AiUnavailable('auth', $e->getMessage(), $e);
        } catch (RateLimitException $e) {
            throw new AiUnavailable('rate_limited', $e->getMessage(), $e);
        } catch (APIStatusException|APIConnectionException $e) {
            throw new AiUnavailable('unavailable', $e->getMessage(), $e);
        }

        return json_decode((string) json_encode($response), true);
    }

    /** Formatul de pe fir → parametrii SDK. Turele asistentului se refac din răspunsul salvat, neschimbate. */
    private static function toSdk(array $message): array
    {
        if ($message['role'] === 'assistant') {
            return ['role' => 'assistant', 'content' => BetaMessage::fromArray($message['raw'])->content];
        }
        if (is_string($message['content'])) {
            return ['role' => 'user', 'content' => $message['content']];
        }

        return ['role' => 'user', 'content' => array_map(fn (array $r) => [
            'type' => 'tool_result', 'toolUseID' => $r['tool_use_id'], 'content' => $r['content'], 'isError' => (bool) ($r['is_error'] ?? false),
        ], $message['content'])];
    }
}
