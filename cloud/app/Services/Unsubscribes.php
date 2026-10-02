<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\IdentityType;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Suppression;

/**
 * Dezabonarea de la marketing: acordul se retrage (rămâne în istoricul de consimțământ ca dovadă), iar adresa
 * intră în lista de suprimare, deci nicio campanie viitoare nu o mai atinge, chiar dacă contactul e importat din nou.
 */
final class Unsubscribes
{
    public function __construct(private readonly ConsentService $consents, private readonly ContactService $contacts) {}

    public function byRecipient(CampaignRecipient $recipient, string $source, ?string $ip = null, ?string $userAgent = null): void
    {
        $campaign = $recipient->campaign;
        $contact = $recipient->contact;
        if ($contact) {
            $this->revoke($contact, $campaign->channel, $source, ['campaign_id' => $campaign->id], $ip, $userAgent);
        }
        if ($recipient->address) {
            Suppression::suppress($campaign->channel, $recipient->address, 'unsubscribed', $source);
        }
        $recipient->forceFill(['status' => 'unsubscribed'])->save();
    }

    public function byPhone(string $phone, Channel $channel, string $source): void
    {
        $normalized = IdentityNormalizer::phone($phone);
        if ($normalized === null) {
            return;
        }
        Suppression::suppress($channel, $normalized, 'unsubscribed', $source);
        $contact = $this->contacts->findByIdentity(IdentityType::WhatsApp, $normalized) ?? $this->contacts->findByIdentity(IdentityType::Phone, $normalized);
        if ($contact) {
            $this->revoke($contact, $channel, $source, []);
        }
    }

    private function revoke(Contact $contact, Channel $channel, string $source, array $metadata, ?string $ip = null, ?string $userAgent = null): void
    {
        $this->consents->record($contact, $channel, ConsentPurpose::Marketing, ConsentStatus::Revoked, $source, $metadata, $ip, $userAgent);
    }
}
