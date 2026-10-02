<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\Permission;
use App\Http\Validation\AgentRules;
use App\Http\Validation\Ro;
use App\Models\Agent;
use App\Models\Site;
use App\Models\User;
use App\Services\AgentConfiguration;
use App\Services\AgentService;
use App\Services\AgentTemplates;
use App\Services\AuditLogger;
use App\Services\WidgetSettings;
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
            'templates' => AgentTemplates::all(),
        ]);
    }

    public function store(Request $request, AgentService $agents): RedirectResponse
    {
        $agent = $agents->create($request->validate(AgentRules::agent(true)));

        return $this->to('portal.agents.edit', ['agent' => $agent->id], 'Agent creat (ciornă).');
    }

    public function edit(int $agent): View
    {
        $model = Agent::query()->findOrFail($agent);
        $versions = $model->versions()->limit(20)->get();

        return view('portal.agents.edit', [
            'organization' => $this->organization(),
            'agent' => $model,
            'versions' => $versions,
            'authors' => User::query()->whereIn('id', $versions->pluck('created_by')->filter())->pluck('name', 'id'),
            'sites' => Site::query()->orderBy('name')->get(),
            'canManage' => Gate::allows(Permission::ManageAgents->value),
            'tones' => AgentConfiguration::TONES,
            'fallbacks' => AgentConfiguration::FALLBACKS,
            'actions' => AgentConfiguration::ACTIONS,
            'leadFields' => AgentConfiguration::LEAD_FIELDS,
        ]);
    }

    /** Aspectul widgetului pe site-ul agentului (formular separat, păstrat pentru compatibilitate). */
    public function widget(Request $request, AuditLogger $audit, int $agent): RedirectResponse
    {
        $model = Agent::query()->with('site')->findOrFail($agent);
        abort_if($model->site === null, 422, 'Agentul nu e legat de un site.');
        $request->validate(self::WIDGET_RULES, Ro::MESSAGES, Ro::ATTRIBUTES);
        $this->saveWidget($request, $audit, $model);

        return $this->to('portal.agents.edit', ['agent' => $model->id], 'Setările widgetului au fost salvate.');
    }

    /** Tot formularul din pagina agentului: configurația și (dacă agentul are site) widgetul, cu un singur buton. */
    public function update(Request $request, AgentService $agents, AuditLogger $audit, int $agent): RedirectResponse
    {
        $model = Agent::query()->findOrFail($agent);
        // regulile de aici au prioritate (câmpuri goale permise: se completează cu valori implicite)
        $data = $request->validate([
            'default_language' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'],
            'engine' => ['sometimes', 'nullable', 'in:'.implode(',', AgentConfiguration::ENGINES)],
            'tone' => ['sometimes', 'nullable', 'string'], 'languages' => ['sometimes', 'nullable', 'string', 'max:60'], 'greeting' => ['sometimes', 'nullable', 'string', 'max:500'],
            'business_facts' => ['sometimes', 'nullable', 'string', 'max:'.AgentConfiguration::MAX_FACTS], 'contact_line' => ['sometimes', 'nullable', 'string', 'max:300'],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:4000'], 'fallback_behavior' => ['sometimes', 'nullable', 'string'],
            'allowed_actions' => ['sometimes', 'array'], 'required_fields' => ['sometimes', 'array'],
        ] + AgentRules::agent(false) + ($request->has('widget_form') ? self::WIDGET_RULES : []), Ro::MESSAGES, Ro::ATTRIBUTES);
        // formularul trimite câmpuri plate; se convertesc în configurația validată de AgentConfiguration
        $system = $model->system_configuration;
        foreach (['tone', 'greeting', 'instructions', 'business_facts', 'contact_line', 'fallback_behavior', 'engine'] as $key) {
            if (array_key_exists($key, $data) && ($data[$key] !== null || ! in_array($key, ['tone', 'fallback_behavior', 'engine'], true))) {
                $system[$key] = $data[$key];
            }
        }
        if (array_key_exists('languages', $data)) {
            $system['languages'] = array_values(array_filter(array_map(fn ($l) => strtolower(trim($l)), explode(',', (string) $data['languages'])))) ?: ['ro'];
        }
        $system['allowed_actions'] = array_values($data['allowed_actions'] ?? []);
        $system['lead_rules']['required_fields'] = array_values($data['required_fields'] ?? []);
        foreach (['on_request', 'on_complaint', 'on_low_confidence'] as $rule) {
            $system['handoff_rules'][$rule] = $request->boolean("handoff_{$rule}");
        }
        $fields = array_intersect_key($data, array_flip(['name', 'site_id', 'status', 'default_language']));
        if (array_key_exists('default_language', $fields) && ! $fields['default_language']) {
            $fields['default_language'] = 'ro';
        }

        $agents->update($model, $fields + ['system_configuration' => $system]);
        $model->refresh()->load('site');
        if ($request->has('widget_form') && $model->site) {
            $this->saveWidget($request, $audit, $model);
        }

        return $this->to('portal.agents.edit', ['agent' => $model->id], 'Am salvat modificările.');
    }

    private const WIDGET_RULES = [
        'color' => ['nullable', 'string', 'max:7'], 'position' => ['nullable', 'in:left,right'], 'title' => ['nullable', 'string', 'max:60'],
        'launcher' => ['nullable', 'string', 'max:40'], 'privacy_url' => ['nullable', 'url', 'max:255'],
        'avatar_url' => ['nullable', 'url:https', 'max:255'], 'welcome_title' => ['nullable', 'string', 'max:40'], 'welcome_text' => ['nullable', 'string', 'max:120'],
        'quick_replies' => ['nullable', 'string', 'max:400'], 'proactive_delay' => ['nullable', 'integer', 'min:0', 'max:300'], 'proactive_text' => ['nullable', 'string', 'max:140'],
        'hours_start' => ['nullable', 'date_format:H:i'], 'hours_end' => ['nullable', 'date_format:H:i'],
    ];

    private function saveWidget(Request $request, AuditLogger $audit, Agent $model): void
    {
        $model->site->forceFill(['widget_config' => WidgetSettings::normalize($request->all() + [
            'enabled' => $request->boolean('enabled'), 'weekends' => $request->boolean('weekends'),
            'email_capture' => $request->boolean('email_capture'), 'sound' => $request->boolean('sound'),
        ])])->save();
        $audit->record('widget.updated', $model->site);
    }
}
