<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/** Un rând de log per cerere API (metodă, rută, status, durată, utilizator, firmă). Fără corpul cererii. */
final class LogApiRequest
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $response = $next($request);
        Log::channel(config('logging.api_channel', config('logging.default')))->info('api', [
            'method' => $request->method(),
            'route' => $request->route()?->getName() ?? $request->path(),
            'status' => $response->getStatusCode(),
            'ms' => (int) round((microtime(true) - $start) * 1000),
            'user' => $request->user()?->getAuthIdentifier(),
            'org' => $this->context->has() ? $this->context->id() : null,
        ]);

        return $response;
    }
}
