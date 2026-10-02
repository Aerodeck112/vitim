<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\Channel;
use App\Enums\MessageStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\ChannelAccount;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\Segment;
use App\Services\AuditLogger;
use App\Services\CampaignRenderer;
use App\Services\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Campaniile firmei: ciornă → test → aprobare (acum sau programat) → trimitere în tranșe → statistici. */
final class CampaignController extends PortalController
{
    public function index(): View
    {
        $campaigns = Campaign::query()->latest('id')->paginate(30);

        return view('portal.campaigns.index', [
            'organization' => $this->organization(),
            'campaigns' => $campaigns,
            'stats' => CampaignRecipient::query()->whereIn('campaign_id', $campaigns->pluck('id'))->selectRaw('campaign_id, status, count(*) as n')
                ->groupBy('campaign_id', 'status')->get()->groupBy('campaign_id')->map(fn ($rows) => $rows->pluck('n', 'status')),
            'accounts' => ChannelAccount::query()->pluck('status', 'channel'),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'channel' => ['required', Rule::in(['email', 'sms', 'whatsapp'])]]);
        $campaign = Campaign::create($data + ['status' => 'draft', 'created_by' => $request->user()->id, 'audience' => [],
            'template' => $data['channel'] === 'whatsapp' ? ['language' => 'ro', 'variables' => ['{{prenume}}']] : null]);
        $audit->record('campaign.created', $campaign);

        return $this->to('portal.campaigns.show', ['campaign' => $campaign->id], 'Campanie creată. Scrie mesajul și alege cui pleacă.');
    }

    public function show(CampaignService $service, int $campaign): View
    {
        $model = Campaign::query()->with('approver')->findOrFail($campaign);
        $account = $service->account($model->channel);
        $preview = app(CampaignRenderer::class)->render($model, $this->organization(), new Contact(['first_name' => 'Maria', 'last_name' => 'Popescu']),
            route('unsubscribe', 'TEST'), (bool) $account?->setting('ascii', true));

        return view('portal.campaigns.show', [
            'organization' => $this->organization(),
            'campaign' => $model,
            'account' => $account,
            'estimate' => $model->editable() ? $service->estimate($model) : null,
            'stats' => $model->stats(),
            'recipients' => $model->editable() ? collect() : CampaignRecipient::query()->where('campaign_id', $model->id)->with('contact')
                ->orderByRaw("case status when 'failed' then 0 when 'excluded' then 2 else 1 end")->orderBy('id')->paginate(50),
            'preview' => $preview,
            'lists' => ContactList::query()->orderBy('name')->get(),
            'segments' => Segment::query()->orderBy('name')->get(),
            'engagement' => $model->editable() ? null : $model->engagement(),
            'smsParts' => $model->channel === Channel::Sms ? CampaignRenderer::smsParts($preview['body']) : null,
        ]);
    }

    public function update(Request $request, AuditLogger $audit, int $campaign): RedirectResponse
    {
        $model = Campaign::query()->findOrFail($campaign);
        abort_unless($model->editable(), 422, 'Campania a fost aprobată și nu se mai poate modifica.');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:'.($model->channel === Channel::Sms ? 900 : 20000)],
            'template_name' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9_]+$/'],
            'template_language' => ['nullable', 'string', 'max:10'],
            'template_variables' => ['nullable', 'string', 'max:1000'],
            'include' => ['nullable', 'array'], 'include.*' => ['regex:/^(list|segment):\d+$/'],
            'exclude' => ['nullable', 'array'], 'exclude.*' => ['regex:/^(list|segment):\d+$/'],
        ], ['template_name.regex' => 'Numele șablonului are doar litere mici, cifre și „_”, exact ca în WhatsApp Manager.']);
        $model->fill([
            'name' => $data['name'], 'subject' => $data['subject'] ?? null, 'body' => $request->has('body') ? ($data['body'] ?? null) : $model->body,
            'audience' => array_filter(['include' => array_values($data['include'] ?? []), 'exclude' => array_values($data['exclude'] ?? [])]),
        ]);
        if ($model->channel === Channel::WhatsApp) {
            $model->template = [
                'name' => $data['template_name'] ?? '', 'language' => $data['template_language'] ?: 'ro',
                'variables' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['template_variables'] ?? ''))), fn ($v) => $v !== '')),
            ];
        }
        $model->save();
        $audit->record('campaign.updated', $model);

        return $this->to('portal.campaigns.show', ['campaign' => $model->id], 'Campania a fost salvată.');
    }

    public function test(Request $request, CampaignService $service, int $campaign): RedirectResponse
    {
        $model = Campaign::query()->findOrFail($campaign);
        $result = $service->sendTest($model, (string) $request->input('test_to', ''), $request->user());

        return $result->status === MessageStatus::Sent
            ? $this->to('portal.campaigns.show', ['campaign' => $model->id], 'Mesajul de test a plecat. Verifică cum arată înainte de trimitere.')
            : $this->to('portal.campaigns.show', ['campaign' => $model->id])->withErrors(['test' => 'Testul a eșuat: '.$result->error]);
    }

    public function approve(Request $request, CampaignService $service, int $campaign): RedirectResponse
    {
        $model = Campaign::query()->findOrFail($campaign);
        $request->validate(['confirm' => ['accepted'], 'when' => ['nullable', 'date', 'after:now']],
            ['confirm.accepted' => 'Bifează confirmarea înainte de trimitere.', 'when.after' => 'Ora programată trebuie să fie în viitor.']);
        $at = $request->filled('when') ? Carbon::parse((string) $request->input('when'), 'Europe/Bucharest')->utc() : null;
        $count = $service->approve($model, $request->user(), $at);

        return $this->to('portal.campaigns.show', ['campaign' => $model->id], $at
            ? "Campania e programată pentru {$at->setTimezone('Europe/Bucharest')->format('d.m.Y H:i')} către {$count} destinatari."
            : "Campania a pornit către {$count} destinatari. Mesajele pleacă în tranșe, în câteva minute.");
    }

    public function action(Request $request, CampaignService $service, int $campaign): RedirectResponse
    {
        $model = Campaign::query()->findOrFail($campaign);
        $action = $request->validate(['action' => ['required', Rule::in(['pause', 'resume', 'cancel'])]])['action'];
        $service->{$action}($model);

        return $this->to('portal.campaigns.show', ['campaign' => $model->id], ['pause' => 'Campania a fost oprită.', 'resume' => 'Campania continuă.', 'cancel' => 'Campania a fost anulată.'][$action]);
    }
}
