<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Ai\AiClient;
use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Site;
use App\Models\UsageRecord;
use App\Services\AiKey;
use App\Services\AuditLogger;
use App\Services\WidgetSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Agent AI (super admin): cheia AI a platformei și, pe fiecare site, dacă agentul răspunde cu AI și dacă chatul apare.
 * Cod de platformă: listează site-urile tuturor firmelor (withoutTenancy) doar pentru echipa VITIM.
 */
final class AiController extends Controller
{
    public const REASONS = [
        'ok' => 'Cheia funcționează. Agenții pot răspunde cu AI.',
        'not_configured' => 'Nu există nicio cheie. Lipește cheia mai jos.',
        'auth' => 'Cheia nu e acceptată: e greșită, incompletă sau a fost revocată. Copiaz-o din nou din Console → API Keys.',
        'billing' => 'Contul nu are credit. Adaugă credit în Console → Billing.',
        'model' => 'Modelul ales nu e disponibil pentru această cheie.',
        'rate_limited' => 'Prea multe cereri în acest moment. Încearcă din nou peste un minut.',
        'network' => 'Serverul nu poate ajunge la serviciul AI (rețea sau firewall la hosting).',
        'unavailable' => 'Serviciul AI nu răspunde acum. Încearcă din nou peste câteva minute.',
    ];

    public function show(Request $request): View
    {
        $this->authorizeSuperAdmin($request);
        $sites = Site::withoutTenancy()->with('organization')->orderBy('domain')->get();
        $agents = Agent::withoutTenancy()->whereIn('site_id', $sites->pluck('id'))->get()->groupBy('site_id');
        $cost = UsageRecord::withoutTenancy()->where('metric', 'ai_cost_micro_usd')
            ->where('period_date', '>=', now()->startOfMonth()->toDateString())
            ->select('organization_id', DB::raw('sum(quantity) as q'))->groupBy('organization_id')->pluck('q', 'organization_id');

        return view('admin.ai', [
            'source' => AiKey::source(),
            'masked' => AiKey::masked(),
            'model' => (string) (config('vitim.ai_models')[0] ?? ''),
            'sites' => $sites,
            'agents' => $agents,
            'chat' => $sites->mapWithKeys(fn (Site $s) => [$s->id => (bool) WidgetSettings::for($s)['enabled']]),
            'cost' => $cost,
            'check' => session('ai_check'),
        ]);
    }

    public function saveKey(Request $request, AiClient $client, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $data = $request->validate([
            'key' => ['required', 'string', 'min:20', 'max:300', 'regex:/^sk-ant-[A-Za-z0-9_\-]+$/'],
            'password' => ['required', 'string'],
        ], [
            'key.regex' => 'Cheia trebuie să înceapă cu „sk-ant-” și să nu conțină spații.',
            'key.min' => 'Cheia pare incompletă.',
        ]);
        $this->confirmPassword($request);
        AiKey::save($data['key']);
        $audit->record('platform.ai_key_saved', null, ['last4' => substr($data['key'], -4)]);

        return redirect()->route('admin.ai')->with('ai_check', $client->check($this->model()))->with('status', 'Cheia a fost salvată.');
    }

    public function clearKey(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $request->validate(['password' => ['required', 'string']]);
        $this->confirmPassword($request);
        AiKey::clear();
        $audit->record('platform.ai_key_removed');

        return redirect()->route('admin.ai')->with('status', 'Cheia din panou a fost ștearsă.');
    }

    public function test(Request $request, AiClient $client): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        return redirect()->route('admin.ai')->with('ai_check', $client->check($this->model()));
    }

    public function toggle(Request $request, int $site, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        $this->authorizeSuperAdmin($request);
        $data = $request->validate(['field' => ['required', 'in:ai,chat'], 'value' => ['required', 'boolean']]);
        $model = Site::withoutTenancy()->findOrFail($site);
        $on = (bool) $data['value'];
        if ($data['field'] === 'ai') {
            $model->forceFill(['ai_enabled' => $on])->save();
        } else {
            $model->forceFill(['widget_config' => array_replace((array) ($model->widget_config ?? []), ['enabled' => $on])])->save();
        }
        $audit->record($data['field'] === 'ai' ? 'site.ai_toggled' : 'site.chat_toggled', $model, ['on' => $on, 'organization_id' => $model->organization_id]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'on' => $on]);
        }

        return back()->with('status', $model->domain.': '.($data['field'] === 'ai' ? 'răspunsuri AI ' : 'chat pe site ').($on ? 'pornite' : 'oprite').'.');
    }

    public function bulk(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $on = (bool) $request->validate(['value' => ['required', 'boolean']])['value'];
        $count = Site::withoutTenancy()->where('ai_enabled', ! $on)->update(['ai_enabled' => $on]);
        $audit->record('site.ai_bulk', null, ['on' => $on, 'sites' => $count]);

        return back()->with('status', 'Răspunsuri AI '.($on ? 'pornite' : 'oprite').' pe toate site-urile.');
    }

    private function model(): string
    {
        return (string) (config('vitim.ai_models')[0] ?? 'claude-opus-5-5');
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->platform_role === PlatformRole::SuperAdmin, 403);
    }

    private function confirmPassword(Request $request): void
    {
        if (! Hash::check((string) $request->input('password'), (string) $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Parola contului nu e corectă.']);
        }
    }
}
