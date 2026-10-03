<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactEvent;
use App\Models\Flow;
use App\Models\FlowRun;
use App\Models\FlowStep;
use App\Models\Segment;
use Illuminate\Support\Facades\DB;

/**
 * Cine intră într-un flux și când: la un eveniment (formular, cerere, comandă...), la intrarea într-o listă
 * sau într-un segment, sau la o dată (zi de naștere). Reguli: o singură rulare activă pe contact, iar reintrarea
 * e permisă doar după numărul de zile setat (implicit niciodată).
 */
final class FlowTriggers
{
    public function onEvent(ContactEvent $event): void
    {
        // ieșirea din flux la eveniment (ex. a comandat) se aplică imediat, nu abia la următorul pas
        FlowRun::query()->where('contact_id', $event->contact_id)->where('status', 'active')->with('flow')->get()
            ->filter(fn (FlowRun $run) => $run->flow && in_array($event->type, (array) $run->flow->setting('exit_on', []), true))
            ->each(fn (FlowRun $run) => $run->forceFill(['status' => 'exited', 'step_id' => null, 'wake_at' => null, 'finished_at' => now(),
                'note' => 'A ieșit: '.mb_strtolower(ContactEvent::TYPES[$event->type][0] ?? $event->type)])->save());

        $flows = Flow::query()->where('status', 'live')->get()->filter(function (Flow $flow) use ($event): bool {
            $t = (array) $flow->trigger;

            return match ($t['type'] ?? '') {
                'event' => ($t['event'] ?? '') === $event->type,
                'list' => $event->type === 'joined_list' && (int) ($event->data['list_id'] ?? 0) === (int) ($t['list_id'] ?? -1),
                default => false,
            };
        });
        foreach ($flows as $flow) {
            $this->enter($flow, $event->contact_id, array_filter(['event_id' => $event->id, 'data' => $event->data, 'value' => $event->value !== null ? (float) $event->value : null]));
        }
    }

    /** Contactele intrate în segmentele fluxurilor de tip „segment” de la ultima verificare. */
    public function checkSegments(): int
    {
        $started = 0;
        foreach (Flow::query()->where('status', 'live')->get() as $flow) {
            if (($flow->trigger['type'] ?? '') !== 'segment' || ! ($segment = Segment::query()->find((int) ($flow->trigger['segment_id'] ?? 0)))) {
                continue;
            }
            $current = SegmentQuery::apply(Contact::query(), $segment->definition)->pluck('id')->all();
            $known = DB::table('flow_segment_members')->where('flow_id', $flow->id)->pluck('contact_id')->all();
            foreach (array_diff($current, $known) as $contactId) {
                DB::table('flow_segment_members')->insert(['flow_id' => $flow->id, 'contact_id' => $contactId]);
                $started += $this->enter($flow, (int) $contactId, ['segment_id' => $segment->id]) ? 1 : 0;
            }
            // cei ieșiți din segment pot intra din nou (dacă regula de reintrare permite)
            $left = array_diff($known, $current);
            if ($left) {
                DB::table('flow_segment_members')->where('flow_id', $flow->id)->whereIn('contact_id', $left)->delete();
            }
        }

        return $started;
    }

    /** Fluxurile pe dată (zi de naștere): o dată pe zi, după ora 9 (ora României). */
    public function checkDates(): int
    {
        $now = now()->setTimezone('Europe/Bucharest');
        if ($now->hour < 9) {
            return 0;
        }
        $started = 0;
        foreach (Flow::query()->where('status', 'live')->get() as $flow) {
            if (($flow->trigger['type'] ?? '') !== 'date') {
                continue;
            }
            $monthDay = $now->format('m-d');
            Contact::query()->whereNotNull('custom_fields->birthday')->chunkById(500, function ($contacts) use ($flow, $monthDay, $now, &$started): void {
                foreach ($contacts as $contact) {
                    $birthday = (string) ($contact->custom_fields['birthday'] ?? '');
                    if (preg_match('/(\d{2})-(\d{2})$/', $birthday, $m) && "{$m[1]}-{$m[2]}" === $monthDay
                        && ! FlowRun::query()->where('flow_id', $flow->id)->where('contact_id', $contact->id)->where('started_at', '>=', $now->copy()->startOfDay()->utc())->exists()) {
                        $started += $this->enter($flow, $contact->id, ['date' => $monthDay], 300) ? 1 : 0;
                    }
                }
            });
        }

        return $started;
    }

    /** Pornește o rulare dacă regulile o permit. */
    public function enter(Flow $flow, int $contactId, array $data = [], ?int $reentryDays = null): bool
    {
        $reentry = $reentryDays ?? $flow->setting('reentry_days');
        $previous = FlowRun::query()->where('flow_id', $flow->id)->where('contact_id', $contactId)->latest('id')->first();
        if ($previous && ($previous->status === 'active' || $reentry === null || $previous->started_at->gt(now()->subDays((int) $reentry)))) {
            return false;
        }
        $filter = (array) ($flow->trigger['filter'] ?? []);
        if (! empty($filter['conditions']) && ! SegmentQuery::apply(Contact::query()->whereKey($contactId), $filter)->exists()) {
            return false;
        }
        $first = FlowStep::query()->where('flow_id', $flow->id)->whereNull('parent_id')->orderBy('position')->first();
        if (! $first) {
            return false;
        }
        FlowRun::create(['flow_id' => $flow->id, 'contact_id' => $contactId, 'step_id' => $first->id, 'status' => 'active',
            'wake_at' => now(), 'trigger_data' => $data ?: null, 'started_at' => now()]);

        return true;
    }
}
