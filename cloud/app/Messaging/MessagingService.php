<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\IdentityType;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\EventRecorder;
use App\Services\UsageMeter;
use Throwable;

/**
 * Singurul drum pentru mesajele trimise către contacte (1:1). Ordinea e fixă:
 * mesaj „queued” → SendPolicy → adaptorul furnizorului → status intern + eveniment.
 *
 * Trimiterile în masă (campanii) NU trec direct pe aici din AI: vor avea flux DRAFT → PREVIEW → APPROVAL → SEND
 * (vezi docs/VITIM-AI-ARCHITECTURE.md, Marketing). În Phase 1 nu există furnizori reali configurați.
 */
final class MessagingService
{
    public function __construct(
        private readonly SendPolicy $policy,
        private readonly ProviderRegistry $providers,
        private readonly EventRecorder $events,
        private readonly UsageMeter $usage,
    ) {}

    public function send(Conversation $conversation, Channel $channel, ConsentPurpose $purpose, string $body, ?string $subject = null, SenderType $sender = SenderType::System, ?User $user = null): Message
    {
        $contact = $conversation->contact;
        $recipient = $contact ? $this->recipient($contact, $channel) : null;
        $message = $conversation->messages()->create([
            'direction' => 'outbound',
            'sender_type' => $sender,
            'sender_user_id' => $user?->getKey(),
            'channel' => $channel,
            'purpose' => $purpose,
            'content' => $body,
            'status' => MessageStatus::Queued,
            'metadata' => $subject !== null ? ['subject' => $subject] : null,
        ]);

        $decision = $contact
            ? $this->policy->decide($contact, $channel, $purpose, $recipient)
            : SendDecision::deny('Conversația nu are un contact.');
        if (! $decision->allowed) {
            return $this->finish($message, MessageStatus::Cancelled, null, $decision->reason, null);
        }

        $provider = $this->providers->for($channel);
        if ($provider === null) {
            return $this->finish($message, MessageStatus::Failed, null, 'Niciun furnizor configurat pentru acest canal.', null);
        }
        try {
            $result = $provider->send(new OutboundMessage($message->getKey(), $channel, (string) $recipient, $body, $subject));
        } catch (Throwable $e) {
            report($e);
            $result = new ProviderResult(MessageStatus::Failed, null, 'Eroare la furnizor.');
        }
        $this->usage->increment("messages_{$channel->value}");

        return $this->finish($message, $result->status, $result->externalId, $result->error, $provider->name());
    }

    private function finish(Message $message, MessageStatus $status, ?string $externalId, ?string $error, ?string $provider): Message
    {
        $failed = in_array($status, [MessageStatus::Failed, MessageStatus::Bounced, MessageStatus::Cancelled], true);
        $message->update([
            'status' => $status,
            'provider' => $provider,
            'external_message_id' => $externalId,
            'error' => $error,
            'sent_at' => $failed ? null : now(),
            'failed_at' => $failed ? now() : null,
        ]);
        $message->conversation->update(['last_message_at' => now()]);
        $this->events->record('message.'.$status->value, $message, ['channel' => $message->channel->value, 'purpose' => $message->purpose?->value]);

        return $message;
    }

    private function recipient(Contact $contact, Channel $channel): ?string
    {
        $type = match ($channel) {
            Channel::Email => IdentityType::Email,
            Channel::Sms => IdentityType::Phone,
            Channel::WhatsApp => IdentityType::WhatsApp,
            default => null,
        };
        if ($type === null) {
            return null;
        }
        $identity = $contact->identities()->where('type', $type->value)->orderByDesc('is_primary')->first()
            ?? ($channel === Channel::WhatsApp ? $contact->identities()->where('type', IdentityType::Phone->value)->orderByDesc('is_primary')->first() : null);

        return $identity?->normalized_value;
    }
}
