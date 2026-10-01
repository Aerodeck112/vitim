<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Ai\AgentRuntime;
use App\Enums\Channel;
use App\Enums\ConversationStatus;
use App\Models\Agent;
use App\Models\Conversation;
use App\Services\UsageMeter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Conversații de test cu agentul, din panou. Răspunsurile sunt reale (consumă din plafonul de cost),
 * dar tool-urile rulează în mod test: nu creează lead-uri și nu trimit notificări.
 */
final class AgentTestController extends PortalController
{
    public function show(Request $request, UsageMeter $usage, int $agent): View
    {
        $model = Agent::query()->findOrFail($agent);
        $conversation = $request->integer('c') ? $this->conversation($model, $request->integer('c')) : null;
        $cap = $this->organization()->subscription?->limit('ai_cost_cap_usd');

        return view('portal.agents.test', [
            'organization' => $this->organization(),
            'agent' => $model,
            'conversation' => $conversation,
            'messages' => $conversation?->messages()->orderBy('id')->get() ?? collect(),
            'executions' => $conversation?->toolExecutions()->get()->keyBy('id') ?? collect(),
            'spentUsd' => $usage->thisMonth('ai_cost_micro_usd') / 1_000_000,
            'capUsd' => $cap,
            'configured' => (string) config('vitim.ai.api_key') !== '',
        ]);
    }

    public function send(Request $request, AgentRuntime $runtime, int $agent): RedirectResponse
    {
        $model = Agent::query()->findOrFail($agent);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:'.config('vitim.ai.max_message_chars')],
            'conversation_id' => ['nullable', 'integer'],
        ]);
        $conversation = ! empty($data['conversation_id'])
            ? $this->conversation($model, (int) $data['conversation_id'])
            : Conversation::create([
                'agent_id' => $model->id,
                'site_id' => $model->site_id,
                'channel' => Channel::Web,
                'status' => ConversationStatus::Open,
                'mode' => 'ai',
                'is_test' => true,
                'subject' => 'Test din panou',
            ]);

        $runtime->reply($conversation, $data['message'], $request->ip(), $request->userAgent());

        return redirect()->route('portal.agents.test', ['organization' => $this->organization()->slug, 'agent' => $model->id, 'c' => $conversation->id]);
    }

    /** Doar conversațiile de test ale acestui agent (și, prin scope, ale acestei firme). */
    private function conversation(Agent $agent, int $id): Conversation
    {
        return Conversation::query()->where('agent_id', $agent->id)->where('is_test', true)->findOrFail($id);
    }
}
