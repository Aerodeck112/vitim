<?php

declare(strict_types=1);

namespace App\Messaging\Contracts;

use App\Enums\MessageStatus;
use App\Messaging\OutboundMessage;
use App\Messaging\ProviderResult;

/**
 * Adaptorul unui furnizor concret (Brevo, Twilio, Meta...). Logica de business vorbește doar cu MessagingService,
 * care alege adaptorul din configurare: schimbarea furnizorului nu atinge restul codului.
 */
interface MessageProvider
{
    /** Numele stabil al furnizorului, salvat pe mesaj (ex. „brevo”). */
    public function name(): string;

    public function send(OutboundMessage $message): ProviderResult;

    /** Traduce statusul primit de la furnizor (webhook) în statusul intern VITIM. */
    public function mapStatus(string $providerStatus): MessageStatus;
}
