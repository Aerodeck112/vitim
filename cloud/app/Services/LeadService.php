<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeadIntent;
use App\Enums\LeadStatus;
use App\Models\Agent;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Membership;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Lead-uri: creare pentru un contact, schimbare de status / responsabil, ștergere. */
final class LeadService
{
    public function __construct(
        private readonly EventRecorder $events,
        private readonly UsageMeter $usage,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(Contact $contact, array $data): Lead
    {
        $this->assertReferences($data);
        $status = LeadStatus::from($data['status'] ?? LeadStatus::New->value);

        return DB::transaction(function () use ($contact, $data, $status): Lead {
            $lead = Lead::create([
                'contact_id' => $contact->getKey(),
                'site_id' => $data['site_id'] ?? null,
                'agent_id' => $data['agent_id'] ?? null,
                'source' => $data['source'] ?? 'manual',
                'status' => $status,
                'intent' => $data['intent'] ?? LeadIntent::Other->value,
                'score' => $data['score'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'summary' => $data['summary'] ?? null,
                'value_amount' => $data['value_amount'] ?? null,
                'currency' => $data['currency'] ?? null,
                'closed_at' => $status->isClosed() ? now() : null,
            ]);
            $this->events->record('lead.created', $lead, ['contact_id' => $contact->getKey(), 'intent' => $lead->intent->value, 'source' => $lead->source]);
            $this->usage->increment('leads_created');

            return $lead;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Lead $lead, array $data): Lead
    {
        $this->assertReferences($data);

        return DB::transaction(function () use ($lead, $data): Lead {
            $oldStatus = $lead->status;
            $oldAssignee = $lead->assigned_to;
            $lead->fill(array_intersect_key($data, array_flip(['status', 'intent', 'score', 'assigned_to', 'summary', 'value_amount', 'currency', 'site_id', 'agent_id'])));
            if ($lead->isDirty('status')) {
                $lead->closed_at = $lead->status->isClosed() ? now() : null;
            }
            $lead->save();
            if ($oldStatus !== $lead->status) {
                $this->events->record('lead.status_changed', $lead, ['from' => $oldStatus->value, 'to' => $lead->status->value]);
            }
            if ($oldAssignee !== $lead->assigned_to) {
                $this->events->record('lead.assigned', $lead, ['assigned_to' => $lead->assigned_to]);
            }

            return $lead;
        });
    }

    public function delete(Lead $lead): void
    {
        DB::transaction(function () use ($lead): void {
            $this->audit->record('lead.deleted', $lead);
            $this->events->record('lead.deleted', $lead);
            $lead->delete();
        });
    }

    /** Referințele trebuie să aparțină firmei curente (interogări scoped → altă firmă = inexistent). */
    private function assertReferences(array $data): void
    {
        if (! empty($data['assigned_to']) && ! Membership::query()->where('user_id', $data['assigned_to'])->exists()) {
            throw ValidationException::withMessages(['assigned_to' => 'Utilizatorul nu face parte din firmă.']);
        }
        if (! empty($data['site_id']) && ! Site::query()->whereKey($data['site_id'])->exists()) {
            throw ValidationException::withMessages(['site_id' => 'Site inexistent.']);
        }
        if (! empty($data['agent_id']) && ! Agent::query()->whereKey($data['agent_id'])->exists()) {
            throw ValidationException::withMessages(['agent_id' => 'Agent inexistent.']);
        }
    }
}
