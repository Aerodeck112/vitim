<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\ContactSource;
use App\Http\Resources\ConsentResource;
use App\Http\Resources\ContactResource;
use App\Http\Validation\ContactRules;
use App\Models\Contact;
use App\Models\ContactConsent;
use App\Services\ConsentService;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** /api/v1/orgs/{org}/contacts (+ /consents) */
final class ContactController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Contact::query()->latest('id');
        if ($q = trim((string) $request->query('q'))) {
            $like = $this->like($q);
            $query->where(fn ($w) => $w->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
                ->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('company', 'like', $like));
        }
        if ($source = $request->query('source')) {
            $query->where('source', $source);
        }

        return ContactResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(Request $request, ContactService $contacts): JsonResponse
    {
        $data = $request->validate(ContactRules::contact(true));
        $contact = $contacts->create($data, ContactSource::from($data['source'] ?? ContactSource::Api->value));

        return (new ContactResource($contact->load('identities')))->response()->setStatusCode(201);
    }

    public function show(ConsentService $consents, int $contact): ContactResource
    {
        $model = Contact::query()->with('identities')->findOrFail($contact);

        return (new ContactResource($model))->withConsents($consents->matrix($model));
    }

    public function update(Request $request, ContactService $contacts, int $contact): ContactResource
    {
        $model = $contacts->update(Contact::query()->findOrFail($contact), $request->validate(ContactRules::contact(false)));

        return new ContactResource($model->load('identities'));
    }

    public function destroy(ContactService $contacts, int $contact): Response
    {
        $contacts->delete(Contact::query()->findOrFail($contact));

        return response()->noContent();
    }

    public function consents(Request $request, int $contact): AnonymousResourceCollection
    {
        $model = Contact::query()->findOrFail($contact);

        return ConsentResource::collection(ContactConsent::query()->where('contact_id', $model->id)->orderByDesc('occurred_at')->orderByDesc('id')->paginate($this->perPage($request)));
    }

    public function recordConsent(Request $request, ConsentService $consents, int $contact): JsonResponse
    {
        $model = Contact::query()->findOrFail($contact);
        $data = $request->validate(ContactRules::consent());
        $consent = $consents->record(
            $model, Channel::from($data['channel']), ConsentPurpose::from($data['purpose']), ConsentStatus::from($data['status']),
            $data['source'], $data['metadata'] ?? [], $request->ip(), $request->userAgent(), $request->user(),
            isset($data['occurred_at']) ? now()->parse($data['occurred_at']) : null,
        );

        return (new ConsentResource($consent))->response()->setStatusCode(201);
    }
}
