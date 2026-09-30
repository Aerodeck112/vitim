<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\AgentResource;
use App\Http\Validation\AgentRules;
use App\Models\Agent;
use App\Services\AgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** /api/v1/orgs/{org}/agents */
final class AgentController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return AgentResource::collection(Agent::query()->orderBy('name')->paginate($this->perPage($request)));
    }

    public function store(Request $request, AgentService $agents): JsonResponse
    {
        $agent = $agents->create($request->validate(AgentRules::agent(true)));

        return (new AgentResource($agent))->response()->setStatusCode(201);
    }

    public function show(int $agent): AgentResource
    {
        return new AgentResource(Agent::query()->findOrFail($agent));
    }

    public function update(Request $request, AgentService $agents, int $agent): AgentResource
    {
        return new AgentResource($agents->update(Agent::query()->findOrFail($agent), $request->validate(AgentRules::agent(false))));
    }

    public function destroy(AgentService $agents, int $agent): Response
    {
        $agents->delete(Agent::query()->findOrFail($agent));

        return response()->noContent();
    }
}
