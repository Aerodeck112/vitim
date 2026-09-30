<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\LeadIntent;
use App\Enums\LeadStatus;
use App\Http\Resources\LeadResource;
use App\Models\Contact;
use App\Models\Lead;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** /api/v1/orgs/{org}/leads */
final class LeadController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Lead::query()->with('contact')->latest('id');
        foreach (['status', 'intent', 'contact_id', 'assigned_to'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        return LeadResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(Request $request, LeadService $leads): JsonResponse
    {
        $data = $request->validate(['contact_id' => ['required', 'integer']] + $this->rules());
        $contact = Contact::query()->find($data['contact_id']);
        if ($contact === null) {
            // contact inexistent sau din altă firmă: aceeași eroare
            abort(422, 'Contactul nu există.');
        }

        return (new LeadResource($leads->create($contact, $data)->load('contact')))->response()->setStatusCode(201);
    }

    public function show(int $lead): LeadResource
    {
        return new LeadResource(Lead::query()->with('contact')->findOrFail($lead));
    }

    public function update(Request $request, LeadService $leads, int $lead): LeadResource
    {
        $data = $request->validate($this->rules() + ['contact_id' => ['prohibited']]);

        return new LeadResource($leads->update(Lead::query()->findOrFail($lead), $data)->load('contact'));
    }

    public function destroy(LeadService $leads, int $lead): Response
    {
        $leads->delete(Lead::query()->findOrFail($lead));

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(LeadStatus::class)],
            'intent' => ['sometimes', Rule::enum(LeadIntent::class)],
            'source' => ['sometimes', 'string', 'max:24', 'alpha_dash'],
            'score' => ['sometimes', 'nullable', 'integer', 'between:0,100'],
            'assigned_to' => ['sometimes', 'nullable', 'integer'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'site_id' => ['sometimes', 'nullable', 'integer'],
            'agent_id' => ['sometimes', 'nullable', 'integer'],
            'value_amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3', 'alpha'],
        ];
    }
}
