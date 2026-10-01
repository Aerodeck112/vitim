<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\LiveChatService;
use App\Support\ChatText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Inboxul firmei: conversațiile de pe site (fără cele de test) și chatul live cu vizitatorii. */
final class ConversationController extends PortalController
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['open', 'pending', 'closed', 'live'], true) ? $request->query('status') : null;

        return view('portal.conversations.index', [
            'organization' => $this->organization(),
            'conversations' => Conversation::query()->where('is_test', false)->with(['site', 'contact', 'lead'])
                ->withCount('messages')
                ->addSelect(['last_inbound_at' => Message::query()->select(DB::raw('max(created_at)'))->whereColumn('conversation_id', 'conversations.id')->where('direction', 'inbound')])
                ->when($status === 'live', fn ($q) => $q->where('mode', 'human')->where('status', '!=', 'closed'))
                ->when($status && $status !== 'live', fn ($q) => $q->where('status', $status))
                ->orderByDesc('last_message_at')->paginate(30)->withQueryString(),
            'status' => $status,
            'pending' => Conversation::query()->where('is_test', false)->where('status', 'pending')->count(),
            'online' => Conversation::query()->where('is_test', false)->where('visitor_seen_at', '>', now()->subSeconds(40))->count(),
        ]);
    }

    public function show(int $conversation): View
    {
        $model = $this->find($conversation)->load(['site', 'contact', 'lead', 'agent']);
        $model->forceFill(['staff_read_at' => now()])->saveQuietly();

        return view('portal.conversations.show', [
            'organization' => $this->organization(),
            'conversation' => $model,
            'messages' => $model->messages()->orderBy('id')->get(),
            'executions' => $model->toolExecutions()->get()->keyBy('id'),
        ]);
    }

    /** Mesajele noi pentru pagina deschisă (verificată la câteva secunde), deja escapate pentru afișare. */
    public function poll(Request $request, int $conversation): JsonResponse
    {
        $model = $this->find($conversation);
        $after = max(0, (int) $request->query('after', 0));
        $messages = $model->messages()->where('id', '>', $after)->orderBy('id')->limit(100)->get();
        if ($messages->contains('direction', 'inbound')) {
            $model->forceFill(['staff_read_at' => now()])->saveQuietly();
        }

        return response()->json([
            'messages' => $messages->map(fn (Message $m) => [
                'id' => $m->id,
                'class' => self::bubble($m),
                'html' => (string) ($m->direction === 'inbound' ? e($m->content) : ChatText::render($m->content)),
                'at' => $m->created_at?->format('H:i'),
                'inbound' => $m->direction === 'inbound',
            ])->values(),
            'visitor_online' => $model->visitorOnline(),
            'live' => $model->isLive(),
            'status' => $model->status->value,
        ]);
    }

    public function reply(Request $request, LiveChatService $live, int $conversation): JsonResponse|RedirectResponse
    {
        $model = $this->find($conversation);
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $message = $live->operatorReply($model, $request->user(), trim($data['message']));

        return $request->expectsJson()
            ? response()->json(['id' => $message->id])
            : $this->to('portal.conversations.show', ['conversation' => $model->id], 'Mesaj trimis.');
    }

    public function action(Request $request, LiveChatService $live, int $conversation): RedirectResponse
    {
        $model = $this->find($conversation);
        $action = $request->validate(['action' => ['required', 'in:take,release,close']])['action'];
        match ($action) {
            'take' => $live->takeOver($model, $request->user()),
            'release' => $live->release($model),
            'close' => $live->close($model),
        };

        return $this->to('portal.conversations.show', ['conversation' => $model->id], match ($action) {
            'take' => 'Ai preluat conversația. Asistentul AI nu mai răspunde până o predai înapoi.',
            'release' => 'Asistentul AI răspunde din nou în această conversație.',
            'close' => 'Conversația a fost încheiată.',
        });
    }

    public function typing(LiveChatService $live, int $conversation): JsonResponse
    {
        $live->typing($this->find($conversation));

        return response()->json(['ok' => true]);
    }

    public static function bubble(Message $m): string
    {
        return match (true) {
            $m->direction === 'inbound' => 'me',
            $m->sender_type === SenderType::Human => 'op',
            $m->sender_type === SenderType::System => 'sys',
            default => 'ai',
        };
    }

    private function find(int $id): Conversation
    {
        return Conversation::query()->where('is_test', false)->findOrFail($id);
    }
}
