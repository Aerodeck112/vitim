<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Reports\ServiceCatalog;
use App\Services\Announcements;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Noutățile VITIM către clienți (doar echipa VITIM): anunțuri, rezumatul lunar automat, livrări. */
final class AnnouncementController extends Controller
{
    public function __construct(private readonly Announcements $service, private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $items = Announcement::query()->latest('id')->paginate(20);
        $stats = AnnouncementDelivery::withoutTenancy()->whereIn('announcement_id', $items->pluck('id'))
            ->selectRaw("announcement_id, count(*) as n, sum(case when opened_at is not null then 1 else 0 end) as o, sum(case when status = 'failed' then 1 else 0 end) as f")
            ->groupBy('announcement_id')->get()->keyBy('announcement_id');

        return view('admin.announcements.index', ['items' => $items, 'stats' => $stats, 'settings' => Announcements::settings()]);
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate(['digest_day' => ['required', 'integer', 'min:1', 'max:28'], 'roles' => ['required', Rule::in(['owners', 'all'])]]);
        PlatformSetting::put('announcements', ['auto_updates' => $request->boolean('auto_updates'), 'digest' => $request->boolean('digest'),
            'digest_day' => (int) $data['digest_day'], 'roles' => $data['roles']]);
        $this->audit->record('announcement.settings', null, Announcements::settings());

        return redirect()->route('admin.announcements.index')->with('ok', 'Setările noutăților au fost salvate.');
    }

    public function create(): View
    {
        return $this->form(new Announcement(['kind' => 'news', 'include_work' => true, 'audience' => ['services' => [], 'roles' => Announcements::settings()['roles']],
            'intro' => 'Avem o veste bună pentru {{firma}}:', 'cta_label' => 'Deschide panoul', 'cta_url' => route('login')]));
    }

    public function store(Request $request): RedirectResponse
    {
        $a = new Announcement(['status' => 'draft', 'created_by' => $request->user()->id]);
        $this->fill($a, $request);
        $this->audit->record('announcement.created', $a);

        return redirect()->route('admin.announcements.edit', $a->id)->with('ok', 'Ciorna a fost salvată. Verifică previzualizarea, trimite-ți un test, apoi programeaz-o.');
    }

    public function edit(int $announcement): View
    {
        return $this->form(Announcement::query()->findOrFail($announcement));
    }

    public function update(Request $request, int $announcement): RedirectResponse
    {
        $a = Announcement::query()->findOrFail($announcement);
        abort_unless($a->editable(), 422, 'Anunțul a fost trimis și nu se mai poate modifica.');
        $this->fill($a, $request);

        return redirect()->route('admin.announcements.edit', $a->id)->with('ok', 'Salvat.');
    }

    /** Emailul exact, pentru o firmă aleasă (în iframe). */
    public function preview(Request $request, int $announcement): Response
    {
        $a = Announcement::query()->findOrFail($announcement);
        $org = Organization::query()->find((int) $request->query('org')) ?? $this->service->organizations($a)->first() ?? Organization::query()->first();
        abort_unless($org, 404);
        $mail = $this->service->render($a, $org, $request->user());

        return response($mail['html'])->header('Content-Security-Policy', "default-src 'none'; img-src * data:; style-src 'unsafe-inline'");
    }

    public function test(Request $request, int $announcement): RedirectResponse
    {
        $a = Announcement::query()->findOrFail($announcement);
        $org = Organization::query()->find((int) $request->input('org')) ?? $this->service->organizations($a)->first();
        if (! $org) {
            throw ValidationException::withMessages(['send' => 'Nicio firmă nu se potrivește publicului ales.']);
        }
        $this->service->sendTest($a, $request->user(), $org);

        return back()->with('ok', 'Testul (cu datele firmei '.$org->name.') a plecat la '.$request->user()->email.'.');
    }

    public function send(Request $request, int $announcement): RedirectResponse
    {
        $a = Announcement::query()->findOrFail($announcement);
        abort_unless($a->editable(), 422);
        $request->validate(['confirm' => ['accepted'], 'when' => ['nullable', 'date']], ['confirm.accepted' => 'Bifează confirmarea.']);
        $at = $request->filled('when') ? Carbon::parse((string) $request->input('when'), 'Europe/Bucharest')->utc() : now();
        $this->service->schedule($a, $at);
        $this->audit->record('announcement.scheduled', $a, ['at' => $at->toIso8601String(), 'recipients' => $this->service->count($a)]);

        return redirect()->route('admin.announcements.edit', $a->id)->with('ok', $at->isFuture() ? 'Programat pentru '.$at->setTimezone('Europe/Bucharest')->format('d.m.Y H:i').'.' : 'Se trimite acum, în tranșe de câte '.Announcements::PER_RUN.' la 5 minute.');
    }

    public function cancel(int $announcement): RedirectResponse
    {
        $a = Announcement::query()->findOrFail($announcement);
        if (in_array($a->status, ['scheduled', 'sending'], true)) {
            $a->forceFill(['status' => $a->status === 'scheduled' ? 'draft' : 'cancelled', 'scheduled_at' => null])->save();
            $this->audit->record('announcement.cancelled', $a);
        }

        return back()->with('ok', 'Trimiterea a fost oprită.');
    }

    public function destroy(int $announcement): RedirectResponse
    {
        $a = Announcement::query()->findOrFail($announcement);
        abort_unless($a->status === 'draft', 422, 'Se pot șterge doar ciornele.');
        $this->audit->record('announcement.deleted', $a, ['title' => $a->title]);
        $a->delete();

        return redirect()->route('admin.announcements.index')->with('ok', 'Ciorna a fost ștearsă.');
    }

    private function form(Announcement $a): View
    {
        return view('admin.announcements.edit', [
            'a' => $a, 'services' => ServiceCatalog::SERVICES,
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name']),
            'recipients' => $a->exists ? $this->service->count($a) : null,
            'deliveries' => $a->exists ? AnnouncementDelivery::withoutTenancy()->where('announcement_id', $a->id)->with('organization')->latest('id')->limit(50)->get() : collect(),
        ]);
    }

    private function fill(Announcement $a, Request $request): void
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['update', 'news'])], 'title' => ['required', 'string', 'max:160'], 'subject' => ['required', 'string', 'max:200'],
            'intro' => ['nullable', 'string', 'max:1000'], 'body' => ['nullable', 'string', 'max:20000'],
            'cta_label' => ['nullable', 'string', 'max:60'], 'cta_url' => ['nullable', 'url:https,http', 'max:300'],
            'services' => ['nullable', 'array'], 'services.*' => [Rule::in(array_keys(ServiceCatalog::SERVICES))], 'roles' => ['required', Rule::in(['owners', 'all'])],
        ]);
        $a->fill([
            'kind' => $a->kind === 'digest' ? 'digest' : $data['kind'], 'title' => $data['title'], 'subject' => $data['subject'],
            'intro' => $data['intro'] ?? null, 'body' => $data['body'] ?? null, 'cta_label' => $data['cta_label'] ?? null, 'cta_url' => $data['cta_url'] ?? null,
            'include_work' => $request->boolean('include_work'), 'audience' => ['services' => array_values($data['services'] ?? []), 'roles' => $data['roles']],
        ])->save();
    }
}
