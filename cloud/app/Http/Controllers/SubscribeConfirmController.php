<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use App\Services\CampaignRenderer;
use App\Services\SignupFormService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dubla confirmare: linkul din email deschide o pagină cu buton (POST), ca scannerele de linkuri ale serverelor
 * de email să nu poată confirma în locul persoanei.
 */
final class SubscribeConfirmController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function show(string $code): View
    {
        return $this->page($code, $this->submission($code));
    }

    public function store(Request $request, SignupFormService $forms, string $code): View
    {
        $submission = $this->submission($code);
        $ok = $submission && $this->context->runAs($submission->organization, fn () => $forms->confirm($submission->fresh(['form', 'contact']), $request->ip(), $request->userAgent()));

        return $this->page($code, $ok ? $submission->fresh() : null);
    }

    private function page(string $code, ?FormSubmission $submission): View
    {
        $valid = $submission && ($submission->confirmed_at || $submission->created_at->gte(now()->subDays(SignupFormService::CONFIRM_DAYS)));
        $form = $submission?->form()->withoutGlobalScopes()->first();

        return view('subscribe-confirm', [
            'company' => $valid ? CampaignRenderer::company($submission->organization) : null,
            'done' => (bool) $submission?->confirmed_at,
            'code' => $code,
            'title' => $form?->c('success_title') === 'Mulțumim!' ? null : $form?->c('success_title'),
            'text' => null,
            'coupon' => $form?->c('coupon') ?: null,
        ]);
    }

    private function submission(string $code): ?FormSubmission
    {
        // cod de platformă: înscrierea (și firma) se află doar din codul aleator de 40 de caractere trimis pe email
        return FormSubmission::withoutTenancy()->with('organization')->where('confirm_hash', hash('sha256', $code))->first();
    }
}
