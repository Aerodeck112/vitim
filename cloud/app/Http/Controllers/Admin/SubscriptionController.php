<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformRole;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Subscription;
use App\Services\AuditLogger;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Abonamentul unui client: planul și starea. Când perioada de probă expiră, agentul și chatul se opresc pe site-urile
 * firmei; de aici echipa VITIM activează abonamentul (unul câte unul sau toate cele cu proba expirată deodată).
 */
final class SubscriptionController extends Controller
{
    /** stare aleasă în formular => [stare salvată, etichetă] */
    public const CHOICES = [
        'active' => 'Activ',
        'trial' => 'Probă (încă 14 zile de la salvare)',
        'past_due' => 'Plată întârziată (merge în continuare)',
        'suspended' => 'Suspendat (agentul se oprește)',
        'cancelled' => 'Anulat (agentul se oprește)',
    ];

    public function update(Request $request, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(array_keys((array) config('plans.plans')))],
            'status' => ['required', Rule::in(array_keys(self::CHOICES))],
        ]);
        $organization = $context->organization();
        $subscription = $organization->subscription ?? new Subscription;
        $before = $subscription->exists ? ['plan' => $subscription->plan, 'status' => $subscription->status?->value] : null;
        $subscription->forceFill([
            'plan' => $data['plan'],
            'limits' => config("plans.plans.{$data['plan']}"),
            'status' => SubscriptionStatus::from($data['status']),
            'trial_ends_at' => $data['status'] === 'trial' ? now()->addDays((int) config('plans.trial_days', 14)) : $subscription->trial_ends_at,
        ])->save();
        $audit->record('subscription.updated', $subscription, ['before' => $before, 'plan' => $data['plan'], 'status' => $data['status']]);

        return redirect()->route('admin.organizations.show', $organization->slug)
            ->with('ok', 'Abonamentul a fost salvat: '.strtoupper($data['plan']).', '.mb_strtolower(self::CHOICES[$data['status']]).'.');
    }

    /** Toate abonamentele cu proba expirată devin active (cod de platformă: toate firmele). */
    public function activateExpired(Request $request, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()?->platform_role === PlatformRole::SuperAdmin, 403);
        $expired = Subscription::withoutTenancy()->where('status', SubscriptionStatus::Trial->value)->where('trial_ends_at', '<', now())->get();
        foreach ($expired as $subscription) {
            $organization = Organization::query()->find($subscription->organization_id);
            if (! $organization) {
                continue;
            }
            $context->runAs($organization, function () use ($subscription, $audit): void {
                $subscription->forceFill(['status' => SubscriptionStatus::Active])->save();
                $audit->record('subscription.updated', $subscription, ['before' => ['status' => 'trial'], 'status' => 'active', 'bulk' => true]);
            });
        }

        return redirect()->route('admin.organizations.index')->with('ok', $expired->count() === 1 ? 'Un abonament a fost activat.' : "{$expired->count()} abonamente au fost activate.");
    }
}
