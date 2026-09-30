<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\SitePlatform;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use App\Services\SiteKeyService;
use App\Services\SiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** /api/v1/orgs/{org}/sites — toate interogările sunt limitate automat la firma din URL. */
final class SiteController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return SiteResource::collection(Site::query()->with('keys')->orderBy('name')->paginate($this->perPage($request)));
    }

    public function store(Request $request, SiteService $sites): JsonResponse
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:253'],
            'name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'platform' => ['required', Rule::enum(SitePlatform::class)],
            'allowed_origins' => ['sometimes', 'array', 'max:10'],
            'allowed_origins.*' => ['string', 'max:253'],
        ]);
        [$site, $issued] = $sites->create($data['domain'], SitePlatform::from($data['platform']), $data['allowed_origins'] ?? [], $data['name'] ?? null);

        // secretul pentru plugin se întoarce o singură dată, la creare / rotire
        return (new SiteResource($site->load('keys')))->additional(['secret' => $issued->secret])->response()->setStatusCode(201);
    }

    public function show(int $site): SiteResource
    {
        return new SiteResource(Site::query()->with('keys')->findOrFail($site));
    }

    public function update(Request $request, SiteService $sites, int $site): SiteResource
    {
        $model = Site::query()->findOrFail($site);
        $sites->update($model, $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'platform' => ['sometimes', Rule::enum(SitePlatform::class)],
            'status' => ['sometimes', Rule::in(['active', 'disabled'])],
            'allowed_origins' => ['sometimes', 'array', 'max:10'],
            'allowed_origins.*' => ['string', 'max:253'],
            'domain' => ['prohibited'],
        ]));

        return new SiteResource($model->load('keys'));
    }

    public function rotateKeys(SiteKeyService $keys, int $site): JsonResponse
    {
        $issued = $keys->rotate(Site::query()->findOrFail($site));

        return response()->json(['data' => ['public_key' => $issued->publicKey, 'secret' => $issued->secret]]);
    }
}
