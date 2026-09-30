<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\LeadStatus;
use App\Enums\Permission;
use App\Http\Validation\LeadRules;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Membership;
use App\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class LeadController extends PortalController
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $query = Lead::query()->with(['contact', 'assignee'])->latest('id');
        if (is_string($status) && LeadStatus::tryFrom($status)) {
            $query->where('status', $status);
        }

        return view('portal.leads.index', [
            'organization' => $this->organization(),
            'leads' => $query->paginate(25)->withQueryString(),
            'status' => $status,
            'statuses' => LeadStatus::cases(),
            'members' => Membership::query()->with('user')->get(),
            'canManage' => Gate::allows(Permission::ManageLeads->value),
        ]);
    }

    public function store(Request $request, LeadService $leads): RedirectResponse
    {
        $data = $request->validate(['contact_id' => ['required', 'integer']] + LeadRules::lead());
        $contact = Contact::query()->findOrFail($data['contact_id']);
        $leads->create($contact, $data + ['source' => 'manual']);

        return $this->to('portal.contacts.show', ['contact' => $contact->id], 'Lead creat.');
    }

    public function update(Request $request, LeadService $leads, int $lead): RedirectResponse
    {
        if ($request->has('assigned_to')) {
            $request->merge(['assigned_to' => $request->input('assigned_to') ?: null]); // „neatribuit” din formular
        }
        $data = $request->validate(LeadRules::lead());
        $leads->update(Lead::query()->findOrFail($lead), $data);

        return back()->with('ok', 'Lead actualizat.');
    }
}
