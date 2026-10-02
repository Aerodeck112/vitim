<?php

declare(strict_types=1);

namespace App\Messaging\Accounts;

use App\Messaging\OutboundMessage;
use App\Messaging\ProviderResult;
use App\Models\ChannelAccount;

/** Trimite prin contul propriu al firmei (SMTP, SMSLink, WhatsApp Cloud API). */
interface AccountSender
{
    public function send(ChannelAccount $account, OutboundMessage $message): ProviderResult;

    /** Verifică datele contului; unde furnizorul nu are o verificare separată, trimite un mesaj de test. */
    public function test(ChannelAccount $account, string $to): ProviderResult;
}
