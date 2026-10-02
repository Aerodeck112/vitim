<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Ai\AiClient;
use App\Ai\AiUnavailable;

/** Model AI simulat: răspunsuri programate, în formatul API; păstrează cererile primite. */
final class FakeAiClient implements AiClient
{
    /** @var list<array<string, mixed>> */
    public array $requests = [];

    /** @param list<array<string, mixed>|AiUnavailable> $responses */
    public function __construct(private array $responses = []) {}

    public bool $configured = true;

    public function configured(): bool
    {
        return $this->configured;
    }

    public function create(array $request): array
    {
        $this->requests[] = $request;
        $next = array_shift($this->responses) ?? self::text('Răspuns implicit.');
        if ($next instanceof AiUnavailable) {
            throw $next;
        }

        return $next;
    }

    /** @return array<string, mixed> */
    public static function text(string $text): array
    {
        return self::message([['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'], ['type' => 'text', 'text' => $text]], 'end_turn');
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public static function tool(string $name, array $input, string $text = ''): array
    {
        $content = [['type' => 'thinking', 'thinking' => '', 'signature' => 'sig']];
        if ($text !== '') {
            $content[] = ['type' => 'text', 'text' => $text];
        }
        $content[] = ['type' => 'tool_use', 'id' => 'toolu_'.bin2hex(random_bytes(4)), 'name' => $name, 'input' => $input];

        return self::message($content, 'tool_use');
    }

    /** @return array<string, mixed> */
    public static function refusal(): array
    {
        return self::message([], 'refusal');
    }

    /** @param list<array<string, mixed>> $content @return array<string, mixed> */
    private static function message(array $content, string $stop): array
    {
        return [
            'id' => 'msg_'.bin2hex(random_bytes(4)), 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
            'content' => $content, 'stop_reason' => $stop, 'stop_sequence' => null,
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50, 'cache_read_input_tokens' => 2000, 'cache_creation_input_tokens' => 0],
        ];
    }
}
