<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\ContactEvent;
use App\Models\ContactList;
use App\Models\Flow;
use App\Models\FlowRun;
use App\Models\FlowStep;
use App\Models\Segment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Construirea fluxurilor: din șablon, pași (inserare / editare / ștergere), declanșator, pornire / oprire. */
final class FlowService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(string $name, ?string $template, User $user): Flow
    {
        $t = $template ? (FlowTemplates::all()[$template] ?? null) : null;

        return DB::transaction(function () use ($name, $t, $user): Flow {
            $trigger = $t['trigger'] ?? ['type' => 'event', 'event' => 'lead_created'];
            if (($trigger['type'] ?? '') === 'segment' && isset($trigger['segment'])) {
                $segment = Segment::create(['name' => $trigger['segment']['name'], 'definition' => $trigger['segment']['definition']]);
                $trigger = ['type' => 'segment', 'segment_id' => $segment->id];
            }
            $flow = Flow::create(['name' => $name ?: ($t['name'] ?? 'Automatizare nouă'), 'status' => 'draft', 'trigger' => $trigger,
                'settings' => ($t['settings'] ?? []) + ['smart_sending_hours' => 16], 'created_by' => $user->id]);
            $this->insertSteps($flow, $t['steps'] ?? [], null, null);
            $this->audit->record('flow.created', $flow);

            return $flow;
        });
    }

    /** @param list<array<int, mixed>> $steps */
    private function insertSteps(Flow $flow, array $steps, ?FlowStep $parent, ?string $branch): void
    {
        foreach ($steps as $i => $s) {
            $step = FlowStep::create(['flow_id' => $flow->id, 'parent_id' => $parent?->id, 'branch' => $branch, 'position' => ($i + 1) * 10,
                'type' => $s[0], 'config' => $s[0] === 'condition' ? ['definition' => $s[1]] : $s[1]]);
            if ($s[0] === 'condition') {
                $this->insertSteps($flow, $s[2]['yes'] ?? [], $step, 'yes');
                $this->insertSteps($flow, $s[2]['no'] ?? [], $step, 'no');
            }
        }
    }

    /** Inserează un pas după $after (sau la începutul secvenței / ramurii). */
    public function addStep(Flow $flow, string $type, ?FlowStep $after, ?FlowStep $parent, ?string $branch): FlowStep
    {
        if (! isset(FlowStep::TYPES[$type])) {
            throw ValidationException::withMessages(['type' => 'Tip de pas necunoscut.']);
        }
        if ($after) {
            [$parent, $branch] = [$after->parent_id ? FlowStep::query()->find($after->parent_id) : null, $after->branch];
        }
        $siblings = FlowStep::query()->where('flow_id', $flow->id)
            ->where(fn ($q) => $parent ? $q->where('parent_id', $parent->id) : $q->whereNull('parent_id'))
            ->where(fn ($q) => $branch ? $q->where('branch', $branch) : $q->whereNull('branch'));
        $position = $after ? $after->position + 1 : 0;
        // face loc: pașii de după se mută cu o poziție
        (clone $siblings)->where('position', '>=', $position)->increment('position');
        $defaults = [
            'wait' => ['amount' => 1, 'unit' => 'days'],
            'email' => ['subject' => '', 'body' => ''],
            'sms' => ['body' => ''],
            'whatsapp' => ['template' => ['name' => '', 'language' => 'ro', 'variables' => ['{{prenume}}']]],
            'condition' => ['definition' => ['match' => 'all', 'conditions' => [['type' => 'since_start', 'event' => 'email_opened', 'op' => 'did']]]],
            'list_add' => ['list_id' => ContactList::query()->value('id')],
        ];
        $step = FlowStep::create(['flow_id' => $flow->id, 'parent_id' => $parent?->id, 'branch' => $branch, 'position' => $position, 'type' => $type, 'config' => $defaults[$type]]);
        $this->audit->record('flow.step_added', $flow, ['type' => $type]);

        return $step;
    }

    /** @param array<string, mixed> $input */
    public function updateStep(FlowStep $step, array $input): void
    {
        $config = match ($step->type) {
            'wait' => ['amount' => max(1, min(365, (int) ($input['amount'] ?? 1))), 'unit' => in_array($input['unit'] ?? '', ['minutes', 'hours', 'days'], true) ? $input['unit'] : 'days'],
            'email' => ['subject' => mb_substr(trim((string) ($input['subject'] ?? '')), 0, 200), 'body' => mb_substr((string) ($input['body'] ?? ''), 0, 20000),
                'blocks' => $step->conf('blocks'), 'preheader' => mb_substr(trim((string) ($input['preheader'] ?? '')), 0, 150) ?: null,
                'ignore_smart_sending' => ! empty($input['ignore_smart_sending'])],
            'sms' => ['body' => mb_substr((string) ($input['body'] ?? ''), 0, 900), 'ignore_smart_sending' => ! empty($input['ignore_smart_sending'])],
            'whatsapp' => ['template' => [
                'name' => preg_match('/^[a-z0-9_]{1,120}$/', (string) ($input['template_name'] ?? '')) ? $input['template_name'] : '',
                'language' => mb_substr((string) ($input['template_language'] ?? 'ro'), 0, 10) ?: 'ro',
                'variables' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($input['template_variables'] ?? ''))), fn ($v) => $v !== '')),
            ]],
            'condition' => ['definition' => $this->conditionDefinition((array) ($input['definition'] ?? []))],
            'list_add' => ['list_id' => ContactList::query()->whereKey((int) ($input['list_id'] ?? 0))->value('id')],
            default => (array) $step->config,
        };
        $step->forceFill(['config' => array_filter($config, fn ($v) => $v !== null)])->save();
    }

    /** Condițiile din segmente + „de la intrarea în flux a făcut / nu a făcut X”. @return array<string, mixed> */
    private function conditionDefinition(array $input): array
    {
        $since = [];
        foreach ((array) ($input['conditions'] ?? []) as $c) {
            if (($c['type'] ?? '') === 'since_start' && isset(ContactEvent::TYPES[$c['event'] ?? ''])) {
                $since[] = ['type' => 'since_start', 'event' => $c['event'], 'op' => ($c['op'] ?? '') === 'not' ? 'not' : 'did'];
            }
        }
        $normal = SegmentQuery::normalize($input);

        return ['match' => $normal['match'], 'conditions' => [...$since, ...$normal['conditions']]];
    }

    /** @param array<string, mixed> $definition @param array<int, string> $lists */
    public static function describeCondition(array $definition, array $lists = []): string
    {
        $parts = [];
        foreach ((array) ($definition['conditions'] ?? []) as $c) {
            $parts[] = ($c['type'] ?? '') === 'since_start'
                ? (($c['op'] ?? 'did') === 'did' ? 'a ' : 'nu a ').mb_strtolower(preg_replace('/^A /u', '', ContactEvent::TYPES[$c['event']][0] ?? $c['event']) ?? '').' de la intrarea în flux'
                : SegmentQuery::describe(['conditions' => [$c]], $lists);
        }

        return $parts ? 'Dacă '.implode(($definition['match'] ?? 'all') === 'any' ? ' sau ' : ' și ', $parts) : 'fără condiție (toți merg pe Da)';
    }

    public function deleteStep(FlowStep $step): void
    {
        $step->delete(); // ramurile unei condiții se șterg în cascadă
    }

    /** @param array<string, mixed> $input */
    public function updateFlow(Flow $flow, array $input): void
    {
        $type = (string) ($input['trigger_type'] ?? 'event');
        $trigger = match ($type) {
            'list' => ['type' => 'list', 'list_id' => ContactList::query()->whereKey((int) ($input['trigger_list_id'] ?? 0))->value('id')],
            'segment' => ['type' => 'segment', 'segment_id' => Segment::query()->whereKey((int) ($input['trigger_segment_id'] ?? 0))->value('id')],
            'date' => ['type' => 'date', 'field' => 'birthday'],
            default => ['type' => 'event', 'event' => in_array($input['trigger_event'] ?? '', Flow::TRIGGER_EVENTS, true) ? $input['trigger_event'] : 'lead_created'],
        };
        if (in_array($type, ['list', 'segment'], true) && empty($trigger[$type.'_id'])) {
            throw ValidationException::withMessages(['trigger_type' => $type === 'list' ? 'Alege lista.' : 'Alege segmentul.']);
        }
        $exit = array_values(array_intersect((array) ($input['exit_on'] ?? []), array_keys(ContactEvent::TYPES)));
        $flow->fill([
            'name' => mb_substr(trim((string) ($input['name'] ?? $flow->name)), 0, 160) ?: $flow->name,
            'trigger' => $trigger + (isset($flow->trigger['filter']) ? ['filter' => $flow->trigger['filter']] : []),
            'settings' => [
                'exit_on' => $exit,
                'smart_sending_hours' => max(0, min(168, (int) ($input['smart_sending_hours'] ?? 16))),
                'reentry_days' => ($input['reentry'] ?? 'never') === 'never' ? null : max(0, min(3650, (int) ($input['reentry_days'] ?? 30))),
            ],
        ])->save();
        $this->audit->record('flow.updated', $flow);
    }

    public function setStatus(Flow $flow, string $status): void
    {
        if ($status === 'live') {
            $this->validateForLive($flow);
            if (($flow->trigger['type'] ?? '') === 'segment' && $flow->status === 'draft') {
                // segment: intră doar cei care ajung în segment DUPĂ pornire (cei de acum sunt punctul de plecare)
                $segment = Segment::query()->find((int) $flow->trigger['segment_id']);
                if ($segment) {
                    $ids = SegmentQuery::apply(Contact::query(), $segment->definition)->pluck('id');
                    DB::table('flow_segment_members')->where('flow_id', $flow->id)->delete();
                    foreach ($ids->chunk(500) as $chunk) {
                        DB::table('flow_segment_members')->insert($chunk->map(fn ($id) => ['flow_id' => $flow->id, 'contact_id' => $id])->all());
                    }
                }
            }
            $flow->forceFill(['status' => 'live', 'live_at' => $flow->live_at ?? now()])->save();
        } else {
            $flow->forceFill(['status' => 'paused'])->save();
        }
        $this->audit->record('flow.'.$status, $flow);
    }

    private function validateForLive(Flow $flow): void
    {
        $steps = FlowStep::query()->where('flow_id', $flow->id)->get();
        if ($steps->isEmpty()) {
            throw ValidationException::withMessages(['flow' => 'Adaugă cel puțin un pas.']);
        }
        foreach ($steps as $step) {
            $missing = match ($step->type) {
                'email' => trim((string) $step->conf('subject')) === '' || (trim((string) $step->conf('body')) === '' && empty($step->conf('blocks'))),
                'sms' => trim((string) $step->conf('body')) === '',
                'whatsapp' => trim((string) ($step->conf('template')['name'] ?? '')) === '',
                'list_add' => ! $step->conf('list_id'),
                default => false,
            };
            if ($missing) {
                throw ValidationException::withMessages(['flow' => 'Completează pasul „'.FlowStep::TYPES[$step->type].'” (are câmpuri goale) înainte de pornire.']);
            }
        }
    }

    /** Statistici pe pas (trimise, deschise, click-uri) și pe flux (în flux acum, terminați, ieșiți). @return array<string, mixed> */
    public function stats(Flow $flow): array
    {
        $steps = CampaignRecipient::query()->where('flow_id', $flow->id)
            ->selectRaw("flow_step_id, sum(case when status in ('sent','delivered','read','unsubscribed') then 1 else 0 end) as sent, sum(case when opened_at is not null then 1 else 0 end) as opened, sum(case when clicked_at is not null then 1 else 0 end) as clicked, sum(case when status = 'excluded' then 1 else 0 end) as skipped")
            ->groupBy('flow_step_id')->get()->keyBy('flow_step_id');
        $runs = FlowRun::query()->where('flow_id', $flow->id)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return ['steps' => $steps, 'runs' => $runs];
    }
}
