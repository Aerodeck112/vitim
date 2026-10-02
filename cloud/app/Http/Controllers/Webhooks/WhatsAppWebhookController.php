<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Enums\Channel;
use App\Http\Controllers\Controller;
use App\Models\CampaignRecipient;
use App\Models\ChannelAccount;
use App\Services\Unsubscribes;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Webhook-ul WhatsApp Cloud API al unei firme (adresa conține tokenul contului ei):
 * GET = verificarea Meta (hub.verify_token), POST = statusuri de livrare și răspunsuri „STOP”, semnate cu App Secret.
 */
final class WhatsAppWebhookController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function verify(Request $request, string $token): Response
    {
        $account = $this->account($token);
        $ok = $account && $request->query('hub_mode') === 'subscribe'
            && hash_equals((string) $account->setting('verify_token'), (string) $request->query('hub_verify_token'));

        return $ok ? response((string) $request->query('hub_challenge'), 200) : response('', 403);
    }

    public function receive(Request $request, Unsubscribes $unsubscribes, string $token): Response
    {
        $account = $this->account($token);
        $secret = (string) $account?->setting('app_secret');
        $signature = (string) $request->header('X-Hub-Signature-256');
        if (! $account || $secret === '' || ! hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
            return response('', 403);
        }

        $this->context->runAs($account->organization, function () use ($request, $unsubscribes): void {
            foreach ((array) $request->input('entry', []) as $entry) {
                foreach ((array) ($entry['changes'] ?? []) as $change) {
                    $value = (array) ($change['value'] ?? []);
                    foreach ((array) ($value['statuses'] ?? []) as $status) {
                        $this->status((string) ($status['id'] ?? ''), (string) ($status['status'] ?? ''), (string) ($status['errors'][0]['title'] ?? ''));
                    }
                    foreach ((array) ($value['messages'] ?? []) as $message) {
                        $text = strtolower(trim((string) ($message['text']['body'] ?? $message['button']['text'] ?? '')));
                        if (preg_match('/^(stop|dezabonare|dezabonez|nu mai vreau)\b/u', $text)) {
                            $unsubscribes->byPhone('+'.ltrim((string) ($message['from'] ?? ''), '+'), Channel::WhatsApp, 'whatsapp_stop');
                        }
                    }
                }
            }
        });

        return response('', 200);
    }

    private function status(string $externalId, string $status, string $error): void
    {
        $map = ['sent' => 'sent', 'delivered' => 'delivered', 'read' => 'read', 'failed' => 'failed'];
        if ($externalId === '' || ! isset($map[$status])) {
            return;
        }
        $recipient = CampaignRecipient::query()->where('external_id', $externalId)->first();
        // statusurile nu coboară (citit → livrat) și nu șterg un „dezabonat”
        $rank = ['pending' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 3];
        if ($recipient && ($rank[$recipient->status] ?? 9) < $rank[$map[$status]]) {
            $recipient->forceFill(['status' => $map[$status], 'reason' => $status === 'failed' ? mb_substr($error, 0, 300) : $recipient->reason])->save();
        }
    }

    private function account(string $token): ?ChannelAccount
    {
        // cod de platformă: firma se află din tokenul unic din adresă (64 de caractere), nu din datele cererii
        return strlen($token) === 64 ? ChannelAccount::withoutTenancy()->with('organization')->where('webhook_token', $token)->where('channel', 'whatsapp')->first() : null;
    }
}
