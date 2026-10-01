<?php

declare(strict_types=1);

namespace App\Ai;

use App\Enums\AgentStatus;
use App\Enums\Channel;
use App\Models\Agent;
use App\Models\Conversation;
use App\Services\AgentConfiguration;
use App\Services\AgentTemplates;
use Illuminate\Support\Str;

/**
 * Rulează un set de evaluare (resources/evals/*.json) pe un agent temporar, cu informațiile firmei din set.
 * Fiecare caz = o conversație de test nouă (tool-urile nu creează date reale). Agentul și conversațiile se șterg la final.
 * Notare simplă, verificabilă: cel puțin un fragment așteptat, niciun tipar interzis, tool-ul așteptat (sau niciunul).
 */
final class Evaluation
{
    public function __construct(private readonly AgentRuntime $runtime) {}

    /** @return list<string> */
    public static function sets(): array
    {
        return array_map(fn ($f) => basename($f, '.json'), glob(resource_path('evals/*.json')) ?: []);
    }

    /**
     * Rulează în contextul firmei curente (costul intră în consumul ei).
     *
     * @param  callable(array<string, mixed>): void|null  $progress
     * @return array<string, mixed>
     */
    public function run(string $set, ?callable $progress = null): array
    {
        $definition = json_decode((string) file_get_contents(resource_path("evals/{$set}.json")), true, flags: JSON_THROW_ON_ERROR);
        [$model, $system] = AgentConfiguration::normalize([], array_replace_recursive(AgentTemplates::system($definition['template']), [
            'business_facts' => $definition['business_facts'],
            'contact_line' => $definition['contact_line'],
        ]));
        // agent temporar, creat direct (nu e un agent al firmei și nu intră în limita planului)
        $agent = Agent::create([
            'name' => 'Evaluare '.$set, 'status' => AgentStatus::Draft, 'default_language' => 'ro', 'template' => $definition['template'],
            'model_configuration' => $model, 'system_configuration' => $system,
        ]);

        $results = [];
        try {
            foreach ($definition['cases'] as $case) {
                $conversation = Conversation::create(['agent_id' => $agent->id, 'channel' => Channel::Web, 'status' => 'open', 'mode' => 'ai', 'is_test' => true, 'subject' => "eval {$set} {$case['id']}"]);
                $tools = [];
                $cost = 0;
                $reply = null;
                foreach ($case['messages'] as $message) {
                    $reply = $this->runtime->reply($conversation, $message);
                    $tools = array_merge($tools, array_map(fn ($e) => $e->tool, $reply->tools));
                    $cost += $reply->costMicroUsd;
                }
                $result = $this->grade($case, (string) $reply?->text, (string) $reply?->status, $tools) + ['cost_micro_usd' => $cost];
                $results[] = $result;
                if ($progress) {
                    $progress($result);
                }
            }
        } finally {
            Conversation::query()->where('agent_id', $agent->id)->delete();
            $agent->delete();
        }

        $passed = count(array_filter($results, fn ($r) => $r['passed']));

        return [
            'set' => $set,
            'name' => $definition['name'],
            'model' => $model['model'],
            'effort' => $model['effort'],
            'ran_at' => now()->toIso8601String(),
            'passed' => $passed,
            'total' => count($results),
            'score' => $results ? round($passed / count($results), 3) : 0,
            'cost_usd' => round(array_sum(array_column($results, 'cost_micro_usd')) / 1_000_000, 4),
            'results' => $results,
        ];
    }

    /**
     * @param  array<string, mixed>  $case
     * @param  list<string>  $tools
     * @return array<string, mixed>
     */
    public function grade(array $case, string $reply, string $status, array $tools): array
    {
        $plain = Str::lower(Str::ascii($reply));
        $failures = [];
        if ($status !== 'ok') {
            $failures[] = "status {$status}";
        }
        $expected = $case['expect_any'] ?? [];
        if ($expected && ! array_filter($expected, fn ($e) => str_contains($plain, Str::lower(Str::ascii($e))))) {
            $failures[] = 'lipsește: '.implode(' / ', $expected);
        }
        foreach ($case['forbid'] ?? [] as $pattern) {
            if (preg_match('/'.$pattern.'/iu', $reply)) {
                $failures[] = "interzis: {$pattern}";
            }
        }
        $tool = $case['expect_tool'] ?? null;
        if ($tool === 'none' && $tools) {
            $failures[] = 'tool neașteptat: '.implode(',', $tools);
        } elseif ($tool !== null && $tool !== 'none' && ! in_array($tool, $tools, true)) {
            $failures[] = "tool lipsă: {$tool}";
        }

        return ['id' => $case['id'], 'passed' => ! $failures, 'failures' => $failures, 'tools' => $tools, 'reply' => $reply];
    }
}
