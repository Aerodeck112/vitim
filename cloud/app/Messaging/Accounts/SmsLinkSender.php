<?php

declare(strict_types=1);

namespace App\Messaging\Accounts;

use App\Enums\Channel;
use App\Enums\MessageStatus;
use App\Messaging\OutboundMessage;
use App\Messaging\ProviderResult;
use App\Models\ChannelAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * SMS prin SMSLink.ro (SMS Gateway, HTTP). Datele de conectare: Connection ID + parola conexiunii, din
 * smslink.ro → SMS Gateway → Configurare. Numerele: 07xxxxxxxx (România) sau 00 + prefixul țării.
 */
final class SmsLinkSender implements AccountSender
{
    public const ENDPOINT = 'https://secure.smslink.ro/sms/gateway/communicate/index.php';

    public function send(ChannelAccount $account, OutboundMessage $message): ProviderResult
    {
        try {
            $response = Http::timeout(20)->asForm()->post(self::ENDPOINT, [
                'connection_id' => (string) $account->setting('connection_id'),
                'password' => (string) $account->setting('password'),
                'to' => self::number($message->to),
                'message' => $message->body,
            ]);
        } catch (ConnectionException $e) {
            return new ProviderResult(MessageStatus::Failed, null, 'SMSLink nu răspunde: '.Str::limit($e->getMessage(), 200));
        }

        return self::parse($response->status(), trim((string) $response->body()));
    }

    public function test(ChannelAccount $account, string $to): ProviderResult
    {
        return $this->send($account, new OutboundMessage(0, Channel::Sms, $to, 'Test VITIM: contul SMS functioneaza.'));
    }

    /**
     * Răspunsul SMSLink e text pe câmpuri separate prin „;” (MESSAGE;… la reușită, ERROR;… la eroare).
     * Orice alt răspuns e tratat ca eroare și afișat așa cum a venit, ca să nu raportăm „trimis” fără certitudine.
     */
    public static function parse(int $status, string $body): ProviderResult
    {
        $parts = array_map('trim', explode(';', $body));
        $kind = strtoupper($parts[0]);
        if ($status === 200 && $kind === 'MESSAGE') {
            $id = null;
            foreach (array_slice($parts, 1) as $part) {
                if (ctype_digit($part) && strlen($part) > 3) {
                    $id = $part;
                    break;
                }
            }

            return new ProviderResult(MessageStatus::Sent, $id);
        }

        return new ProviderResult(MessageStatus::Failed, null, 'SMSLink: '.Str::limit($body !== '' ? $body : 'HTTP '.$status, 280));
    }

    /** +40722… → 0722…; alte țări: 00 + prefix. */
    public static function number(string $e164): string
    {
        $digits = ltrim($e164, '+');

        return str_starts_with($digits, '40') ? '0'.substr($digits, 2) : '00'.$digits;
    }
}
