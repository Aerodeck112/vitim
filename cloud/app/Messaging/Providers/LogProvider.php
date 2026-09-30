<?php

declare(strict_types=1);

namespace App\Messaging\Providers;

use App\Enums\MessageStatus;
use App\Messaging\Contracts\EmailProvider;
use App\Messaging\Contracts\SmsProvider;
use App\Messaging\Contracts\WhatsAppProvider;
use App\Messaging\OutboundMessage;
use App\Messaging\ProviderResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Doar pentru dezvoltare și teste: nu trimite nimic, scrie în log ID-ul mesajului și canalul
 * (fără conținut și fără destinatar). Nu se configurează în producție.
 */
final class LogProvider implements EmailProvider, SmsProvider, WhatsAppProvider
{
    public function name(): string
    {
        return 'log';
    }

    public function send(OutboundMessage $message): ProviderResult
    {
        Log::info('messaging.log_provider', ['message_id' => $message->messageId, 'channel' => $message->channel->value]);

        return new ProviderResult(MessageStatus::Sent, 'log-'.Str::uuid()->toString());
    }

    public function mapStatus(string $providerStatus): MessageStatus
    {
        return MessageStatus::tryFrom($providerStatus) ?? MessageStatus::Failed;
    }
}
