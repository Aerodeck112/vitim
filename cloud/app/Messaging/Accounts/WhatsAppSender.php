<?php

declare(strict_types=1);

namespace App\Messaging\Accounts;

use App\Enums\MessageStatus;
use App\Messaging\OutboundMessage;
use App\Messaging\ProviderResult;
use App\Models\ChannelAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * WhatsApp prin API-ul oficial Meta (Cloud API). Mesajele de marketing pleacă doar ca șabloane aprobate de Meta;
 * contul are nevoie de ID-ul numărului de telefon și de un token permanent (System User din Business Manager).
 */
final class WhatsAppSender implements AccountSender
{
    public function send(ChannelAccount $account, OutboundMessage $message): ProviderResult
    {
        $template = (array) ($message->metadata['template'] ?? []);
        if (($template['name'] ?? '') === '') {
            return new ProviderResult(MessageStatus::Failed, null, 'Lipsește numele șablonului WhatsApp aprobat de Meta.');
        }
        $parameters = array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], array_values((array) ($template['variables'] ?? [])));
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => ltrim($message->to, '+'),
            'type' => 'template',
            'template' => array_filter([
                'name' => (string) $template['name'],
                'language' => ['code' => (string) ($template['language'] ?? 'ro')],
                'components' => $parameters ? [['type' => 'body', 'parameters' => $parameters]] : null,
            ]),
        ];

        return $this->call($account, 'post', '/messages', $payload, fn (array $json) => $json['messages'][0]['id'] ?? null);
    }

    /** Verifică tokenul și numărul (fără să trimită nimic). */
    public function test(ChannelAccount $account, string $to = ''): ProviderResult
    {
        return $this->call($account, 'get', '?fields=display_phone_number,verified_name', null, fn (array $json) => $json['display_phone_number'] ?? null);
    }

    /** @param (callable(array<string, mixed>): ?string) $id */
    private function call(ChannelAccount $account, string $method, string $path, ?array $payload, callable $id): ProviderResult
    {
        $url = 'https://graph.facebook.com/'.config('vitim.whatsapp_graph_version').'/'.rawurlencode((string) $account->setting('phone_number_id')).$path;
        try {
            $request = Http::timeout(20)->withToken((string) $account->setting('access_token'))->acceptJson();
            $response = $method === 'post' ? $request->post($url, $payload) : $request->get($url);
        } catch (ConnectionException $e) {
            return new ProviderResult(MessageStatus::Failed, null, 'Meta nu răspunde: '.Str::limit($e->getMessage(), 200));
        }
        $json = (array) $response->json();
        if ($response->successful() && ($value = $id($json)) !== null) {
            return new ProviderResult(MessageStatus::Sent, (string) $value);
        }

        return new ProviderResult(MessageStatus::Failed, null, 'WhatsApp: '.Str::limit((string) ($json['error']['message'] ?? 'HTTP '.$response->status()), 280));
    }
}
