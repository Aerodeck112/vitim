<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactEvent;
use Carbon\CarbonInterface;

/** Scrie activitatea în profilul contactului (cronologia lui și baza segmentelor / automatizărilor). */
final class ContactActivity
{
    /** @param array<string, mixed> $data */
    public function record(Contact|int $contact, string $type, array $data = [], ?float $value = null, ?int $campaignId = null, ?int $flowId = null, ?int $recipientId = null, ?CarbonInterface $at = null): ContactEvent
    {
        $id = $contact instanceof Contact ? $contact->getKey() : $contact;
        $event = ContactEvent::create([
            'contact_id' => $id, 'type' => $type, 'data' => $data ?: null, 'value' => $value,
            'campaign_id' => $campaignId, 'flow_id' => $flowId, 'recipient_id' => $recipientId, 'occurred_at' => $at ?? now(),
        ]);
        Contact::query()->whereKey($id)->where(fn ($q) => $q->whereNull('last_activity_at')->orWhere('last_activity_at', '<', $at ?? now()))->update(['last_activity_at' => $at ?? now()]);
        if ($at === null || $at->gt(now()->subDay())) {
            app(FlowTriggers::class)->onEvent($event); // istoricul importat (ex. comenzi vechi) nu pornește automatizări
        }

        return $event;
    }
}
