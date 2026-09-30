<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\ContactSource;
use App\Enums\LeadIntent;
use App\Enums\LeadStatus;
use App\Enums\Permission;
use App\Http\Validation\ContactRules;
use App\Models\Contact;
use App\Models\ContactConsent;
use App\Models\Lead;
use App\Services\ConsentService;
use App\Services\ContactService;
use App\Services\DuplicateContactException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class ContactController extends PortalController
{
    public function index(Request $request): View
    {
        $query = Contact::query()->latest('id');
        if ($q = trim((string) $request->query('q'))) {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(fn ($w) => $w->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
                ->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('company', 'like', $like));
        }

        return view('portal.contacts.index', [
            'organization' => $this->organization(),
            'contacts' => $query->paginate(25)->withQueryString(),
            'q' => $q,
            'canManage' => Gate::allows(Permission::ManageContacts->value),
        ]);
    }

    public function create(): View
    {
        return view('portal.contacts.form', ['organization' => $this->organization(), 'contact' => null]);
    }

    public function store(Request $request, ContactService $contacts): RedirectResponse
    {
        try {
            $contact = $contacts->create($request->validate(ContactRules::contact(true)), ContactSource::Manual);
        } catch (DuplicateContactException $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()])->with('duplicate', $e->existingContactId);
        }

        return $this->to('portal.contacts.show', ['contact' => $contact->id], 'Contact creat.');
    }

    public function show(ConsentService $consents, int $contact): View
    {
        $model = Contact::query()->with('identities')->findOrFail($contact);

        return view('portal.contacts.show', [
            'organization' => $this->organization(),
            'contact' => $model,
            'consents' => $consents->matrix($model),
            'history' => ContactConsent::query()->where('contact_id', $model->id)->orderByDesc('occurred_at')->orderByDesc('id')->limit(30)->get(),
            'leads' => Lead::query()->where('contact_id', $model->id)->latest('id')->get(),
            'channels' => Channel::consentChannels(),
            'purposes' => ConsentPurpose::cases(),
            'statuses' => ConsentStatus::cases(),
            'intents' => LeadIntent::cases(),
            'leadStatuses' => LeadStatus::cases(),
            'can' => [
                'manage' => Gate::allows(Permission::ManageContacts->value),
                'consent' => Gate::allows(Permission::ManageConsent->value),
                'leads' => Gate::allows(Permission::ManageLeads->value),
                'delete' => Gate::allows(Permission::DeleteData->value),
            ],
        ]);
    }

    public function edit(int $contact): View
    {
        return view('portal.contacts.form', ['organization' => $this->organization(), 'contact' => Contact::query()->findOrFail($contact)]);
    }

    public function update(Request $request, ContactService $contacts, int $contact): RedirectResponse
    {
        $model = Contact::query()->findOrFail($contact);
        try {
            $contacts->update($model, $request->validate(ContactRules::contact(false)));
        } catch (DuplicateContactException $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()]);
        }

        return $this->to('portal.contacts.show', ['contact' => $model->id], 'Contact actualizat.');
    }

    public function destroy(ContactService $contacts, int $contact): RedirectResponse
    {
        $contacts->delete(Contact::query()->findOrFail($contact));

        return $this->to('portal.contacts.index', [], 'Contactul și datele lui au fost șterse.');
    }

    public function consent(Request $request, ConsentService $consents, int $contact): RedirectResponse
    {
        $model = Contact::query()->findOrFail($contact);
        $data = $request->validate(ContactRules::consent());
        $consents->record($model, Channel::from($data['channel']), ConsentPurpose::from($data['purpose']), ConsentStatus::from($data['status']),
            $data['source'], [], $request->ip(), $request->userAgent(), $request->user());

        return $this->to('portal.contacts.show', ['contact' => $model->id], 'Consimțământ înregistrat.');
    }
}
