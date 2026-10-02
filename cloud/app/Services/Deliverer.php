<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\MessageStatus;
use App\Messaging\MessagingService;
use App\Messaging\OutboundMessage;
use App\Messaging\SendPolicy;
use App\Models\CampaignRecipient;
use App\Models\ChannelAccount;
use App\Models\Contact;
use App\Tenancy\TenantContext;

/**
 * Trimiterea unui mesaj de marketing către un contact (din campanii și din automatizări): verifică din nou
 * acordul, randează (cu urmărire), trimite prin contul firmei, salvează rezultatul și activitatea contactului.
 */
final class Deliverer
{
    public function __construct(
        private readonly SendPolicy $policy,
        private readonly MessagingService $messaging,
        private readonly CampaignRenderer $renderer,
        private readonly ContactActivity $activity,
        private readonly UsageMeter $usage,
        private readonly TenantContext $context,
    ) {}

    /** @return array{0: bool, 1: ?string, 2: ?string} [poate primi, adresa, motivul excluderii] */
    public function check(Contact $contact, Channel $channel): array
    {
        $address = $this->messaging->recipientFor($contact, $channel);
        $decision = $this->policy->decide($contact, $channel, ConsentPurpose::Marketing, $address);

        return [$decision->allowed, $address, $decision->allowed ? null : $decision->reason];
    }

    /** Trimite către destinatarul pregătit (status pending). Întoarce true dacă a plecat. @param array<string, string> $extra variabile suplimentare */
    public function deliver(CampaignRecipient $recipient, MessageContent $content, ChannelAccount $account, array $extra = []): bool
    {
        $contact = $recipient->contact;
        [$ok, $address, $reason] = $contact ? $this->check($contact, $content->channel) : [false, null, 'Contact șters'];
        if (! $ok) {
            $recipient->forceFill(['status' => 'excluded', 'reason' => $reason])->save();

            return false;
        }
        $unsubscribe = route('unsubscribe', $recipient->unsubscribe_code);
        $rendered = $this->renderer->render($content, $this->context->organization(), $contact, $unsubscribe, (bool) $account->setting('ascii', true),
            $content->channel === Channel::Email ? $recipient->unsubscribe_code : null, $extra);
        $result = CampaignService::sender($content->channel)->send($account, new OutboundMessage(0, $content->channel, (string) $address, $rendered['body'], $rendered['subject'],
            array_filter(['html' => $rendered['html'], 'template' => $rendered['template'], 'unsubscribe_url' => $unsubscribe])));
        $sent = $result->status === MessageStatus::Sent;
        $recipient->forceFill([
            'address' => $address, 'status' => $sent ? 'sent' : 'failed', 'reason' => $sent ? null : $result->error,
            'external_id' => $result->externalId, 'sent_at' => now(),
        ])->save();
        if ($sent) {
            $this->usage->increment('campaign_'.$content->channel->value);
            $this->activity->record($contact, $content->channel->value.'_sent', array_filter(['subject' => $rendered['subject']]), null,
                $recipient->campaign_id, $recipient->flow_id ?? null, $recipient->id);
        }

        return $sent;
    }
}
