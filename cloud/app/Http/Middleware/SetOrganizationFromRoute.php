<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Services\AuditLogger;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rutele portalului: /app/{organization:slug}/...
 * Setează organizația curentă doar dacă utilizatorul e membru sau face parte din echipa VITIM.
 * Pentru nemembri răspunsul e 404 (nu confirmăm că firma există).
 */
final class SetOrganizationFromRoute
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $slug = $request->route('organization');
        $organization = $slug instanceof Organization ? $slug : Organization::where('slug', (string) $slug)->first();

        if ($user === null || $organization === null) {
            abort(404);
        }
        $isMember = $user->roleIn($organization) !== null;
        if (! $isMember && ! $user->isPlatformStaff()) {
            abort(404);
        }

        $this->context->set($organization);
        if (! $isMember) {
            // echipa VITIM intră în datele unui client: o dată pe sesiune, în audit
            $sessionKey = 'audited_access.'.$organization->getKey();
            if (! $request->session()->has($sessionKey)) {
                $this->audit->record('platform.accessed_organization', $organization, [], 'platform');
                $request->session()->put($sessionKey, true);
            }
        }
        $request->route()?->forgetParameter('organization');

        return $next($request);
    }
}
