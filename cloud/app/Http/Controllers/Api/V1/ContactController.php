<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\ContactSource;
use App\Http\Resources\ConsentResource;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Models\ContactConsent;
use App\Services\ConsentService;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

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
        $data = $request->validate($this->rules(true));
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
        $model = $contacts->update(Contact::query()->findOrFail($contact), $request->validate($this->rules(false)));

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
        $data = $request->validate([
            'channel' => ['required', Rule::in(array_map(fn ($c) => $c->value, Channel::consentChannels()))],
            'purpose' => ['required', Rule::enum(ConsentPurpose::class)],
            'status' => ['required', Rule::enum(ConsentStatus::class)],
            'source' => ['required', 'string', 'max:40'],
            'occurred_at' => ['sometimes', 'date', 'before_or_equal:now'],
            'metadata' => ['sometimes', 'array', 'max:20'],
            'metadata.*' => ['nullable', 'scalar'],
        ]);
        $consent = $consents->record(
            $model, Channel::from($data['channel']), ConsentPurpose::from($data['purpose']), ConsentStatus::from($data['status']),
            $data['source'], $data['metadata'] ?? [], $request->ip(), $request->userAgent(), $request->user(),
            isset($data['occurred_at']) ? now()->parse($data['occurred_at']) : null,
        );

        return (new ConsentResource($consent))->response()->setStatusCode(201);
    }

    /** @return array<string, mixed> */
    private function rules(bool $creating): array
    {
        return [
            'first_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'string', 'max:190'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'whatsapp' => [$creating ? 'sometimes' : 'prohibited', 'nullable', 'string', 'max:40'],
            'company' => ['sometimes', 'nullable', 'string', 'max:190'],
            'language' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'],
            'status' => ['sometimes', Rule::in(['active', 'archived'])],
            'source' => [$creating ? 'sometimes' : 'prohibited', Rule::enum(ContactSource::class)],
            'custom_fields' => ['sometimes', 'nullable', 'array', 'max:50'],
            'custom_fields.*' => ['nullable', 'scalar'],
            'external_ids' => [$creating ? 'sometimes' : 'prohibited', 'array', 'max:10'],
            'external_ids.*.provider' => ['required', 'string', 'max:40', 'alpha_dash'],
            'external_ids.*.id' => ['required', 'string', 'max:190'],
        ];
    }
}
