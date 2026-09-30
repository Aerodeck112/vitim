<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\IssuedSiteKey;
use App\Services\SiteKeyService;
use App\Services\SiteService;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Site-urile unui client (în contextul organizației din rută). */
final class SiteController extends Controller
{
    public function store(Request $request, TenantContext $context, SiteService $sites): RedirectResponse
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:253'],
            'platform' => ['required', Rule::in(['wordpress', 'generic'])],
        ]);
        [, $issued] = $sites->create($data['domain'], $data['platform']);

        return $this->withIssuedKey($context, $issued, 'Site adăugat.');
    }

    public function rotate(TenantContext $context, SiteKeyService $keys, int $site): RedirectResponse
    {
        $model = Site::query()->findOrFail($site); // scoped: un site al altei firme = 404

        return $this->withIssuedKey($context, $keys->rotate($model), 'Cheile au fost schimbate. Cheile vechi nu mai funcționează.');
    }

    /** Secretul se afișează o singură dată (sesiunea e criptată și mesajul dispare după afișare). */
    private function withIssuedKey(TenantContext $context, IssuedSiteKey $issued, string $message): RedirectResponse
    {
        return redirect()->route('admin.organizations.show', $context->organization()->slug)
            ->with('ok', $message)
            ->with('issued', ['public' => $issued->publicKey, 'secret' => $issued->secret]);
    }
}
