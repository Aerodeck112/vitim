<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Site;
use App\Models\WorkLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Jurnalul de lucrări al unui client: adăugare, modificare, ștergere (echipa VITIM), cu audit. */
final class WorkLogService
{
    private const FIELDS = ['site_id', 'performed_at', 'category', 'title', 'description', 'duration_minutes', 'visible_to_client'];

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?int $authorId, string $source = 'manual'): WorkLog
    {
        $this->assertSite($data['site_id'] ?? null);

        return DB::transaction(function () use ($data, $authorId, $source): WorkLog {
            $log = WorkLog::create(array_intersect_key($data, array_flip(self::FIELDS)) + [
                'performed_by' => $authorId,
                'performed_at' => $data['performed_at'] ?? now(),
                'visible_to_client' => $data['visible_to_client'] ?? true,
                'source' => $source,
            ]);
            $this->audit->record('worklog.created', $log, ['category' => $log->category->value]);

            return $log;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(WorkLog $log, array $data): WorkLog
    {
        if (array_key_exists('site_id', $data)) {
            $this->assertSite($data['site_id']);
        }
        $log->fill(array_intersect_key($data, array_flip(self::FIELDS)));
        $changed = array_keys($log->getDirty());
        $log->save();
        if ($changed) {
            $this->audit->record('worklog.updated', $log, ['fields' => implode(',', $changed)]);
        }

        return $log;
    }

    public function delete(WorkLog $log): void
    {
        $this->audit->record('worklog.deleted', $log);
        $log->delete();
    }

    private function assertSite(mixed $siteId): void
    {
        if ($siteId !== null && $siteId !== '' && ! Site::query()->whereKey($siteId)->exists()) {
            throw ValidationException::withMessages(['site_id' => 'Site inexistent.']);
        }
    }
}
