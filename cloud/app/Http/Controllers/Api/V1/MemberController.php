<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrgRole;
use App\Http\Resources\MemberResource;
use App\Models\Membership;
use App\Services\MembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Utilizatorii firmei: /api/v1/orgs/{org}/users */
final class MemberController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return MemberResource::collection(Membership::query()->with('user')->orderBy('id')->paginate($this->perPage($request)));
    }

    public function store(Request $request, MembershipService $members): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', Rule::enum(OrgRole::class)],
        ]);
        $membership = $members->invite($request->user(), $data['email'], $data['name'], OrgRole::from($data['role']));

        return (new MemberResource($membership->load('user')))->response()->setStatusCode(201);
    }

    public function update(Request $request, MembershipService $members, int $member): MemberResource
    {
        $data = $request->validate(['role' => ['required', Rule::enum(OrgRole::class)]]);
        $membership = $members->changeRole($request->user(), Membership::query()->findOrFail($member), OrgRole::from($data['role']));

        return new MemberResource($membership->load('user'));
    }

    public function destroy(Request $request, MembershipService $members, int $member): Response
    {
        $members->remove($request->user(), Membership::query()->findOrFail($member));

        return response()->noContent();
    }
}
