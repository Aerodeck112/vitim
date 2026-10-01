<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Conversațiile vizitatorilor cu agentul (fără cele de test din panou). Doar citire în această versiune. */
final class ConversationController extends PortalController
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['open', 'pending', 'closed'], true) ? $request->query('status') : null;

        return view('portal.conversations.index', [
            'organization' => $this->organization(),
            'conversations' => Conversation::query()->where('is_test', false)->with(['site', 'contact', 'lead'])
                ->withCount('messages')->when($status, fn ($q) => $q->where('status', $status))
                ->orderByDesc('last_message_at')->paginate(30)->withQueryString(),
            'status' => $status,
            'pending' => Conversation::query()->where('is_test', false)->where('status', 'pending')->count(),
        ]);
    }

    public function show(int $conversation): View
    {
        $model = Conversation::query()->where('is_test', false)->with(['site', 'contact', 'lead', 'agent'])->findOrFail($conversation);

        return view('portal.conversations.show', [
            'organization' => $this->organization(),
            'conversation' => $model,
            'messages' => $model->messages()->orderBy('id')->get(),
            'executions' => $model->toolExecutions()->get()->keyBy('id'),
        ]);
    }
}
