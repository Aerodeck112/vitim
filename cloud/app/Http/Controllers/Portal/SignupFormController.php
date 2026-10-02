<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Models\ChannelAccount;
use App\Models\ContactList;
use App\Models\FormSubmission;
use App\Models\SignupForm;
use App\Models\Site;
use App\Services\AuditLogger;
use App\Services\SignupFormService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Formularele de abonare (ca „Sign-up forms” din Klaviyo): creare, design, reguli de afișare, publicare, înscrieri. */
final class SignupFormController extends PortalController
{
    public function index(): View
    {
        return view('portal.forms.index', [
            'organization' => $this->organization(),
            'forms' => SignupForm::query()->with('list')->latest('id')->get(),
            'types' => SignupForm::TYPES,
            'week' => FormSubmission::query()->where('created_at', '>=', now()->subDays(7))->count(),
        ]);
    }

    public function store(Request $request, SignupFormService $forms): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(array_keys(SignupForm::TYPES))], 'name' => ['nullable', 'string', 'max:120']]);
        $form = $forms->create($data['type'], (string) ($data['name'] ?? ''), $this->organization());

        return $this->to('portal.forms.show', ['form' => $form->id], 'Formularul a fost creat. Personalizează-l, apoi apasă „Publică”.');
    }

    public function show(int $form): View
    {
        $model = SignupForm::query()->findOrFail($form);

        return view('portal.forms.show', [
            'organization' => $this->organization(),
            'form' => $model,
            'payload' => SignupFormService::publicPayload($model, $this->organization()),
            'lists' => ContactList::query()->orderBy('name')->pluck('name', 'id'),
            'sites' => Site::query()->orderBy('domain')->pluck('domain', 'id'),
            'hasEmail' => ChannelAccount::query()->where('channel', 'email')->exists(),
            'submissions' => FormSubmission::query()->where('signup_form_id', $model->id)->with('contact')->latest('id')->paginate(20),
        ]);
    }

    public function update(Request $request, SignupFormService $forms, int $form): RedirectResponse
    {
        $model = SignupForm::query()->findOrFail($form);
        $request->validate(['name' => ['required', 'string', 'max:120'], 'image_url' => ['nullable', 'url:https', 'max:500']],
            ['image_url.url' => 'Adresa imaginii trebuie să înceapă cu https://']);
        $forms->update($model, $request->all());

        return $this->to('portal.forms.show', ['form' => $model->id], $model->status === 'live' ? 'Salvat. Modificările apar pe site imediat.' : 'Formularul a fost salvat.');
    }

    public function status(Request $request, SignupFormService $forms, int $form): RedirectResponse
    {
        $model = SignupForm::query()->findOrFail($form);
        $forms->setStatus($model, (string) $request->input('status'));

        return $this->to('portal.forms.show', ['form' => $model->id], $model->status === 'live' ? 'Formularul e publicat pe site.' : 'Formularul a fost oprit.');
    }

    public function destroy(AuditLogger $audit, int $form): RedirectResponse
    {
        $model = SignupForm::query()->findOrFail($form);
        $audit->record('signup_form.deleted', $model, ['name' => $model->name]);
        $model->delete();

        return $this->to('portal.forms.index', [], 'Formularul a fost șters. Contactele și acordurile lor rămân.');
    }
}
