<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Channel;
use App\Enums\MessageStatus;
use App\Models\ChannelAccount;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Conturile de trimitere ale firmei. Secretele (parole, tokenuri) se salvează criptat și nu se mai afișează:
 * un câmp lăsat gol la editare păstrează valoarea veche.
 */
final class ChannelAccountService
{
    /** câmp => [etichetă, secret, obligatoriu] */
    public const FIELDS = [
        'email' => [
            'host' => ['Server SMTP (ex. mail.firma.ro)', false, true],
            'port' => ['Port (465 SSL sau 587 TLS)', false, true],
            'encryption' => ['Criptare', false, true],
            'username' => ['Utilizator (adresa de email)', false, true],
            'password' => ['Parola emailului', true, true],
            'from_email' => ['Adresa expeditorului', false, true],
            'from_name' => ['Numele expeditorului', false, false],
            'reply_to' => ['Răspunsurile vin la (opțional)', false, false],
        ],
        'sms' => [
            'connection_id' => ['Connection ID (SMSLink → SMS Gateway)', false, true],
            'password' => ['Parola conexiunii', true, true],
        ],
        'whatsapp' => [
            'phone_number_id' => ['Phone number ID (Meta → WhatsApp → API Setup)', false, true],
            'business_account_id' => ['WhatsApp Business Account ID', false, false],
            'access_token' => ['Token permanent (System User)', true, true],
            'app_secret' => ['App Secret (pentru webhook)', true, false],
        ],
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $input */
    public function save(Channel $channel, array $input): ChannelAccount
    {
        $fields = self::FIELDS[$channel->value];
        $account = ChannelAccount::query()->firstOrNew(['channel' => $channel->value]);
        $config = (array) ($account->exists ? $account->config : []);
        foreach ($fields as $key => [$label, $secret, $required]) {
            $value = trim((string) ($input[$key] ?? ''));
            if ($secret && $value === '') {
                $value = (string) ($config[$key] ?? ''); // gol = păstrează secretul salvat
            }
            if ($required && $value === '') {
                throw ValidationException::withMessages([$key => "Completează „{$label}”."]);
            }
            $config[$key] = mb_substr($value, 0, 500);
        }
        if ($channel === Channel::Email) {
            foreach (['from_email', 'username', 'reply_to'] as $key) {
                if ($config[$key] !== '' && $key !== 'username' && IdentityNormalizer::email($config[$key]) === null) {
                    throw ValidationException::withMessages([$key => 'Adresa nu e validă.']);
                }
            }
            $config['port'] = (string) (int) $config['port'];
            $config['encryption'] = in_array($config['encryption'], ['ssl', 'tls'], true) ? $config['encryption'] : 'tls';
        }
        if ($channel === Channel::Sms) {
            $config['ascii'] = ! empty($input['ascii']);
        }
        if ($channel === Channel::WhatsApp) {
            $config['verify_token'] = (string) ($config['verify_token'] ?? '') ?: Str::random(32);
        }
        $account->fill([
            'provider' => ChannelAccount::PROVIDERS[$channel->value],
            'config' => $config,
            'status' => 'untested',
            'hourly_limit' => $channel === Channel::Email ? max(10, min(5000, (int) ($input['hourly_limit'] ?? 100))) : null,
        ]);
        if ($channel === Channel::WhatsApp && ! $account->webhook_token) {
            $account->webhook_token = Str::random(64);
        }
        $account->save();
        $this->audit->record('channel_account.saved', $account, ['channel' => $channel->value]);

        return $account;
    }

    public function test(ChannelAccount $account, string $to): bool
    {
        $channel = Channel::from($account->channel);
        if ($channel !== Channel::WhatsApp) {
            $to = $channel === Channel::Email ? (string) IdentityNormalizer::email($to) : (string) IdentityNormalizer::phone($to);
            if ($to === '') {
                throw ValidationException::withMessages(['test_to' => $channel === Channel::Email ? 'Adresa de email nu e validă.' : 'Numărul de telefon nu e valid.']);
            }
        }
        $result = CampaignService::sender($channel)->test($account, $to);
        $ok = $result->status === MessageStatus::Sent;
        $account->forceFill(['status' => $ok ? 'ok' : 'error', 'last_error' => $ok ? null : $result->error, 'tested_at' => now()])->save();
        $this->audit->record('channel_account.tested', $account, ['ok' => $ok]);

        return $ok;
    }

    public function delete(ChannelAccount $account): void
    {
        $this->audit->record('channel_account.deleted', $account, ['channel' => $account->channel]);
        $account->delete();
    }
}
