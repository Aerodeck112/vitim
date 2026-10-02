<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Channel;
use App\Models\CampaignRecipient;
use App\Models\ChannelAccount;
use App\Models\Contact;
use App\Models\ContactEvent;
use App\Models\ContactList;
use App\Models\FlowRun;
use App\Models\FlowStep;
use Illuminate\Support\Str;

/**
 * Avansează rulările: execută pașii până la o așteptare sau la final.
 * - „ieșire la eveniment” (ex. a comandat): contactul iese din flux înainte de orice pas;
 * - smart sending: nu trimite dacă a primit deja un mesaj de marketing în ultimele N ore (implicit 16);
 * - SMS / WhatsApp doar între 9 și 20 (ora României); în afara intervalului, pasul așteaptă dimineața.
 */
final class FlowRunner
{
    private const MAX_STEPS_PER_RUN = 25;

    public function __construct(private readonly Deliverer $deliverer, private readonly ListService $lists) {}

    public function advance(FlowRun $run): void
    {
        $flow = $run->flow;
        if ($flow->status !== 'live') {
            return; // fluxurile oprite păstrează rulările pe loc; continuă la repornire
        }
        for ($i = 0; $i < self::MAX_STEPS_PER_RUN; $i++) {
            $step = $run->step_id ? FlowStep::query()->find($run->step_id) : null;
            if (! $step || ! $run->contact) {
                $this->finish($run, 'completed');

                return;
            }
            $exit = (array) $flow->setting('exit_on', []);
            if ($exit && ContactEvent::query()->where('contact_id', $run->contact_id)->whereIn('type', $exit)->where('occurred_at', '>=', $run->started_at)->exists()) {
                $this->finish($run, 'exited', 'A ieșit: '.implode(', ', array_map(fn ($t) => mb_strtolower(ContactEvent::TYPES[$t][0] ?? $t), $exit)));

                return;
            }
            $next = match ($step->type) {
                'wait' => $this->wait($run, $step),
                'email', 'sms', 'whatsapp' => $this->message($run, $step),
                'condition' => $this->condition($run, $step),
                'list_add' => $this->listAdd($run, $step),
                default => $this->after($step),
            };
            if ($next === false) {
                return; // așteaptă (wake_at setat)
            }
            $run->forceFill(['step_id' => $next?->id])->save();
        }
    }

    /** Pasul următor din aceeași secvență; null = finalul fluxului (ramurile nu se reunesc). */
    public function after(FlowStep $step): ?FlowStep
    {
        return FlowStep::query()->where('flow_id', $step->flow_id)
            ->where(fn ($q) => $step->parent_id ? $q->where('parent_id', $step->parent_id) : $q->whereNull('parent_id'))
            ->where(fn ($q) => $step->branch ? $q->where('branch', $step->branch) : $q->whereNull('branch'))
            ->where('position', '>', $step->position)->orderBy('position')->first();
    }

    private function wait(FlowRun $run, FlowStep $step): false
    {
        $unit = ['minutes' => 1, 'hours' => 60, 'days' => 1440][(string) $step->conf('unit', 'days')] ?? 1440;
        $minutes = max(1, (int) $step->conf('amount', 1) * $unit);
        $next = $this->after($step);
        $run->forceFill(['step_id' => $next?->id, 'wake_at' => now()->addMinutes($minutes)])->save();
        if (! $next) {
            $this->finish($run, 'completed');
        }

        return false;
    }

    private function message(FlowRun $run, FlowStep $step): FlowStep|false|null
    {
        $channel = Channel::from($step->type);
        if ($channel !== Channel::Email) {
            $local = now()->setTimezone('Europe/Bucharest');
            if ($local->hour < 9 || $local->hour >= 20) {
                $wake = $local->hour >= 20 ? $local->copy()->addDay()->setTime(9, 0) : $local->copy()->setTime(9, 0);
                $run->forceFill(['wake_at' => $wake->utc()])->save();

                return false;
            }
        }
        $account = ChannelAccount::query()->where('channel', $channel->value)->first();
        $hours = (int) $run->flow->setting('smart_sending_hours', 16);
        $recent = $hours > 0 && ContactEvent::query()->where('contact_id', $run->contact_id)
            ->where('type', $channel->value.'_sent')->where('occurred_at', '>=', now()->subHours($hours))->exists();
        $recipient = CampaignRecipient::create([
            'flow_id' => $run->flow_id, 'flow_step_id' => $step->id, 'flow_run_id' => $run->id, 'contact_id' => $run->contact_id,
            'status' => 'pending', 'unsubscribe_code' => Str::random(10),
        ]);
        if (! $account) {
            $recipient->forceFill(['status' => 'excluded', 'reason' => 'Contul de trimitere nu e conectat.'])->save();
        } elseif ($recent && ! $step->conf('ignore_smart_sending')) {
            $recipient->forceFill(['status' => 'excluded', 'reason' => "Smart sending: a primit deja un mesaj în ultimele {$hours} ore."])->save();
        } else {
            $this->deliverer->deliver($recipient->setRelation('contact', $run->contact), MessageContent::fromArray($channel, (array) $step->config), $account, $this->variables($run));
        }

        return $this->after($step);
    }

    private function condition(FlowRun $run, FlowStep $step): ?FlowStep
    {
        $definition = (array) $step->conf('definition', ['match' => 'all', 'conditions' => []]);
        $since = array_values(array_filter((array) ($definition['conditions'] ?? []), fn ($c) => ($c['type'] ?? '') === 'since_start'));
        $normal = $definition;
        $normal['conditions'] = array_values(array_filter((array) ($definition['conditions'] ?? []), fn ($c) => ($c['type'] ?? '') !== 'since_start'));
        $results = [];
        if ($normal['conditions'] !== []) {
            $results[] = SegmentQuery::apply(Contact::query()->whereKey($run->contact_id), $normal)->exists();
        }
        foreach ($since as $c) {
            // „de la intrarea în flux a făcut X” (ex. a comandat, a dat click)
            $did = ContactEvent::query()->where('contact_id', $run->contact_id)->where('type', (string) ($c['event'] ?? ''))->where('occurred_at', '>=', $run->started_at)->exists();
            $results[] = ($c['op'] ?? 'did') === 'did' ? $did : ! $did;
        }
        $yes = $results === [] || (($definition['match'] ?? 'all') === 'any' ? in_array(true, $results, true) : ! in_array(false, $results, true));
        $branch = $yes ? 'yes' : 'no';

        return FlowStep::query()->where('flow_id', $step->flow_id)->where('parent_id', $step->id)->where('branch', $branch)->orderBy('position')->first();
    }

    private function listAdd(FlowRun $run, FlowStep $step): ?FlowStep
    {
        if ($list = ContactList::query()->find((int) $step->conf('list_id'))) {
            $this->lists->add($list, $run->contact, 'flow');
        }

        return $this->after($step);
    }

    /** Variabile din evenimentul care a pornit fluxul (ex. produsele din coș, linkul de finalizare). @return array<string, string> */
    private function variables(FlowRun $run): array
    {
        $data = (array) ($run->trigger_data['data'] ?? []);
        $items = array_map(fn ($i) => (string) ($i['name'] ?? ''), (array) ($data['items'] ?? []));

        return array_filter([
            '{{produse}}' => implode(', ', array_filter($items)),
            '{{total}}' => isset($run->trigger_data['value']) ? number_format((float) $run->trigger_data['value'], 2, ',', '.').' '.($data['currency'] ?? 'lei') : '',
            '{{link_cos}}' => (string) ($data['checkout_url'] ?? ''),
            '{{produs}}' => (string) ($data['product'] ?? ''),
            '{{link_produs}}' => (string) ($data['url'] ?? ''),
        ], fn ($v) => $v !== '');
    }

    private function finish(FlowRun $run, string $status, ?string $note = null): void
    {
        $run->forceFill(['status' => $status, 'step_id' => null, 'wake_at' => null, 'finished_at' => now(), 'note' => $note])->save();
    }
}
