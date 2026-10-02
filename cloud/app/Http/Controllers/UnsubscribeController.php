<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CampaignRecipient;
use App\Services\CampaignRenderer;
use App\Services\Unsubscribes;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Dezabonarea din linkul unei campanii: pagina de confirmare și dezabonarea dintr-un click (POST, fără CSRF). */
final class UnsubscribeController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function show(string $code): View
    {
        $recipient = $this->recipient($code);

        return view('unsubscribe', [
            'company' => $recipient ? CampaignRenderer::company($recipient->organization) : null,
            'done' => $recipient?->status === 'unsubscribed',
            'code' => $code,
            'test' => $code === 'TEST',
        ]);
    }

    public function confirm(Request $request, Unsubscribes $unsubscribes, string $code): View|Response
    {
        $recipient = $this->recipient($code);
        if ($recipient && $recipient->status !== 'unsubscribed') {
            $this->context->runAs($recipient->organization, fn () => $unsubscribes->byRecipient(
                $recipient, $request->has('List-Unsubscribe') ? 'unsubscribe_one_click' : 'unsubscribe_link', $request->ip(), $request->userAgent()));
        }
        if ($request->has('List-Unsubscribe')) {
            return response('', 200); // clientul de email nu afișează nimic
        }

        return view('unsubscribe', ['company' => $recipient ? CampaignRenderer::company($recipient->organization) : null, 'done' => (bool) $recipient, 'code' => $code, 'test' => $code === 'TEST']);
    }

    private function recipient(string $code): ?CampaignRecipient
    {
        // cod de platformă: firma se află din codul unic aleator (10 caractere), nu din datele cererii
        return CampaignRecipient::withoutTenancy()->with(['organization', 'campaign' => fn ($q) => $q->withoutGlobalScopes(), 'contact' => fn ($q) => $q->withoutGlobalScopes()])
            ->where('unsubscribe_code', $code)->first();
    }
}
