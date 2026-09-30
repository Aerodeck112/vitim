<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DomainEvent;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Scrie evenimente de domeniu în outbox-ul `domain_events`. Apelat în aceeași tranzacție cu modificarea,
 * deci evenimentul există dacă și numai dacă modificarea a fost salvată. Payload: ID-uri și valori tehnice, fără date personale.
 */
final class EventRecorder
{
    public function __construct(private readonly TenantContext $context) {}

    /** @param array<string, scalar|list<scalar>|null> $payload */
    public function record(string $type, ?Model $subject = null, array $payload = []): DomainEvent
    {
        return DomainEvent::create([
            'organization_id' => $this->context->has() ? $this->context->id() : null,
            'type' => $type,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'payload' => $payload ?: null,
            'occurred_at' => now(),
        ]);
    }
}
