<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\ConversationStatus;
use App\Models\Agent;
use App\Services\EventRecorder;

/**
 * Semnalează echipei că vizitatorul are nevoie de un om (cerere explicită, reclamație, întrebare la care
 * agentul nu are răspuns). Conversația devine „în așteptare”; agentul continuă să răspundă până la preluare.
 */
final class RequestHumanTool implements Tool
{
    public const REASONS = ['visitor_request', 'complaint', 'no_answer', 'other'];

    public function __construct(private readonly EventRecorder $events) {}

    public function name(): string
    {
        return 'request_human';
    }

    public function definition(Agent $agent): array
    {
        return [
            'name' => 'request_human',
            'description' => 'Anunță echipa firmei că vizitatorul are nevoie de un om: cere explicit asta, are o reclamație sau întreabă ceva la care nu ai răspuns în informațiile firmei. După apel, spune-i că un coleg preia și, dacă nu ai datele lui de contact, cere-i un telefon sau un email.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'reason' => ['type' => 'string', 'enum' => self::REASONS, 'description' => 'Motivul.'],
                    'summary' => ['type' => 'string', 'description' => 'Ce vrea vizitatorul, în 1–3 propoziții, pentru colegul care preia.'],
                ],
                'required' => ['reason', 'summary'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function handle(ToolContext $context, array $input): ToolResult
    {
        $reason = in_array($input['reason'] ?? '', self::REASONS, true) ? $input['reason'] : 'other';
        if ($context->isTest()) {
            return ToolResult::dryRun('Echipa a fost anunțată (conversație de test din panou: nu s-a trimis nicio notificare).');
        }
        $conversation = $context->conversation;
        $conversation->forceFill(['status' => ConversationStatus::Pending])->save();
        $this->events->record('conversation.human_requested', $conversation, [
            'reason' => $reason,
            'summary' => mb_substr(trim((string) ($input['summary'] ?? '')), 0, 1000),
        ]);

        return ToolResult::ok('Echipa a fost anunțată. Un coleg preia conversația cât mai curând.');
    }
}
