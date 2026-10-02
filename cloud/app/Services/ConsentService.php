<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\IdentityType;
use App\Models\Contact;
use App\Models\ContactConsent;
use App\Models\Suppression;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Consimțământul contactelor, ca istoric de evenimente (cine, când, prin ce sursă, pentru ce scop).
 * Retragerea consimțământului de marketing trece automat adresa pe lista de suprimări a canalului.
 */
final class ConsentService
{
    public function __construct(
        private readonly EventRecorder $events,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, scalar|null> $metadata */
    public function record(
        Contact $contact,
        Channel $channel,
        ConsentPurpose $purpose,
        ConsentStatus $status,
        string $source,
        array $metadata = [],
        ?string $ip = null,
        ?string $userAgent = null,
        ?User $recordedBy = null,
        ?CarbonInterface $occurredAt = null,
    ): ContactConsent {
        if (! in_array($channel, Channel::consentChannels(), true)) {
            throw new InvalidArgumentException("Canal fără consimțământ: {$channel->value}");
        }

        $wasGranted = $purpose === ConsentPurpose::Marketing && $this->current($contact, $channel, $purpose) === ConsentStatus::Granted;
        $consent = DB::transaction(function () use ($contact, $channel, $purpose, $status, $source, $metadata, $ip, $userAgent, $recordedBy, $occurredAt): ContactConsent {
            $consent = ContactConsent::create([
                'contact_id' => $contact->getKey(),
                'channel' => $channel,
                'purpose' => $purpose,
                'status' => $status,
                'source' => $source,
                'ip_address' => $ip,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
                'metadata' => $metadata ?: null,
                'recorded_by_user_id' => $recordedBy?->getKey(),
                'occurred_at' => $occurredAt ?? now(),
            ]);

            if ($purpose === ConsentPurpose::Marketing) {
                foreach ($this->valuesFor($contact, $channel) as $value) {
                    $hash = Suppression::hash($value);
                    if ($status === ConsentStatus::Revoked) {
                        Suppression::suppress($channel, $value, 'unsubscribed', $source);
                    } elseif ($status === ConsentStatus::Granted) {
                        // un nou acord explicit ridică doar dezabonarea; bounce-urile și reclamațiile rămân
                        Suppression::query()->where('channel', $channel->value)->where('value_hash', $hash)
                            ->where('reason', 'unsubscribed')->delete();
                    }
                }
            }

            $details = ['channel' => $channel->value, 'purpose' => $purpose->value, 'status' => $status->value, 'source' => $source];
            $this->events->record('consent.changed', $contact, $details);
            $this->audit->record('consent.changed', $contact, $details);

            return $consent;
        });
        if ($purpose === ConsentPurpose::Marketing && $status === ConsentStatus::Granted && ! $wasGranted) {
            // abonare nouă pe canal: intră în activitate și poate porni fluxul „Bun venit”
            app(ContactActivity::class)->record($contact, 'subscribed', ['channel' => $channel->value, 'source' => $source]);
        }

        return $consent;
    }

    public function current(Contact $contact, Channel $channel, ConsentPurpose $purpose): ConsentStatus
    {
        $latest = ContactConsent::query()->where('contact_id', $contact->getKey())
            ->where('channel', $channel->value)->where('purpose', $purpose->value)
            ->orderByDesc('occurred_at')->orderByDesc('id')->first();

        return $latest?->status ?? ConsentStatus::Unknown;
    }

    /** @return array<string, array<string, ConsentStatus>> [canal][scop] => status */
    public function matrix(Contact $contact): array
    {
        $out = [];
        foreach (Channel::consentChannels() as $channel) {
            foreach (ConsentPurpose::cases() as $purpose) {
                $out[$channel->value][$purpose->value] = ConsentStatus::Unknown;
            }
        }
        $rows = ContactConsent::query()->where('contact_id', $contact->getKey())->orderBy('occurred_at')->orderBy('id')->get();
        foreach ($rows as $row) {
            $out[$row->channel->value][$row->purpose->value] = $row->status;
        }

        return $out;
    }

    /** Adresele normalizate ale contactului pe canalul dat. @return list<string> */
    public function valuesFor(Contact $contact, Channel $channel): array
    {
        $types = match ($channel) {
            Channel::Email => [IdentityType::Email],
            Channel::Sms, Channel::Phone => [IdentityType::Phone],
            Channel::WhatsApp => [IdentityType::WhatsApp, IdentityType::Phone],
            default => [],
        };
        if ($types === []) {
            return [];
        }

        return $contact->identities()->whereIn('type', array_map(fn ($t) => $t->value, $types))
            ->pluck('normalized_value')->unique()->values()->all();
    }
}
