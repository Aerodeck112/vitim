<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Enums\Channel;
use App\Messaging\Contracts\EmailProvider;
use App\Messaging\Contracts\MessageProvider;
use App\Messaging\Contracts\SmsProvider;
use App\Messaging\Contracts\WhatsAppProvider;
use InvalidArgumentException;

/** Alege adaptorul configurat pentru un canal (config/messaging.php). Null = niciun furnizor activ. */
final class ProviderRegistry
{
    private const CONTRACTS = [
        'email' => EmailProvider::class,
        'sms' => SmsProvider::class,
        'whatsapp' => WhatsAppProvider::class,
    ];

    public function for(Channel $channel): ?MessageProvider
    {
        $contract = self::CONTRACTS[$channel->value] ?? null;
        $name = config("messaging.channels.{$channel->value}");
        if ($contract === null || ! $name) {
            return null;
        }
        $class = config("messaging.providers.{$name}");
        if (! is_string($class) || ! is_subclass_of($class, $contract)) {
            throw new InvalidArgumentException("Furnizorul „{$name}” nu implementează {$contract}.");
        }

        return app($class);
    }
}
