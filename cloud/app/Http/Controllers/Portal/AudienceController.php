<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactEvent;
use App\Models\ContactList;
use App\Models\ContactListMember;
use App\Models\Segment;
use App\Services\AuditLogger;
use App\Services\ListService;
use App\Services\SegmentQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Audiența: liste statice și segmente dinamice (constructor de condiții, număr live, primele contacte). */
final class AudienceController extends PortalController
{
    public function index(): View
    {
        $lists = ContactList::query()->withCount('members')->orderBy('name')->get();
        $segments = Segment::query()->orderBy('name')->get();
        $names = $lists->pluck('name', 'id')->all();
        foreach ($segments as $segment) {
            $segment->contacts_count = SegmentQuery::apply(Contact::query(), $segment->definition)->count();
        }

        return view('portal.audience.index', ['organization' => $this->organization(), 'lists' => $lists, 'segments' => $segments, 'names' => $names]);
    }

    public function storeList(Request $request, AuditLogger $audit): RedirectResponse
    {
        $list = ContactList::create($request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:300']]));
        $audit->record('list.created', $list);

        return $this->to('portal.audience.list', ['list' => $list->id], 'Lista a fost creată.');
    }

    public function showList(Request $request, int $list): View
    {
        $model = ContactList::query()->findOrFail($list);
        $q = trim((string) $request->query('q', ''));

        return view('portal.audience.list', [
            'organization' => $this->organization(),
            'list' => $model,
            'members' => Contact::query()->whereIn('id', ContactListMember::query()->where('contact_list_id', $model->id)->select('contact_id'))
                ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('first_name', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")))
                ->orderBy('first_name')->paginate(50)->withQueryString(),
            'q' => $q,
        ]);
    }

    public function members(Request $request, ListService $lists, int $list): RedirectResponse
    {
        $model = ContactList::query()->findOrFail($list);
        $data = $request->validate(['action' => ['required', 'in:add,remove'], 'contact_id' => ['required', 'integer']]);
        $contact = Contact::query()->findOrFail($data['contact_id']);
        $data['action'] === 'add' ? $lists->add($model, $contact) : $lists->remove($model, $contact);

        return back()->with('ok', $data['action'] === 'add' ? 'Adăugat în listă.' : 'Scos din listă.');
    }

    public function destroyList(AuditLogger $audit, int $list): RedirectResponse
    {
        $model = ContactList::query()->findOrFail($list);
        $audit->record('list.deleted', $model, ['name' => $model->name]);
        $model->delete();

        return $this->to('portal.audience', [], 'Lista a fost ștearsă (contactele rămân).');
    }

    public function segment(?int $segment = null): View
    {
        $model = $segment ? Segment::query()->findOrFail($segment) : new Segment(['name' => '', 'definition' => ['match' => 'all', 'conditions' => [['type' => 'consent', 'channel' => 'email', 'op' => 'granted']]]]);
        $query = SegmentQuery::apply(Contact::query(), $model->definition);

        return view('portal.audience.segment', [
            'organization' => $this->organization(),
            'segment' => $model,
            'count' => $query->count(),
            'sample' => $query->orderByDesc('last_activity_at')->limit(20)->get(),
            'lists' => ContactList::query()->orderBy('name')->pluck('name', 'id'),
            'campaigns' => Campaign::query()->latest('id')->limit(50)->pluck('name', 'id'),
            'events' => ContactEvent::TYPES,
        ]);
    }

    public function saveSegment(Request $request, AuditLogger $audit, ?int $segment = null): RedirectResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:120']]);
        $definition = SegmentQuery::normalize((array) $request->input('definition', []));
        $model = $segment ? Segment::query()->findOrFail($segment) : new Segment;
        $model->fill(['name' => $request->input('name'), 'definition' => $definition]);
        $model->contacts_count = SegmentQuery::apply(Contact::query(), $definition)->count();
        $model->refreshed_at = now();
        $model->save();
        $audit->record('segment.saved', $model);

        return $this->to('portal.audience.segment', ['segment' => $model->id], "Segment salvat: {$model->contacts_count} contacte acum.");
    }

    public function destroySegment(AuditLogger $audit, int $segment): RedirectResponse
    {
        $model = Segment::query()->findOrFail($segment);
        $audit->record('segment.deleted', $model, ['name' => $model->name]);
        $model->delete();

        return $this->to('portal.audience', [], 'Segmentul a fost șters.');
    }
}
