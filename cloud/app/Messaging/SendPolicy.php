<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Models\Contact;
use App\Models\Suppression;
use App\Services\ConsentService;

/**
 * Poate primi contactul acest mesaj? Fail-safe: fără certitudine, NU se trimite.
 *
 * - bounce / reclamație pe adresă: nimic pe acel canal;
 * - marketing: doar cu consimțământ ACORDAT pentru (canal, marketing) și fără nicio suprimare;
 *   „necunoscut” = refuz;
 * - tranzacțional / serviciu: permis, dacă scopul nu a fost retras explicit și adresa nu are bounce/reclamație.
 *   Baza legală pentru aceste mesaje rămâne responsabilitatea firmei (documentat în VITIM-AI-SECURITY.md).
 */
final class SendPolicy
{
    public function __construct(private readonly ConsentService $consents) {}

    public function decide(Contact $contact, Channel $channel, ConsentPurpose $purpose, ?string $recipient): SendDecision
    {
        if ($recipient === null || $recipient === '') {
            return SendDecision::deny('Contactul nu are o adresă pentru acest canal.');
        }
        $suppression = Suppression::query()->where('channel', $channel->value)
            ->where('value_hash', Suppression::hash($recipient))->first();
        if ($suppression && in_array($suppression->reason, Suppression::HARD_REASONS, true)) {
            return SendDecision::deny("Adresă suprimată ({$suppression->reason}).");
        }

        $consentChannel = $channel === Channel::WhatsApp ? Channel::WhatsApp : ($channel === Channel::Email ? Channel::Email : Channel::Sms);
        $status = $this->consents->current($contact, $consentChannel, $purpose);

        if ($purpose === ConsentPurpose::Marketing) {
            if ($suppression) {
                return SendDecision::deny("Adresă suprimată ({$suppression->reason}).");
            }

            return $status === ConsentStatus::Granted
                ? SendDecision::allow()
                : SendDecision::deny('Fără consimțământ de marketing pe acest canal.');
        }

        return $status === ConsentStatus::Revoked
            ? SendDecision::deny('Contactul a retras acordul pentru acest tip de mesaje.')
            : SendDecision::allow();
    }
}
