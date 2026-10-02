<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Validation\Ro;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validează și completează configurația unui agent. Doar cheile cunoscute se păstrează;
 * orice cheie care seamănă a secret e respinsă (cheile API stau în mediul serverului, nu pe agent).
 */
final class AgentConfiguration
{
    public const TONES = ['professional', 'friendly', 'formal', 'concise'];

    public const ACTIONS = ['create_lead', 'request_human', 'search_knowledge'];

    public const LEAD_FIELDS = ['name', 'phone', 'email', 'company', 'requested_service', 'product', 'budget', 'preferred_date', 'notes'];

    public const FALLBACKS = ['collect_contact', 'handoff', 'apologize'];

    /** auto = Claude dacă e configurată cheia, altfel răspunsuri din informațiile firmei; local = mereu fără AI; claude = doar Claude */
    public const ENGINES = ['auto', 'local', 'claude'];

    /** Informațiile despre firmă sunt și baza de cunoștințe a agentului local, deci pot fi lungi. */
    public const MAX_FACTS = 60000;

    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /** Chei proprii care conțin cuvinte „suspecte”, dar nu sunt secrete. */
    private const NOT_SECRETS = ['max_output_tokens'];

    /**
     * @param  array<string, mixed>  $model
     * @param  array<string, mixed>  $system
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function normalize(array $model, array $system): array
    {
        self::rejectSecrets(['model_configuration' => $model, 'system_configuration' => $system]);

        $m = Validator::make($model, [
            'model' => ['sometimes', Rule::in(config('vitim.ai_models'))],
            'effort' => ['sometimes', Rule::in(['low', 'medium', 'high'])],
            'max_output_tokens' => ['sometimes', 'integer', 'min:256', 'max:16000'],
        ])->validate();

        $time = 'regex:/^([01]\d|2[0-3]):[0-5]\d$/';
        $rules = [
            'tone' => ['sometimes', Rule::in(self::TONES)],
            'languages' => ['sometimes', 'array', 'max:10'],
            'languages.*' => ['string', 'size:2'],
            'greeting' => ['sometimes', 'nullable', 'string', 'max:500'],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'business_facts' => ['sometimes', 'nullable', 'string', 'max:'.self::MAX_FACTS],
            'contact_line' => ['sometimes', 'nullable', 'string', 'max:300'],
            'business_hours' => ['sometimes', 'array'],
            'business_hours.timezone' => ['sometimes', 'timezone:all'],
            'handoff_rules' => ['sometimes', 'array'],
            'handoff_rules.on_request' => ['sometimes', 'boolean'],
            'handoff_rules.on_complaint' => ['sometimes', 'boolean'],
            'handoff_rules.on_low_confidence' => ['sometimes', 'boolean'],
            'lead_rules' => ['sometimes', 'array'],
            'lead_rules.required_fields' => ['sometimes', 'array'],
            'lead_rules.required_fields.*' => [Rule::in(self::LEAD_FIELDS)],
            'allowed_actions' => ['sometimes', 'array'],
            'allowed_actions.*' => [Rule::in(self::ACTIONS)],
            'knowledge_sources' => ['sometimes', 'array'],
            'knowledge_sources.*' => ['integer'],
            'fallback_behavior' => ['sometimes', Rule::in(self::FALLBACKS)],
            'engine' => ['sometimes', Rule::in(self::ENGINES)],
        ];
        foreach (self::DAYS as $day) {
            $rules["business_hours.days.{$day}"] = ['sometimes', 'array', 'max:3'];
            $rules["business_hours.days.{$day}.*"] = ['array', 'size:2'];
            $rules["business_hours.days.{$day}.*.*"] = ['string', $time];
        }
        $s = Validator::make($system, $rules, Ro::MESSAGES, Ro::ATTRIBUTES)->validate();

        $defaults = self::defaults();
        $model = array_replace($defaults[0], $m);
        $system = self::merge($defaults[1], $s);
        // regula GDPR nu se poate dezactiva: lead-ul se salvează doar cu acordul explicit al vizitatorului
        $system['lead_rules']['require_consent'] = true;
        $system['allowed_actions'] = array_values(array_unique($system['allowed_actions']));
        $system['languages'] = array_values(array_unique(array_map('strtolower', $system['languages'])));

        return [$model, $system];
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    public static function defaults(): array
    {
        return [
            ['model' => config('vitim.ai_models')[0], 'effort' => 'low', 'max_output_tokens' => 4000],
            [
                'tone' => 'professional',
                'languages' => ['ro'],
                'greeting' => null,
                'instructions' => null,
                'business_facts' => null,
                'contact_line' => null,
                'business_hours' => ['timezone' => 'Europe/Bucharest', 'days' => []],
                'handoff_rules' => ['on_request' => true, 'on_complaint' => true, 'on_low_confidence' => true],
                'lead_rules' => ['required_fields' => ['name', 'phone'], 'require_consent' => true],
                'allowed_actions' => ['create_lead', 'request_human'],
                'knowledge_sources' => [],
                'fallback_behavior' => 'collect_contact',
                'engine' => 'auto',
            ],
        ];
    }

    /**
     * Completează valorile lipsă din implicite. Obiectele se combină pe chei; listele (limbi, acțiuni,
     * câmpuri) se ÎNLOCUIESC, altfel o opțiune debifată ar rămâne activă din valorile implicite.
     *
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private static function merge(array $defaults, array $input): array
    {
        foreach ($input as $key => $value) {
            $isObject = fn ($v) => is_array($v) && $v !== [] && ! array_is_list($v);
            $defaults[$key] = $isObject($value) && isset($defaults[$key]) && $isObject($defaults[$key])
                ? self::merge($defaults[$key], $value)
                : $value;
        }

        return $defaults;
    }

    /** @param array<string, mixed> $input */
    private static function rejectSecrets(array $input, string $path = ''): void
    {
        foreach ($input as $key => $value) {
            $full = ltrim($path.'.'.$key, '.');
            if (is_string($key) && ! in_array($key, self::NOT_SECRETS, true) && preg_match('/secret|password|passwd|api[_-]?key|token|credential/i', $key)) {
                throw ValidationException::withMessages([$full => 'Configurația agentului nu poate conține secrete.']);
            }
            if (is_array($value)) {
                self::rejectSecrets($value, $full);
            }
        }
    }
}
