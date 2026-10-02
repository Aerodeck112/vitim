<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\MessageStatus;
use App\Messaging\Accounts\AccountSender;
use App\Messaging\Accounts\SmsLinkSender;
use App\Messaging\Accounts\SmtpSender;
use App\Messaging\Accounts\WhatsAppSender;
use App\Messaging\MessagingService;
use App\Messaging\OutboundMessage;
use App\Messaging\ProviderResult;
use App\Messaging\SendPolicy;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\ChannelAccount;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Campaniile: publicul (doar contactele cu acord de marketing pe canal), lista de destinatari fixată la pornire,
 * trimiterea în tranșe (limita pe oră a contului de email), testul către propria adresă și jurnalul complet.
 */
final class CampaignService
{
    /** Câte mesaje pleacă la o rulare (în fiecare minut). */
    private const PER_RUN = ['email' => 40, 'sms' => 60, 'whatsapp' => 60];

    public function __construct(
        private readonly SendPolicy $policy,
        private readonly MessagingService $messaging,
        private readonly CampaignRenderer $renderer,
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
        private readonly UsageMeter $usage,
    ) {}

    public static function sender(Channel $channel): AccountSender
    {
        return app(match ($channel) {
            Channel::Email => SmtpSender::class,
            Channel::Sms => SmsLinkSender::class,
            default => WhatsAppSender::class,
        });
    }

    public function account(Channel $channel): ?ChannelAccount
    {
        return ChannelAccount::query()->where('channel', $channel->value)->first();
    }

    /** Contactele care corespund filtrelor (înainte de verificarea acordului). @return Builder<Contact> */
    public function audienceQuery(Campaign $campaign): Builder
    {
        $a = (array) ($campaign->audience ?? []);

        return Contact::query()
            ->when(! empty($a['sources']), fn ($q) => $q->whereIn('source', (array) $a['sources']))
            ->when(($a['leads'] ?? '') === 'with', fn ($q) => $q->whereIn('id', Lead::query()->select('contact_id')))
            ->when(($a['leads'] ?? '') === 'without', fn ($q) => $q->whereNotIn('id', Lead::query()->select('contact_id')))
            ->when(! empty($a['created_after']), fn ($q) => $q->where('created_at', '>=', $a['created_after']));
    }

    /** Câți primesc și câți sunt excluși (și de ce), fără să salveze nimic. @return array{eligible: int, excluded: array<string, int>} */
    public function estimate(Campaign $campaign): array
    {
        $eligible = 0;
        $excluded = [];
        $this->audienceQuery($campaign)->chunkById(500, function ($contacts) use ($campaign, &$eligible, &$excluded): void {
            foreach ($contacts as $contact) {
                [$ok, , $reason] = $this->check($contact, $campaign->channel);
                $ok ? $eligible++ : $excluded[$reason] = ($excluded[$reason] ?? 0) + 1;
            }
        });

        return ['eligible' => $eligible, 'excluded' => $excluded];
    }

    /** Trimite o probă la adresa celui care pregătește campania (fără liste, fără acord: e propria lui adresă). */
    public function sendTest(Campaign $campaign, string $to, User $user): ProviderResult
    {
        $account = $this->requireAccount($campaign->channel);
        $address = $campaign->channel === Channel::Email ? IdentityNormalizer::email($to) : IdentityNormalizer::phone($to);
        if ($address === null) {
            throw ValidationException::withMessages(['test_to' => $campaign->channel === Channel::Email ? 'Adresa de email nu e validă.' : 'Numărul de telefon nu e valid.']);
        }
        $sample = new Contact(['first_name' => Str::before(trim($user->name), ' '), 'last_name' => Str::after(trim($user->name), ' ')]);
        $rendered = $this->renderer->render($campaign, $this->context->organization(), $sample, route('unsubscribe', 'TEST'), (bool) $account->setting('ascii', true));
        $result = self::sender($campaign->channel)->send($account, $this->outbound($campaign, $address, $rendered, $rendered['subject'] ? '[TEST] '.$rendered['subject'] : null));
        $this->audit->record('campaign.test_sent', $campaign, ['status' => $result->status->value]);

        return $result;
    }

    /** Aprobare: lista de destinatari se fixează acum (cu motivele excluderilor), apoi pleacă imediat sau la ora aleasă. */
    public function approve(Campaign $campaign, User $user, ?\DateTimeInterface $at = null): int
    {
        if (! $campaign->editable()) {
            throw ValidationException::withMessages(['campaign' => 'Campania a fost deja aprobată.']);
        }
        $this->requireAccount($campaign->channel);
        $this->validateContent($campaign);

        $eligible = DB::transaction(function () use ($campaign): int {
            $eligible = 0;
            $this->audienceQuery($campaign)->chunkById(500, function ($contacts) use ($campaign, &$eligible): void {
                foreach ($contacts as $contact) {
                    [$ok, $address, $reason] = $this->check($contact, $campaign->channel);
                    CampaignRecipient::create([
                        'campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'address' => $address,
                        'status' => $ok ? 'pending' : 'excluded', 'reason' => $ok ? null : $reason,
                        'unsubscribe_code' => $ok ? Str::random(10) : null,
                    ]);
                    $eligible += $ok ? 1 : 0;
                }
            });

            return $eligible;
        });
        if ($eligible === 0) {
            CampaignRecipient::query()->where('campaign_id', $campaign->id)->delete();
            throw ValidationException::withMessages(['campaign' => 'Niciun contact nu poate primi campania: nu există contacte cu acord de marketing pe acest canal (vezi Contacte → import cu acord).']);
        }
        $campaign->forceFill([
            'status' => 'scheduled', 'approved_by' => $user->id, 'approved_at' => now(),
            'scheduled_at' => $at ?? now(), 'last_error' => null,
        ])->save();
        $this->audit->record('campaign.approved', $campaign, ['recipients' => $eligible, 'scheduled_at' => $campaign->scheduled_at->toIso8601String()]);

        return $eligible;
    }

    public function pause(Campaign $campaign): void
    {
        if (in_array($campaign->status, ['scheduled', 'sending'], true)) {
            $campaign->forceFill(['status' => 'paused'])->save();
            $this->audit->record('campaign.paused', $campaign);
        }
    }

    public function resume(Campaign $campaign): void
    {
        if ($campaign->status === 'paused') {
            $campaign->forceFill(['status' => 'sending', 'last_error' => null])->save();
            $this->audit->record('campaign.resumed', $campaign);
        }
    }

    public function cancel(Campaign $campaign): void
    {
        if (in_array($campaign->status, ['draft', 'scheduled', 'paused'], true)) {
            CampaignRecipient::query()->where('campaign_id', $campaign->id)->where('status', 'pending')->update(['status' => 'excluded', 'reason' => 'Campanie anulată']);
            $campaign->forceFill(['status' => 'cancelled'])->save();
            $this->audit->record('campaign.cancelled', $campaign);
        }
    }

    /** O tranșă (rulată în fiecare minut de vitim:campaigns). Întoarce câte mesaje au plecat. */
    public function process(Campaign $campaign): int
    {
        if ($campaign->status === 'scheduled') {
            if ($campaign->scheduled_at && $campaign->scheduled_at->isFuture()) {
                return 0;
            }
            $campaign->forceFill(['status' => 'sending', 'started_at' => now()])->save();
        }
        if ($campaign->status !== 'sending') {
            return 0;
        }
        $account = $this->account($campaign->channel);
        if (! $account) {
            $campaign->forceFill(['status' => 'paused', 'last_error' => 'Contul de trimitere a fost șters.'])->save();

            return 0;
        }
        $budget = self::PER_RUN[$campaign->channel->value];
        if ($account->hourly_limit) {
            $lastHour = CampaignRecipient::query()->whereIn('campaign_id', Campaign::query()->where('channel', $campaign->channel->value)->select('id'))
                ->where('sent_at', '>=', now()->subHour())->count();
            $budget = min($budget, max(0, $account->hourly_limit - $lastHour));
        }
        $sender = self::sender($campaign->channel);
        $organization = $this->context->organization();
        $sent = 0;
        $failedInRow = 0;
        $pending = CampaignRecipient::query()->where('campaign_id', $campaign->id)->where('status', 'pending')->with('contact')->orderBy('id')->limit($budget)->get();
        foreach ($pending as $recipient) {
            // acordul se verifică din nou la trimitere: între timp contactul s-a putut dezabona
            [$ok, $address, $reason] = $recipient->contact ? $this->check($recipient->contact, $campaign->channel) : [false, null, 'Contact șters'];
            if (! $ok) {
                $recipient->forceFill(['status' => 'excluded', 'reason' => $reason])->save();

                continue;
            }
            $rendered = $this->renderer->render($campaign, $organization, $recipient->contact, route('unsubscribe', $recipient->unsubscribe_code), (bool) $account->setting('ascii', true));
            $result = $sender->send($account, $this->outbound($campaign, (string) $address, $rendered, $rendered['subject'], route('unsubscribe', $recipient->unsubscribe_code)));
            $okSent = $result->status === MessageStatus::Sent;
            $recipient->forceFill([
                'status' => $okSent ? 'sent' : 'failed', 'reason' => $okSent ? null : $result->error,
                'external_id' => $result->externalId, 'sent_at' => now(),
            ])->save();
            if ($okSent) {
                $sent++;
                $failedInRow = 0;
                $this->usage->increment('campaign_'.$campaign->channel->value);
            } elseif (++$failedInRow >= 3) {
                // contul nu merge (parolă schimbată, credit epuizat): oprim, nu ardem toată lista
                $campaign->forceFill(['status' => 'paused', 'last_error' => $result->error])->save();
                $this->audit->record('campaign.auto_paused', $campaign, ['error' => Str::limit((string) $result->error, 200)]);

                return $sent;
            }
        }
        if (! CampaignRecipient::query()->where('campaign_id', $campaign->id)->where('status', 'pending')->exists()) {
            $campaign->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
            $this->audit->record('campaign.completed', $campaign, ['stats' => $campaign->stats()]);
        }

        return $sent;
    }

    /** @return array{0: bool, 1: ?string, 2: ?string} [poate primi, adresa, motivul excluderii] */
    private function check(Contact $contact, Channel $channel): array
    {
        $address = $this->messaging->recipientFor($contact, $channel);
        $decision = $this->policy->decide($contact, $channel, ConsentPurpose::Marketing, $address);

        return [$decision->allowed, $address, $decision->allowed ? null : $decision->reason];
    }

    /** @param array{subject: ?string, body: string, html: ?string, template: ?array<string, mixed>} $rendered */
    private function outbound(Campaign $campaign, string $to, array $rendered, ?string $subject, ?string $unsubscribe = null): OutboundMessage
    {
        return new OutboundMessage(0, $campaign->channel, $to, $rendered['body'], $subject, array_filter([
            'html' => $rendered['html'], 'template' => $rendered['template'], 'unsubscribe_url' => $unsubscribe,
        ]));
    }

    private function requireAccount(Channel $channel): ChannelAccount
    {
        $account = $this->account($channel);
        if (! $account) {
            throw ValidationException::withMessages(['campaign' => 'Conectează întâi contul de '.['email' => 'email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'][$channel->value].' în Setări → Canale de trimitere.']);
        }

        return $account;
    }

    private function validateContent(Campaign $campaign): void
    {
        $missing = match ($campaign->channel) {
            Channel::Email => trim((string) $campaign->subject) === '' || trim((string) $campaign->body) === '',
            Channel::Sms => trim((string) $campaign->body) === '',
            default => trim((string) ($campaign->template['name'] ?? '')) === '',
        };
        if ($missing) {
            throw ValidationException::withMessages(['campaign' => $campaign->channel === Channel::WhatsApp ? 'Completează numele șablonului WhatsApp aprobat de Meta.' : 'Completează conținutul mesajului.']);
        }
    }
}
