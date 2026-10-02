<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContactSource;
use App\Models\Contact;
use App\Models\ContactEvent;
use App\Models\Segment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * Motorul segmentelor: transformă condițiile (ca în Klaviyo: „a deschis un email în ultimele 30 de zile”,
 * „are acord SMS”, „nu e în lista X”, „orașul conține Suceava”) în SQL pe tabela contacts.
 * Definiția: {match: all|any, conditions: [{type, ...}]}; normalize() acceptă doar chei și valori cunoscute.
 */
final class SegmentQuery
{
    public const FIELDS = [
        'first_name' => 'Prenume', 'last_name' => 'Nume', 'email' => 'Email', 'phone' => 'Telefon', 'company' => 'Firmă',
        'source' => 'Sursă', 'created_at' => 'Data adăugării', 'last_activity_at' => 'Ultima activitate',
        'city' => 'Oraș', 'county' => 'Județ', 'birthday' => 'Zi de naștere',
    ];

    /** câmpuri păstrate în custom_fields */
    public const CUSTOM = ['city', 'county', 'birthday'];

    public const DATE_FIELDS = ['created_at', 'last_activity_at'];

    public const TEXT_OPS = ['equals' => 'este', 'not_equals' => 'nu este', 'contains' => 'conține', 'not_contains' => 'nu conține', 'is_set' => 'e completat', 'not_set' => 'nu e completat'];

    public const DATE_OPS = ['in_last_days' => 'în ultimele … zile', 'older_than_days' => 'mai vechi de … zile', 'after' => 'după data', 'before' => 'înainte de data'];

    /** @param array<string, mixed> $definition */
    public static function apply(Builder $query, array $definition): Builder
    {
        $conditions = (array) ($definition['conditions'] ?? []);
        if ($conditions === []) {
            return $query;
        }
        $any = ($definition['match'] ?? 'all') === 'any';

        return $query->where(function (Builder $w) use ($conditions, $any): void {
            foreach ($conditions as $c) {
                $w->{$any ? 'orWhere' : 'where'}(fn (Builder $q) => self::condition($q, (array) $c));
            }
        });
    }

    /** @param array<string, mixed> $c */
    private static function condition(Builder $q, array $c): void
    {
        match ($c['type'] ?? '') {
            'property' => self::property($q, $c),
            'consent' => ($c['op'] ?? 'granted') === 'granted'
                ? $q->whereExists(fn ($s) => self::grantedConsent($s, (string) $c['channel']))
                : $q->whereNotExists(fn ($s) => self::grantedConsent($s, (string) $c['channel'])),
            'list' => ($c['op'] ?? 'in') === 'in'
                ? $q->whereExists(fn ($s) => $s->from('contact_list_members as m')->whereColumn('m.contact_id', 'contacts.id')->where('m.contact_list_id', (int) $c['list_id']))
                : $q->whereNotExists(fn ($s) => $s->from('contact_list_members as m')->whereColumn('m.contact_id', 'contacts.id')->where('m.contact_list_id', (int) $c['list_id'])),
            'event' => self::event($q, $c),
            'lead' => ($c['op'] ?? 'has') === 'has'
                ? $q->whereExists(fn ($s) => self::leads($s, $c))
                : $q->whereNotExists(fn ($s) => self::leads($s, $c)),
            'segment' => self::nested($q, $c),
            'revenue' => $q->whereRaw('(select coalesce(sum(e.value), 0) from contact_events e where e.contact_id = contacts.id and e.type = ?'.(! empty($c['days']) ? ' and e.occurred_at >= ?' : '').') '.(($c['op'] ?? 'at_least') === 'at_least' ? '>=' : '<').' cast(? as decimal(12,2))',
                array_values(array_filter(['placed_order', ! empty($c['days']) ? now()->subDays((int) $c['days']) : null, (float) ($c['value'] ?? 0)], fn ($v) => $v !== null))),
            default => $q->whereRaw('1 = 0'), // condiție necunoscută: nimeni (fail-closed)
        };
    }

    private static int $depth = 0;

    /** „e în segmentul X” (folosit în condițiile automatizărilor); adâncime limitată, fără cicluri. @param array<string, mixed> $c */
    private static function nested(Builder $q, array $c): void
    {
        $segment = self::$depth < 3 ? Segment::query()->find((int) ($c['segment_id'] ?? 0)) : null;
        if (! $segment) {
            $q->whereRaw('1 = 0');

            return;
        }
        self::$depth++;
        try {
            $ids = self::apply(Contact::query(), $segment->definition)->select('contacts.id');
            ($c['op'] ?? 'in') === 'in' ? $q->whereIn('contacts.id', $ids) : $q->whereNotIn('contacts.id', $ids);
        } finally {
            self::$depth--;
        }
    }

    /** @param array<string, mixed> $c */
    private static function property(Builder $q, array $c): void
    {
        $field = (string) $c['field'];
        $column = in_array($field, self::CUSTOM, true) ? 'custom_fields->'.$field : $field;
        $value = (string) ($c['value'] ?? '');
        // comparații fără majuscule, la fel pe SQLite și MySQL (valorile JSON au colaționare binară în MySQL)
        $lower = 'lower('.$q->getQuery()->getGrammar()->wrap($column).')';
        $needle = mb_strtolower($value);
        $like = '%'.addcslashes($needle, '%_').'%';
        match ($c['op'] ?? '') {
            'equals' => $q->whereRaw("{$lower} = ?", [$needle]),
            'not_equals' => $q->where(fn ($x) => $x->whereNull($column)->orWhereRaw("{$lower} != ?", [$needle])),
            'contains' => $q->whereRaw("{$lower} like ?", [$like]),
            'not_contains' => $q->where(fn ($x) => $x->whereNull($column)->orWhereRaw("{$lower} not like ?", [$like])),
            'is_set' => $q->whereNotNull($column)->where($column, '!=', ''),
            'not_set' => $q->where(fn ($x) => $x->whereNull($column)->orWhere($column, '')),
            'in_last_days' => $q->where($column, '>=', now()->subDays((int) $value)),
            'older_than_days' => $q->where($column, '<', now()->subDays((int) $value)),
            'after' => $q->where($column, '>', Carbon::parse($value)->endOfDay()),
            'before' => $q->where($column, '<', Carbon::parse($value)->startOfDay()),
            default => $q->whereRaw('1 = 0'),
        };
    }

    /** @param array<string, mixed> $c */
    private static function event(Builder $q, array $c): void
    {
        $since = ! empty($c['days']) ? now()->subDays((int) $c['days']) : null;
        $scope = function ($s) use ($c, $since): void {
            $s->from('contact_events as e')->whereColumn('e.contact_id', 'contacts.id')->where('e.type', (string) $c['event'])
                ->when($since, fn ($x) => $x->where('e.occurred_at', '>=', $since))
                ->when(! empty($c['campaign_id']), fn ($x) => $x->where('e.campaign_id', (int) $c['campaign_id']));
        };
        if (($c['op'] ?? 'at_least') === 'zero') {
            $q->whereNotExists($scope);

            return;
        }
        $count = max(1, (int) ($c['count'] ?? 1));
        if ($count === 1) {
            $q->whereExists($scope);

            return;
        }
        $sub = \DB::query();
        $scope($sub);
        $q->whereRaw('('.$sub->selectRaw('count(*)')->toSql().') >= ?', [...$sub->getBindings(), $count]);
    }

    private static function grantedConsent(QueryBuilder $s, string $channel): void
    {
        // ultima înregistrare de acord de marketing pe canal trebuie să fie „granted”
        $s->from('contact_consents as cc')->whereColumn('cc.contact_id', 'contacts.id')
            ->where('cc.channel', $channel)->where('cc.purpose', 'marketing')->where('cc.status', 'granted')
            ->whereNotExists(fn ($n) => $n->from('contact_consents as cn')->whereColumn('cn.contact_id', 'cc.contact_id')
                ->whereColumn('cn.channel', 'cc.channel')->whereColumn('cn.purpose', 'cc.purpose')
                ->where(fn ($o) => $o->whereColumn('cn.occurred_at', '>', 'cc.occurred_at')
                    ->orWhere(fn ($e) => $e->whereColumn('cn.occurred_at', 'cc.occurred_at')->whereColumn('cn.id', '>', 'cc.id'))));
    }

    /** @param array<string, mixed> $c */
    private static function leads(QueryBuilder $s, array $c): void
    {
        $s->from('leads as l')->whereColumn('l.contact_id', 'contacts.id')->when(! empty($c['status']), fn ($x) => $x->where('l.status', (string) $c['status']));
    }

    /**
     * Păstrează doar condițiile valide (din formular sau API). @param array<string, mixed> $input
     *
     * @return array{match: string, conditions: list<array<string, mixed>>}
     */
    public static function normalize(array $input): array
    {
        $out = [];
        foreach (array_slice((array) ($input['conditions'] ?? []), 0, 20) as $c) {
            $c = (array) $c;
            $type = (string) ($c['type'] ?? '');
            $clean = match ($type) {
                'property' => isset(self::FIELDS[$c['field'] ?? '']) && (isset(self::TEXT_OPS[$c['op'] ?? '']) || isset(self::DATE_OPS[$c['op'] ?? '']))
                    ? ['type' => 'property', 'field' => $c['field'], 'op' => $c['op'], 'value' => self::value($c)] : null,
                'consent' => in_array($c['channel'] ?? '', ['email', 'sms', 'whatsapp'], true)
                    ? ['type' => 'consent', 'channel' => $c['channel'], 'op' => ($c['op'] ?? '') === 'not_granted' ? 'not_granted' : 'granted'] : null,
                'segment' => (int) ($c['segment_id'] ?? 0) > 0 ? ['type' => 'segment', 'segment_id' => (int) $c['segment_id'], 'op' => ($c['op'] ?? '') === 'not_in' ? 'not_in' : 'in'] : null,
                'list' => (int) ($c['list_id'] ?? 0) > 0 ? ['type' => 'list', 'list_id' => (int) $c['list_id'], 'op' => ($c['op'] ?? '') === 'not_in' ? 'not_in' : 'in'] : null,
                'event' => isset(ContactEvent::TYPES[$c['event'] ?? '']) ? array_filter([
                    'type' => 'event', 'event' => $c['event'], 'op' => ($c['op'] ?? '') === 'zero' ? 'zero' : 'at_least',
                    'count' => max(1, min(1000, (int) ($c['count'] ?? 1))), 'days' => (int) ($c['days'] ?? 0) ?: null,
                    'campaign_id' => (int) ($c['campaign_id'] ?? 0) ?: null,
                ], fn ($v) => $v !== null) : null,
                'lead' => ['type' => 'lead', 'op' => ($c['op'] ?? '') === 'none' ? 'none' : 'has', 'status' => preg_match('/^[a-z_]{1,20}$/', (string) ($c['status'] ?? '')) ? $c['status'] : null],
                'revenue' => ['type' => 'revenue', 'op' => ($c['op'] ?? '') === 'less_than' ? 'less_than' : 'at_least', 'value' => max(0, (float) ($c['value'] ?? 0)), 'days' => (int) ($c['days'] ?? 0) ?: null],
                default => null,
            };
            if ($clean !== null) {
                $out[] = array_filter($clean, fn ($v) => $v !== null && $v !== '');
            }
        }

        return ['match' => ($input['match'] ?? 'all') === 'any' ? 'any' : 'all', 'conditions' => $out];
    }

    /** @param array<string, mixed> $c */
    private static function value(array $c): string
    {
        $value = mb_substr(trim((string) ($c['value'] ?? '')), 0, 120);
        if (in_array($c['op'], ['in_last_days', 'older_than_days'], true)) {
            return (string) max(0, min(3650, (int) $value));
        }
        if (in_array($c['op'], ['after', 'before'], true)) {
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : now()->toDateString();
        }
        if (($c['field'] ?? '') === 'source' && ! ContactSource::tryFrom($value) && ! in_array($c['op'], ['is_set', 'not_set'], true)) {
            return 'manual';
        }

        return $value;
    }

    /** O propoziție pe înțelesul oricui (în listă și în campanii). @param array<string, mixed> $definition */
    public static function describe(array $definition, array $lists = []): string
    {
        $parts = [];
        foreach ((array) ($definition['conditions'] ?? []) as $c) {
            $parts[] = match ($c['type']) {
                'property' => (self::FIELDS[$c['field']] ?? $c['field']).' '.(self::TEXT_OPS[$c['op']] ?? self::DATE_OPS[$c['op']] ?? $c['op']).(in_array($c['op'], ['is_set', 'not_set'], true) ? '' : ' „'.($c['value'] ?? '').'”'),
                'consent' => ($c['op'] === 'granted' ? 'are' : 'nu are').' acord de marketing pe '.['email' => 'email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'][$c['channel']],
                'list' => ($c['op'] === 'in' ? 'e în' : 'nu e în').' lista „'.($lists[$c['list_id']] ?? '#'.$c['list_id']).'”',
                'event' => mb_strtolower(ContactEvent::TYPES[$c['event']][0] ?? $c['event']).' '.($c['op'] === 'zero' ? 'niciodată' : 'de cel puțin '.($c['count'] ?? 1).' ori').(! empty($c['days']) ? ' în ultimele '.$c['days'].' zile' : ''),
                'lead' => $c['op'] === 'has' ? 'a trimis o cerere' : 'nu a trimis nicio cerere',
                'segment' => ($c['op'] === 'in' ? 'e în' : 'nu e în').' segmentul „'.(Segment::query()->whereKey($c['segment_id'])->value('name') ?? '#'.$c['segment_id']).'”',
                'revenue' => 'a cheltuit '.($c['op'] === 'at_least' ? 'cel puțin' : 'mai puțin de').' '.$c['value'].' lei'.(! empty($c['days']) ? ' în ultimele '.$c['days'].' zile' : ''),
                default => '?',
            };
        }

        return $parts ? implode(($definition['match'] ?? 'all') === 'any' ? ' SAU ' : ' ȘI ', $parts) : 'toate contactele';
    }
}
