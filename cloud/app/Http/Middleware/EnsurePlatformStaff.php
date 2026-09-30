<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\PlatformRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Zona /admin: doar echipa VITIM. Cu parametrul „super”, doar super adminii (administrarea echipei). */
final class EnsurePlatformStaff
{
    public function handle(Request $request, Closure $next, ?string $level = null): Response
    {
        $role = $request->user()?->platform_role;
        if ($role === null || ($level === 'super' && $role !== PlatformRole::SuperAdmin)) {
            abort(404);
        }

        return $next($request);
    }
}
