<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Tools\Tool;
use App\Ai\Tools\ToolContext;
use App\Ai\Tools\ToolRegistry;
use App\Ai\Tools\ToolResult;
use App\Enums\Channel;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Models\AiTurn;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ToolExecution;
use App\Services\UsageMeter;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bucla agentului: mesajul vizitatorului → model AI → tool-uri (max. N runde) → răspuns.
 * Rulează mereu în contextul firmei conversației; tool-urile primesc contextul de la server.
 */
final class AgentRuntime
{
    public function __construct(
        private readonly AiClient $client,
        private readonly PromptBuilder $prompts,
        private readonly ToolRegistry $registry,
        private readonly UsageMeter $usage,
        private readonly TenantContext $context,
    ) {}

    public function reply(Conversation $conversation, string $text, ?string $ip = null, ?string $userAgent = null): AgentReply
    {
        $text = mb_substr(trim($text), 0, (int) config('vitim.ai.max_message_chars'));
        $organization = $this->context->organization();
        $agent = $conversation->agent;
        $contact = $this->contactLine($conversation);

        $this->message($conversation, SenderType::Contact, $text);
        $blocked = match (true) {
            $agent === null || (! $agent->isActive() && ! $conversation->is_test) => new AgentReply('Asistentul nu este disponibil momentan. '.$contact, 'inactive'),
            ! $organization->isActive() || ! $organization->subscription?->isServiceable() => new AgentReply('Asistentul nu este disponibil momentan. '.$contact, 'inactive'),
            $this->overCap($conversation) => new AgentReply('Asistentul nu poate răspunde acum. '.$contact, 'capped'),
            default => null,
        };
        if ($blocked) {
            return $this->finish($conversation, $blocked);
        }

        $tools = $this->registry->forAgent($agent);
        $toolContext = new ToolContext($agent, $conversation, $ip, $userAgent);
        $model = $agent->model_configuration;
        $system = $this->prompts->build($organization, $agent);
        $definitions = array_values(array_map(fn ($t) => $t->definition($agent), $tools));

        $this->turn($conversation, 'user', ['content' => $text]);
        $executions = [];
        $texts = [];
        $cost = 0;
        $status = 'ok';
        try {
            for ($round = 0; $round < (int) config('vitim.ai.max_tool_rounds'); $round++) {
                $response = $this->client->create([
                    'model' => $model['model'],
                    'max_tokens' => (int) $model['max_output_tokens'],
                    'effort' => $model['effort'],
                    'system' => $system,
                    'messages' => $this->history($conversation),
                    'tools' => $definitions,
                ]);
                $cost += $this->meter($conversation, (string) ($response['model'] ?? $model['model']), (array) ($response['usage'] ?? []));

                if (($response['stop_reason'] ?? '') === 'refusal') {
                    // răspuns gol: nu intră în istoric (o tură de asistent fără conținut nu e validă)
                    $status = 'refused';
                    $texts = ['Nu te pot ajuta cu asta aici. '.$contact];
                    break;
                }
                $this->turn($conversation, 'assistant', $response);

                $results = [];
                foreach ($response['content'] ?? [] as $block) {
                    if (($block['type'] ?? '') === 'text' && trim((string) $block['text']) !== '') {
                        $texts[] = trim((string) $block['text']);
                    } elseif (($block['type'] ?? '') === 'tool_use') {
                        [$result, $execution] = $this->runTool($tools, $toolContext, $block);
                        $executions[] = $execution;
                        $results[] = ['type' => 'tool_result', 'tool_use_id' => $block['id'], 'content' => $result->message, 'is_error' => $result->isError()];
                    }
                }
                if ($results) {
                    // toate rezultatele într-o singură tură, ca istoricul să rămână valid
                    $this->turn($conversation, 'user', ['content' => $results]);
                }
                if (($response['stop_reason'] ?? '') !== 'tool_use' || ! $results) {
                    break;
                }
                $texts = [];
            }
        } catch (AiUnavailable $e) {
            Log::warning('AI indisponibil', ['reason' => $e->reason, 'organization_id' => $organization->id, 'conversation_id' => $conversation->id]);

            return $this->finish($conversation, new AgentReply('Momentan nu pot răspunde automat. '.$contact, 'unavailable', $executions, $cost));
        }

        $reply = trim(implode("\n\n", $texts));
        if ($reply === '') {
            $reply = 'Poți reformula, te rog? '.$contact;
        }

        return $this->finish($conversation, new AgentReply($reply, $status, $executions, $cost));
    }

    /**
     * @param  array<string, Tool>  $tools
     * @param  array<string, mixed>  $block
     * @return array{0: ToolResult, 1: ToolExecution}
     */
    private function runTool(array $tools, ToolContext $context, array $block): array
    {
        $name = (string) ($block['name'] ?? '');
        $input = is_array($block['input'] ?? null) ? $block['input'] : [];
        $started = hrtime(true);
        if (! isset($tools[$name])) {
            // modelul a cerut o acțiune care nu e permisă acestui agent
            $result = ToolResult::rejected('Acțiune indisponibilă.');
        } else {
            try {
                $result = $tools[$name]->handle($context, $input);
            } catch (Throwable $e) {
                report($e);
                $result = ToolResult::error('Nu am putut finaliza acțiunea. Oferă vizitatorului datele de contact ale firmei.');
            }
        }
        $execution = ToolExecution::create([
            'conversation_id' => $context->conversation->getKey(),
            'agent_id' => $context->agent->getKey(),
            'tool' => mb_substr($name, 0, 40),
            'input' => $input,
            'result' => $result->message,
            'status' => $result->status,
            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
        ]);

        return [$result, $execution];
    }

    /** Plafonul lunar de cost AI și, pentru conversațiile noi, numărul de conversații din plan. */
    private function overCap(Conversation $conversation): bool
    {
        $subscription = $this->context->organization()->subscription;
        $costCap = $subscription?->limit('ai_cost_cap_usd');
        if ($costCap !== null && $this->usage->thisMonth('ai_cost_micro_usd') >= $costCap * 1_000_000) {
            return true;
        }
        if ($conversation->is_test || AiTurn::query()->where('conversation_id', $conversation->getKey())->exists()) {
            return false;
        }
        $conversations = $subscription?->limit('conversations_per_month');
        if ($conversations !== null && $this->usage->thisMonth('conversations_started') >= $conversations) {
            return true;
        }
        $this->usage->increment('conversations_started');

        return false;
    }

    /** @param array<string, mixed> $usage */
    private function meter(Conversation $conversation, string $model, array $usage): int
    {
        $cost = Cost::microUsd($model, $usage);
        $this->usage->increment('ai_requests');
        $this->usage->increment('ai_input_tokens', (int) ($usage['input_tokens'] ?? 0) + (int) ($usage['cache_read_input_tokens'] ?? 0) + (int) ($usage['cache_creation_input_tokens'] ?? 0));
        $this->usage->increment('ai_output_tokens', (int) ($usage['output_tokens'] ?? 0));
        $this->usage->increment('ai_cost_micro_usd', $cost);
        $conversation->increment('ai_cost_micro_usd', $cost);

        return $cost;
    }

    /** @return list<array<string, mixed>> */
    private function history(Conversation $conversation): array
    {
        return AiTurn::query()->where('conversation_id', $conversation->getKey())->orderBy('id')->get()
            ->map(fn (AiTurn $t) => $t->role === 'assistant'
                ? ['role' => 'assistant', 'raw' => $t->payload]
                : ['role' => 'user', 'content' => $t->payload['content']])
            ->all();
    }

    /** @param array<string, mixed> $payload */
    private function turn(Conversation $conversation, string $role, array $payload): void
    {
        AiTurn::create(['conversation_id' => $conversation->getKey(), 'role' => $role, 'payload' => $payload]);
    }

    private function message(Conversation $conversation, SenderType $sender, string $content, array $metadata = []): void
    {
        $inbound = $sender === SenderType::Contact;
        Message::create([
            'conversation_id' => $conversation->getKey(),
            'direction' => $inbound ? 'inbound' : 'outbound',
            'sender_type' => $sender,
            'channel' => $conversation->channel ?? Channel::Web,
            'content' => $content,
            'status' => $inbound ? MessageStatus::Received : MessageStatus::Sent,
            'metadata' => $metadata ?: null,
            'sent_at' => now(),
        ]);
    }

    private function finish(Conversation $conversation, AgentReply $reply): AgentReply
    {
        $this->message($conversation, $reply->answeredByAi() ? SenderType::Ai : SenderType::System, $reply->text, array_filter([
            'status' => $reply->status,
            'tools' => array_map(fn (ToolExecution $e) => $e->id, $reply->tools),
            'cost_micro_usd' => $reply->costMicroUsd,
        ]));
        $conversation->forceFill(['last_message_at' => now()])->save();
        if ($reply->answeredByAi()) {
            $this->usage->increment('ai_messages');
        }

        return $reply;
    }

    private function contactLine(Conversation $conversation): string
    {
        $line = trim((string) ($conversation->agent?->system_configuration['contact_line'] ?? ''));

        return $line !== '' ? "Ne poți contacta direct: {$line}." : 'Te rugăm să contactezi direct firma.';
    }
}
