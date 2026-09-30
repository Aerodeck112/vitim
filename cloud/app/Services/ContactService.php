<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContactSource;
use App\Enums\IdentityType;
use App\Models\Contact;
use App\Models\ContactIdentity;
use App\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crearea, modificarea și ștergerea contactelor, cu deduplicare pe identități (email, telefon, WhatsApp, ID extern).
 * Datele de intrare sunt deja validate de controller (tipuri, lungimi).
 */
final class ContactService
{
    private const FIELDS = ['first_name', 'last_name', 'company', 'language', 'status', 'custom_fields'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly EventRecorder $events,
        private readonly UsageMeter $usage,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ContactSource $source): Contact
    {
        $identities = $this->identitiesFrom($data);
        foreach ($identities as $identity) {
            $this->assertIdentityFree($identity);
        }

        try {
            return DB::transaction(function () use ($data, $source, $identities): Contact {
                $contact = Contact::create(array_intersect_key($data, array_flip(self::FIELDS)) + [
                    'email' => $this->primaryValue($identities, IdentityType::Email),
                    'phone' => $this->primaryValue($identities, IdentityType::Phone),
                    'source' => $source,
                    'status' => $data['status'] ?? 'active',
                ]);
                foreach ($identities as $identity) {
                    $contact->identities()->create($identity);
                }
                $this->events->record('contact.created', $contact, ['source' => $source->value]);
                $this->usage->increment('contacts_created');

                return $contact;
            });
        } catch (UniqueConstraintViolationException) {
            // cursă între două cereri simultane cu același identificator
            throw ValidationException::withMessages(['email' => 'Există deja un contact cu aceste date.']);
        }
    }

    /** @param array<string, mixed> $data */
    public function update(Contact $contact, array $data): Contact
    {
        return DB::transaction(function () use ($contact, $data): Contact {
            $contact->fill(array_intersect_key($data, array_flip(self::FIELDS)));
            foreach ([IdentityType::Email->value => 'email', IdentityType::Phone->value => 'phone'] as $type => $field) {
                if (array_key_exists($field, $data)) {
                    $this->replacePrimary($contact, IdentityType::from($type), $data[$field]);
                }
            }
            $changed = array_keys($contact->getDirty());
            $contact->save();
            if ($changed) {
                $this->events->record('contact.updated', $contact, ['fields' => $changed]);
            }

            return $contact;
        });
    }

    /** Ștergere GDPR: contactul, identitățile, consimțămintele și lead-urile lui (cascade). Lista de suprimări rămâne. */
    public function delete(Contact $contact): void
    {
        DB::transaction(function () use ($contact): void {
            $this->audit->record('contact.deleted', $contact);
            $this->events->record('contact.deleted', $contact);
            $contact->delete();
        });
    }

    public function findByIdentity(IdentityType $type, string $value, string $provider = ''): ?Contact
    {
        $normalized = $this->normalize($type, $value);
        if ($normalized === null) {
            return null;
        }

        return ContactIdentity::query()->where('type', $type->value)->where('provider', $provider)
            ->where('normalized_value', $normalized)->first()?->contact;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{type: IdentityType, provider: string, value: string, normalized_value: string, is_primary: bool}>
     */
    private function identitiesFrom(array $data): array
    {
        $out = [];
        foreach (['email' => IdentityType::Email, 'phone' => IdentityType::Phone, 'whatsapp' => IdentityType::WhatsApp] as $field => $type) {
            if (! empty($data[$field])) {
                $out[] = $this->identity($type, (string) $data[$field], '', true, $field);
            }
        }
        foreach ($data['external_ids'] ?? [] as $i => $external) {
            $out[] = $this->identity(IdentityType::ExternalId, (string) $external['id'], (string) $external['provider'], false, "external_ids.{$i}.id");
        }

        return $out;
    }

    /** @return array{type: IdentityType, provider: string, value: string, normalized_value: string, is_primary: bool} */
    private function identity(IdentityType $type, string $value, string $provider, bool $primary, string $field): array
    {
        $normalized = $this->normalize($type, $value);
        if ($normalized === null) {
            throw ValidationException::withMessages([$field => 'Valoare invalidă (telefonul trebuie să includă prefixul țării sau să înceapă cu 0).']);
        }

        return ['type' => $type, 'provider' => $provider, 'value' => trim($value), 'normalized_value' => $normalized, 'is_primary' => $primary];
    }

    private function normalize(IdentityType $type, string $value): ?string
    {
        $country = $this->context->organization()->country ?? 'RO';

        return match ($type) {
            IdentityType::Email => IdentityNormalizer::email($value),
            IdentityType::Phone, IdentityType::WhatsApp => IdentityNormalizer::phone($value, $country),
            IdentityType::ExternalId => IdentityNormalizer::externalId($value),
        };
    }

    /** @param array{type: IdentityType, provider: string, normalized_value: string} $identity */
    private function assertIdentityFree(array $identity, ?int $exceptContactId = null): void
    {
        $existing = ContactIdentity::query()->where('type', $identity['type']->value)->where('provider', $identity['provider'])
            ->where('normalized_value', $identity['normalized_value'])
            ->when($exceptContactId, fn ($q) => $q->where('contact_id', '!=', $exceptContactId))->first();
        if ($existing) {
            throw new DuplicateContactException((int) $existing->contact_id, $identity['type']->value);
        }
    }

    /** @param list<array{type: IdentityType, normalized_value: string}> $identities */
    private function primaryValue(array $identities, IdentityType $type): ?string
    {
        foreach ($identities as $identity) {
            if ($identity['type'] === $type) {
                return $identity['normalized_value'];
            }
        }

        return null;
    }

    private function replacePrimary(Contact $contact, IdentityType $type, mixed $value): void
    {
        $field = $type === IdentityType::Email ? 'email' : 'phone';
        $contact->identities()->where('type', $type->value)->where('is_primary', true)->delete();
        if ($value === null || $value === '') {
            $contact->{$field} = null;

            return;
        }
        $identity = $this->identity($type, (string) $value, '', true, $field);
        $this->assertIdentityFree($identity, $contact->getKey());
        $contact->identities()->where('type', $type->value)->where('normalized_value', $identity['normalized_value'])->delete();
        $contact->identities()->create($identity);
        $contact->{$field} = $identity['normalized_value'];
    }
}
