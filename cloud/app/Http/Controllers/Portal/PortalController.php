<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;

abstract class PortalController extends Controller
{
    public function __construct(protected readonly TenantContext $context) {}

    protected function organization(): Organization
    {
        return $this->context->organization();
    }

    /** @param array<string, mixed> $params */
    protected function to(string $route, array $params = [], ?string $message = null): RedirectResponse
    {
        $redirect = redirect()->route($route, ['organization' => $this->organization()->slug] + $params);

        return $message ? $redirect->with('ok', $message) : $redirect;
    }
}
