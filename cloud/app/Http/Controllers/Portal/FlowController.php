<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Models\ContactEvent;
use App\Models\ContactList;
use App\Models\Flow;
use App\Models\FlowRun;
use App\Models\FlowStep;
use App\Models\Segment;
use App\Services\FlowService;
use App\Services\FlowTemplates;
use App\Services\ShopEvents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Automatizările: bibliotecă de șabloane, constructorul de pași și statisticile pe pas. */
final class FlowController extends PortalController
{
    public function index(FlowService $flows): View
    {
        $list = Flow::query()->latest('id')->get();

        return view('portal.flows.index', [
            'organization' => $this->organization(),
            'flows' => $list,
            'active' => FlowRun::query()->where('status', 'active')->selectRaw('flow_id, count(*) as n')->groupBy('flow_id')->pluck('n', 'flow_id'),
            'templates' => FlowTemplates::all(),
        ]);
    }

    public function store(Request $request, FlowService $flows): RedirectResponse
    {
        $data = $request->validate(['template' => ['nullable', Rule::in(array_keys(FlowTemplates::all()))], 'name' => ['nullable', 'string', 'max:160']]);
        $flow = $flows->create((string) ($data['name'] ?? ''), $data['template'] ?? null, $request->user());

        return $this->to('portal.flows.show', ['flow' => $flow->id], 'Automatizarea a fost creată ca ciornă. Verifică textele, apoi pornește-o.');
    }

    public function show(FlowService $flows, int $flow): View
    {
        $model = Flow::query()->findOrFail($flow);
        $steps = FlowStep::query()->where('flow_id', $model->id)->orderBy('position')->get();

        return view('portal.flows.show', [
            'organization' => $this->organization(),
            'flow' => $model,
            'steps' => $steps->groupBy(fn (FlowStep $s) => ($s->parent_id ?? 0).':'.($s->branch ?? '')),
            'stats' => $flows->stats($model),
            'revenue' => ShopEvents::revenue(null, $model->id),
            'lists' => ContactList::query()->orderBy('name')->pluck('name', 'id'),
            'segments' => Segment::query()->orderBy('name')->pluck('name', 'id'),
            'events' => ContactEvent::TYPES,
            'recent' => FlowRun::query()->where('flow_id', $model->id)->with('contact')->latest('id')->limit(15)->get(),
        ]);
    }

    public function update(Request $request, FlowService $flows, int $flow): RedirectResponse
    {
        $model = Flow::query()->findOrFail($flow);
        $flows->updateFlow($model, $request->all());

        return $this->to('portal.flows.show', ['flow' => $model->id], 'Setările automatizării au fost salvate.');
    }

    public function status(Request $request, FlowService $flows, int $flow): RedirectResponse
    {
        $model = Flow::query()->findOrFail($flow);
        $status = $request->validate(['status' => ['required', Rule::in(['live', 'paused'])]])['status'];
        $flows->setStatus($model, $status);

        return $this->to('portal.flows.show', ['flow' => $model->id], $status === 'live' ? 'Automatizarea e pornită: contactele noi care îndeplinesc declanșatorul intră în ea.' : 'Automatizarea e oprită. Contactele rămân pe loc și continuă la repornire.');
    }

    public function addStep(Request $request, FlowService $flows, int $flow): RedirectResponse
    {
        $model = Flow::query()->findOrFail($flow);
        $data = $request->validate(['type' => ['required', Rule::in(array_keys(FlowStep::TYPES))], 'after' => ['nullable', 'integer'], 'parent' => ['nullable', 'integer'], 'branch' => ['nullable', Rule::in(['yes', 'no'])]]);
        $after = ! empty($data['after']) ? FlowStep::query()->where('flow_id', $model->id)->findOrFail($data['after']) : null;
        $parent = ! empty($data['parent']) ? FlowStep::query()->where('flow_id', $model->id)->where('type', 'condition')->findOrFail($data['parent']) : null;
        $step = $flows->addStep($model, $data['type'], $after, $parent, $parent ? ($data['branch'] ?? 'yes') : null);

        return redirect()->to(route('portal.flows.show', [$this->organization()->slug, $model->id]).'#step-'.$step->id)->with('ok', 'Pas adăugat.');
    }

    public function updateStep(Request $request, FlowService $flows, int $flow, int $step): RedirectResponse
    {
        $model = FlowStep::query()->where('flow_id', Flow::query()->findOrFail($flow)->id)->findOrFail($step);
        $flows->updateStep($model, $request->all());

        return redirect()->to(route('portal.flows.show', [$this->organization()->slug, $flow]).'#step-'.$model->id)->with('ok', 'Pas salvat.');
    }

    public function deleteStep(FlowService $flows, int $flow, int $step): RedirectResponse
    {
        $model = FlowStep::query()->where('flow_id', Flow::query()->findOrFail($flow)->id)->findOrFail($step);
        $flows->deleteStep($model);

        return $this->to('portal.flows.show', ['flow' => $flow], 'Pas șters.');
    }

    public function destroy(int $flow): RedirectResponse
    {
        $model = Flow::query()->findOrFail($flow);
        abort_if($model->status === 'live', 422, 'Oprește automatizarea înainte să o ștergi.');
        $model->delete();

        return $this->to('portal.flows.index', [], 'Automatizarea a fost ștearsă.');
    }
}
