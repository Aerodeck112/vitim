<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\WorkCategory;
use App\Http\Controllers\Controller;
use App\Http\Validation\WorkLogRules;
use App\Models\Site;
use App\Models\WorkLog;
use App\Services\WorkLogService;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Echipa VITIM: jurnalul de lucrări al unui client (rulează în contextul firmei, middleware `org`). */
final class WorkLogController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(): View
    {
        return view('admin.worklogs.index', [
            'organization' => $this->context->organization(),
            'logs' => WorkLog::query()->with(['site', 'author'])->latest('performed_at')->latest('id')->paginate(50),
            'sites' => Site::query()->orderBy('domain')->get(),
            'categories' => WorkCategory::cases(),
            'edit' => null,
        ]);
    }

    public function edit(int $log): View
    {
        return view('admin.worklogs.index', [
            'organization' => $this->context->organization(),
            'logs' => null,
            'sites' => Site::query()->orderBy('domain')->get(),
            'categories' => WorkCategory::cases(),
            'edit' => WorkLog::query()->findOrFail($log),
        ]);
    }

    public function store(Request $request, WorkLogService $logs): RedirectResponse
    {
        $data = $request->validate(WorkLogRules::rules());
        $logs->create($data + ['visible_to_client' => $request->boolean('visible_to_client')], $request->user()->id);

        return $this->back('Lucrarea a fost adăugată.');
    }

    public function update(Request $request, WorkLogService $logs, int $log): RedirectResponse
    {
        $data = $request->validate(WorkLogRules::rules());
        $logs->update(WorkLog::query()->findOrFail($log), $data + ['visible_to_client' => $request->boolean('visible_to_client')]);

        return $this->back('Lucrarea a fost modificată.');
    }

    public function destroy(WorkLogService $logs, int $log): RedirectResponse
    {
        $logs->delete(WorkLog::query()->findOrFail($log));

        return $this->back('Lucrarea a fost ștearsă.');
    }

    private function back(string $message): RedirectResponse
    {
        return redirect()->route('admin.worklogs.index', $this->context->organization()->slug)->with('ok', $message);
    }
}
