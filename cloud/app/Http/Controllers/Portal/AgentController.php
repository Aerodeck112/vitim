<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\Permission;
use App\Http\Validation\AgentRules;
use App\Models\Agent;
use App\Models\Site;
use App\Services\AgentConfiguration;
use App\Services\AgentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class AgentController extends PortalController
{
    public function index(): View
    {
        return view('portal.agents.index', [
            'organization' => $this->organization(),
            'agents' => Agent::query()->with('site')->orderBy('name')->get(),
            'sites' => Site::query()->orderBy('name')->get(),
            'canManage' => Gate::allows(Permission::ManageAgents->value),
        ]);
    }

    public function store(Request $request, AgentService $agents): RedirectResponse
    {
        $agent = $agents->create($request->validate(AgentRules::agent(true)));

        return $this->to('portal.agents.edit', ['agent' => $agent->id], 'Agent creat (ciornă).');
    }

    public function edit(int $agent): View
    {
        return view('portal.agents.edit', [
            'organization' => $this->organization(),
            'agent' => Agent::query()->findOrFail($agent),
            'sites' => Site::query()->orderBy('name')->get(),
            'canManage' => Gate::allows(Permission::ManageAgents->value),
            'tones' => AgentConfiguration::TONES,
            'fallbacks' => AgentConfiguration::FALLBACKS,
            'actions' => AgentConfiguration::ACTIONS,
            'leadFields' => AgentConfiguration::LEAD_FIELDS,
        ]);
    }

    public function update(Request $request, AgentService $agents, int $agent): RedirectResponse
    {
        $model = Agent::query()->findOrFail($agent);
        $data = $request->validate(AgentRules::agent(false) + [
            'tone' => ['sometimes', 'string'], 'languages' => ['sometimes', 'string', 'max:60'], 'greeting' => ['sometimes', 'nullable', 'string'],
            'instructions' => ['sometimes', 'nullable', 'string'], 'fallback_behavior' => ['sometimes', 'string'],
            'allowed_actions' => ['sometimes', 'array'], 'required_fields' => ['sometimes', 'array'],
        ]);
        // formularul trimite câmpuri plate; se convertesc în configurația validată de AgentConfiguration
        $system = $model->system_configuration;
        foreach (['tone', 'greeting', 'instructions', 'fallback_behavior'] as $key) {
            if (array_key_exists($key, $data)) {
                $system[$key] = $data[$key];
            }
        }
        if (isset($data['languages'])) {
            $system['languages'] = array_values(array_filter(array_map('trim', explode(',', $data['languages']))));
        }
        $system['allowed_actions'] = array_values($data['allowed_actions'] ?? []);
        $system['lead_rules']['required_fields'] = array_values($data['required_fields'] ?? []);
        foreach (['on_request', 'on_complaint', 'on_low_confidence'] as $rule) {
            $system['handoff_rules'][$rule] = $request->boolean("handoff_{$rule}");
        }

        $agents->update($model, array_intersect_key($data, array_flip(['name', 'site_id', 'status', 'default_language'])) + ['system_configuration' => $system]);

        return $this->to('portal.agents.edit', ['agent' => $model->id], 'Configurația a fost salvată.');
    }
}
