<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentVersion;
use App\Models\Site;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AgentService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
        private readonly EventRecorder $events,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): Agent
    {
        $this->assertSite($data['site_id'] ?? null);
        $limit = $this->context->organization()->subscription?->limit('agents');
        if ($limit !== null && Agent::query()->count() >= $limit) {
            throw ValidationException::withMessages(['name' => 'Planul curent nu permite mai mulți agenți.']);
        }
        $template = $data['template'] ?? null;
        if ($template !== null && ! array_key_exists($template, AgentTemplates::all())) {
            throw ValidationException::withMessages(['template' => 'Template necunoscut.']);
        }
        $system = array_replace_recursive($template ? AgentTemplates::system($template) : [], $data['system_configuration'] ?? []);
        [$model, $system] = AgentConfiguration::normalize($data['model_configuration'] ?? [], $system);

        return DB::transaction(function () use ($data, $model, $system, $template): Agent {
            $agent = Agent::create([
                'site_id' => $data['site_id'] ?? null,
                'name' => $data['name'],
                'status' => $data['status'] ?? AgentStatus::Draft->value,
                'default_language' => $data['default_language'] ?? $this->context->organization()->default_language,
                'template' => $template,
                'model_configuration' => $model,
                'system_configuration' => $system,
            ]);
            $this->snapshot($agent);
            $this->audit->record('agent.created', $agent);
            $this->events->record('agent.created', $agent);

            return $agent;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Agent $agent, array $data): Agent
    {
        if (array_key_exists('site_id', $data)) {
            $this->assertSite($data['site_id']);
        }
        if (array_key_exists('model_configuration', $data) || array_key_exists('system_configuration', $data)) {
            [$data['model_configuration'], $data['system_configuration']] = AgentConfiguration::normalize(
                $data['model_configuration'] ?? $agent->model_configuration,
                $data['system_configuration'] ?? $agent->system_configuration,
            );
        }

        return DB::transaction(function () use ($agent, $data): Agent {
            $agent->fill(array_intersect_key($data, array_flip(['site_id', 'name', 'status', 'default_language', 'model_configuration', 'system_configuration'])));
            $changed = array_keys($agent->getDirty());
            $agent->save();
            if (array_intersect($changed, ['model_configuration', 'system_configuration'])) {
                $this->snapshot($agent);
            }
            if ($changed) {
                $this->audit->record('agent.updated', $agent, ['fields' => implode(',', $changed)]);
            }

            return $agent;
        });
    }

    public function delete(Agent $agent): void
    {
        DB::transaction(function () use ($agent): void {
            $this->audit->record('agent.deleted', $agent);
            $agent->delete();
        });
    }

    /** Versiune nouă a configurației (istoric complet, pentru comparații și revenire). */
    private function snapshot(Agent $agent): void
    {
        $agent->versions()->create([
            'version' => (int) AgentVersion::query()->where('agent_id', $agent->getKey())->max('version') + 1,
            'model_configuration' => $agent->model_configuration,
            'system_configuration' => $agent->system_configuration,
            'created_by' => auth()->id(),
        ]);
    }

    private function assertSite(mixed $siteId): void
    {
        if ($siteId !== null && ! Site::query()->whereKey($siteId)->exists()) {
            throw ValidationException::withMessages(['site_id' => 'Site inexistent.']);
        }
    }
}
