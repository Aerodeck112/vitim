<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\ContactSource;
use App\Enums\ConversationStatus;
use App\Enums\IdentityType;
use App\Enums\LeadIntent;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Chatul live: un om din echipa firmei preia conversația de la asistentul AI și răspunde din panou.
 * În modul „human” mesajele vizitatorului nu mai ajung la AI; „ai” îl repune pe asistent să răspundă.
 */
final class LiveChatService
{
    public function __construct(
        private readonly ContactService $contacts,
        private readonly ConsentService $consents,
        private readonly LeadService $leads,
    ) {}

    public function operatorReply(Conversation $conversation, User $user, string $text): Message
    {
        $message = $this->message($conversation, SenderType::Human, $text, $user->getKey());
        $conversation->forceFill([
            'mode' => 'human', 'status' => ConversationStatus::Open, 'closed_at' => null,
            'assigned_to' => $user->getKey(), 'last_message_at' => now(), 'staff_read_at' => now(),
        ])->save();
        Cache::forget($this->typingKey($conversation));

        return $message;
    }

    public function visitorMessage(Conversation $conversation, string $text): Message
    {
        $message = $this->message($conversation, SenderType::Contact, $text);
        $conversation->forceFill(['last_message_at' => now(), 'closed_at' => null]
            + ($conversation->status === ConversationStatus::Closed ? ['status' => ConversationStatus::Open] : []))->save();

        return $message;
    }

    public function takeOver(Conversation $conversation, User $user): void
    {
        $conversation->forceFill(['mode' => 'human', 'assigned_to' => $user->getKey(), 'status' => ConversationStatus::Open, 'closed_at' => null])->save();
    }

    /** Asistentul AI răspunde din nou; cererea de om (dacă exista) se consideră rezolvată. */
    public function release(Conversation $conversation): void
    {
        $this->message($conversation, SenderType::System, 'Conversația a fost preluată din nou de asistentul virtual.');
        $conversation->forceFill(['mode' => 'ai', 'assigned_to' => null, 'status' => ConversationStatus::Open, 'last_message_at' => now()])->save();
    }

    public function close(Conversation $conversation): void
    {
        $this->message($conversation, SenderType::System, 'Conversația a fost încheiată. Ne poți scrie oricând din nou aici.');
        $conversation->forceFill(['mode' => 'ai', 'status' => ConversationStatus::Closed, 'closed_at' => now(), 'last_message_at' => now()])->save();
    }

    public function typing(Conversation $conversation): void
    {
        Cache::put($this->typingKey($conversation), true, 6);
    }

    public function isTyping(Conversation $conversation): bool
    {
        return (bool) Cache::get($this->typingKey($conversation), false);
    }

    /** Vizitatorul lasă numele și emailul din fereastra de chat (cu acordul lui explicit) → contact + lead pentru echipă. */
    public function leaveContact(Conversation $conversation, string $name, string $email, ?string $ip, ?string $userAgent): void
    {
        DB::transaction(function () use ($conversation, $name, $email, $ip, $userAgent): void {
            $contact = $this->contacts->findByIdentity(IdentityType::Email, $email)
                ?? $this->contacts->create(['first_name' => $name ?: null, 'email' => $email], ContactSource::WebsiteAi);
            $this->consents->record($contact, Channel::Email, ConsentPurpose::Service, ConsentStatus::Granted, 'website_chat',
                ['conversation_id' => $conversation->getKey()], $ip, $userAgent);
            $conversation->forceFill(['contact_id' => $contact->getKey()])->save();

            if (! Lead::query()->where('conversation_id', $conversation->getKey())->exists()) {
                $last = Message::query()->where('conversation_id', $conversation->getKey())->where('direction', 'inbound')->latest('id')->value('content');
                $this->leads->create($contact, [
                    'site_id' => $conversation->site_id, 'agent_id' => $conversation->agent_id, 'conversation_id' => $conversation->getKey(),
                    'source' => ContactSource::WebsiteAi->value, 'intent' => LeadIntent::Other->value,
                    'summary' => 'A lăsat emailul în chat ca să primească răspuns.'.($last ? "\n\nUltimul mesaj: ".$last : ''),
                ]);
            }
        });
    }

    /**
     * Mesajele pentru widget (fără metadatele interne).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forVisitor(Conversation $conversation, int $after = 0, bool $withVisitor = true): Collection
    {
        $messages = Message::query()->where('conversation_id', $conversation->getKey())->where('id', '>', $after)
            ->when(! $withVisitor, fn ($q) => $q->where('direction', 'outbound'))->orderBy('id')->limit(100)->get();
        $names = User::query()->whereIn('id', $messages->pluck('sender_user_id')->filter()->unique())->pluck('name', 'id');

        return $messages->map(fn (Message $m) => [
            'id' => $m->id,
            'role' => match (true) {
                $m->direction === 'inbound' => 'visitor',
                $m->sender_type === SenderType::Human => 'operator',
                $m->sender_type === SenderType::System && ($m->metadata['status'] ?? null) === null => 'system',
                default => 'agent',
            },
            'name' => $m->sender_user_id ? self::firstName((string) ($names[$m->sender_user_id] ?? '')) : null,
            'text' => $m->content,
            'at' => $m->created_at?->toIso8601String(),
        ])->values();
    }

    /** În chat apare doar prenumele colegului (fără nume complet sau email). */
    public static function firstName(string $name): string
    {
        return mb_substr(trim(explode(' ', trim($name))[0] ?? ''), 0, 30) ?: 'Echipa';
    }

    private function message(Conversation $conversation, SenderType $sender, string $content, ?int $userId = null): Message
    {
        $inbound = $sender === SenderType::Contact;

        return Message::create([
            'conversation_id' => $conversation->getKey(),
            'direction' => $inbound ? 'inbound' : 'outbound',
            'sender_type' => $sender,
            'sender_user_id' => $userId,
            'channel' => $conversation->channel ?? Channel::Web,
            'content' => $content,
            'status' => $inbound ? MessageStatus::Received : MessageStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    private function typingKey(Conversation $conversation): string
    {
        return 'chat-typing:'.$conversation->getKey();
    }
}
