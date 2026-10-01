<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrgRole;
use App\Http\Controllers\Controller;
use App\Mail\MonthlyReportPublished;
use App\Models\ClientService;
use App\Models\Membership;
use App\Models\MonthlyReport;
use App\Reports\ReportBuilder;
use App\Reports\ServiceCatalog;
use App\Services\AuditLogger;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/** Echipa VITIM: serviciile clientului și rapoartele lunare (completare, previzualizare, publicare). */
final class ReportController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(): View
    {
        $reports = MonthlyReport::query()->get()->keyBy('period');
        $periods = collect(range(0, 11))->map(fn ($i) => now()->startOfMonth()->subMonths($i)->format('Y-m'));

        return view('admin.reports.index', [
            'organization' => $this->context->organization(),
            'periods' => $periods,
            'reports' => $reports,
            'services' => ClientService::query()->get()->keyBy('service'),
        ]);
    }

    public function services(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'services' => ['nullable', 'array'],
            'services.*' => ['string', 'in:'.implode(',', array_keys(ServiceCatalog::SERVICES))],
        ]);
        $active = $data['services'] ?? [];
        foreach (array_keys(ServiceCatalog::SERVICES) as $service) {
            $existing = ClientService::query()->where('service', $service)->first();
            if (in_array($service, $active, true)) {
                $existing ? $existing->update(['status' => 'active']) : ClientService::create(['service' => $service, 'status' => 'active', 'started_at' => now()]);
            } elseif ($existing) {
                $existing->update(['status' => 'paused']);
            }
        }
        $audit->record('services.updated', $this->context->organization(), ['active' => implode(',', $active)]);

        return redirect()->route('admin.reports.index', $this->context->organization()->slug)->with('ok', 'Serviciile au fost salvate.');
    }

    public function edit(ReportBuilder $builder, string $period): View
    {
        $report = MonthlyReport::query()->where('period', $period)->first();

        return view('admin.reports.edit', [
            'organization' => $this->context->organization(),
            'period' => $period,
            'report' => $report,
            'preview' => $builder->build($period, $report),
            'services' => ClientService::query()->where('status', 'active')->pluck('service')->all(),
        ]);
    }

    public function update(Request $request, string $period): RedirectResponse
    {
        $rules = ['summary' => ['nullable', 'string', 'max:5000']];
        foreach (ClientService::query()->where('status', 'active')->pluck('service') as $service) {
            $rules["data.{$service}.summary"] = ['nullable', 'string', 'max:5000'];
            foreach (ServiceCatalog::metrics($service) as $key => $definition) {
                $rules["data.{$service}.metrics.{$key}"] = ['nullable', 'numeric', 'min:0', $key === 'rating' ? 'max:5' : 'max:1000000000'];
            }
        }
        $data = $request->validate($rules);
        $report = MonthlyReport::query()->firstOrNew(['period' => $period]);
        $report->fill(['data' => $data['data'] ?? [], 'summary' => $data['summary'] ?? null]);
        $report->save();

        if ($request->boolean('publish')) {
            $report->forceFill(['published_at' => now(), 'published_by' => $request->user()->id])->save();
            $this->notifyClient($period);

            return $this->back($period, 'Raportul a fost publicat și trimis clientului pe email.');
        }

        return $this->back($period, 'Raportul a fost salvat (nepublicat).');
    }

    public function unpublish(string $period): RedirectResponse
    {
        MonthlyReport::query()->where('period', $period)->firstOrFail()->forceFill(['published_at' => null])->save();

        return $this->back($period, 'Raportul nu mai e vizibil clientului.');
    }

    private function notifyClient(string $period): void
    {
        $organization = $this->context->organization();
        $emails = Membership::query()->with('user')->whereIn('role', [OrgRole::Owner->value, OrgRole::Admin->value])->get()
            ->map(fn (Membership $m) => $m->user?->email)->filter()->unique()->values();
        $url = route('portal.reports.show', [$organization->slug, $period]);
        $label = ucfirst(Carbon::createFromFormat('Y-m-d', $period.'-01')->locale('ro')->translatedFormat('F Y'));
        foreach ($emails as $email) {
            Mail::to($email)->send(new MonthlyReportPublished($organization->name, $label, $url));
        }
    }

    private function back(string $period, string $message): RedirectResponse
    {
        return redirect()->route('admin.reports.edit', [$this->context->organization()->slug, $period])->with('ok', $message);
    }
}
