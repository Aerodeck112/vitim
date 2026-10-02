<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** O automatizare: declanșator + pași; fiecare contact care intră are o rulare (FlowRun). */
#[Fillable(['name', 'status', 'trigger', 'settings', 'created_by', 'live_at'])]
class Flow extends Model
{
    use BelongsToOrganization;

    public const STATUSES = ['draft' => 'Ciornă', 'live' => 'Pornită', 'paused' => 'Oprită'];

    /** Evenimentele care pot porni un flux. */
    public const TRIGGER_EVENTS = ['form_submitted', 'lead_created', 'subscribed', 'started_checkout', 'placed_order', 'viewed_product', 'added_to_cart', 'email_clicked', 'unsubscribed'];

    protected function casts(): array
    {
        return ['trigger' => 'array', 'settings' => 'array', 'live_at' => 'datetime'];
    }

    /** @return HasMany<FlowStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(FlowStep::class)->orderBy('position');
    }

    /** @param array<int, string> $lists @param array<int, string> $segments */
    public function describeTrigger(array $lists = [], array $segments = []): string
    {
        $t = (array) $this->trigger;

        return match ($t['type'] ?? '') {
            'event' => 'Când: '.mb_strtolower(ContactEvent::TYPES[$t['event'] ?? ''][0] ?? ($t['event'] ?? '?')),
            'list' => 'Când e adăugat în lista „'.($lists[$t['list_id'] ?? 0] ?? '?').'”',
            'segment' => 'Când intră în segmentul „'.($segments[$t['segment_id'] ?? 0] ?? '?').'”',
            'date' => 'De ziua lui (câmpul „Zi de naștere”)',
            default => '?',
        };
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return ($this->settings ?? [])[$key] ?? $default;
    }
}
