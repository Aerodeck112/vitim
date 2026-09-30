<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Organizațiile (doar echipa VITIM): /api/v1/admin/organizations */
final class OrganizationController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Organization::query()->with('subscriptionWithoutTenancy')->orderBy('name');
        if ($q = $request->query('q')) {
            $query->where(fn ($w) => $w->where('name', 'like', $this->like((string) $q))->orWhere('company_name', 'like', $this->like((string) $q)));
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return OrganizationResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(Request $request, OrganizationService $organizations): OrganizationResource
    {
        $data = $request->validate($this->rules(true));
        $owner = null;
        $newOwner = false;
        if (! empty($data['owner_email'])) {
            $email = Str::lower($data['owner_email']);
            $owner = User::where('email', $email)->first();
            if ($owner === null) {
                $owner = User::create(['name' => $data['owner_name'] ?? $email, 'email' => $email, 'password' => Str::password(40)]);
                $newOwner = true;
            }
        }
        $organization = $organizations->create($data['name'], $data['plan'], $owner, $data);
        if ($newOwner) {
            Password::sendResetLink(['email' => $owner->email]);
        }

        return new OrganizationResource($organization->load('subscriptionWithoutTenancy'));
    }

    public function show(int $organization): OrganizationResource
    {
        return new OrganizationResource(Organization::query()->with('subscriptionWithoutTenancy')->findOrFail($organization));
    }

    public function update(Request $request, OrganizationService $organizations, int $organization): OrganizationResource
    {
        $model = Organization::query()->findOrFail($organization);
        $organizations->update($model, $request->validate($this->rules(false)));

        return new OrganizationResource($model->load('subscriptionWithoutTenancy'));
    }

    /** @return array<string, mixed> */
    private function rules(bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:160'],
            'plan' => [$creating ? 'required' : 'prohibited', Rule::in(array_keys(config('plans.plans')))],
            'status' => ['sometimes', Rule::in(['active', 'suspended'])],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:190'],
            'vat_id' => ['sometimes', 'nullable', 'string', 'max:32'],
            'country' => ['sometimes', 'string', 'size:2', 'alpha'],
            'timezone' => ['sometimes', 'timezone:all'],
            'default_language' => ['sometimes', 'string', 'size:2', 'alpha'],
            'owner_email' => [$creating ? 'sometimes' : 'prohibited', 'email', 'max:190'],
            'owner_name' => [$creating ? 'sometimes' : 'prohibited', 'string', 'max:120'],
        ];
    }
}
