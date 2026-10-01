<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Site;
use App\Models\SiteBackup;
use App\Models\WorkLog;
use Illuminate\Support\Carbon;

/**
 * Backup-urile raportate de plugin: le înregistrează, le verifică vechimea și deschide / închide problemele
 * de backup ale site-ului. O dată pe săptămână, un backup reușit intră în jurnalul de lucrări al clientului.
 */
final class BackupMonitor
{
    public function __construct(private readonly SiteScanService $scans, private readonly WorkLogService $logs) {}

    /** @param array<string, mixed> $data */
    public function record(Site $site, array $data): SiteBackup
    {
        $backup = SiteBackup::create([
            'site_id' => $site->id,
            'status' => $data['status'],
            'verified' => (bool) ($data['verified'] ?? false),
            'started_at' => Carbon::parse($data['started_at']),
            'finished_at' => isset($data['finished_at']) ? Carbon::parse($data['finished_at']) : null,
            'db_bytes' => (int) ($data['db_bytes'] ?? 0),
            'files_bytes' => (int) ($data['files_bytes'] ?? 0),
            'files_count' => (int) ($data['files_count'] ?? 0),
            'location' => $data['location'] ?? null,
            'kept' => (int) ($data['kept'] ?? 0),
            'error' => $data['error'] ?? null,
        ]);

        if ($backup->status === 'ok' && $backup->verified) {
            $recent = WorkLog::query()->where('site_id', $site->id)->where('category', 'backup')->where('source', 'system')
                ->where('performed_at', '>=', now()->subDays(6))->exists();
            if (! $recent) {
                $this->logs->create([
                    'site_id' => $site->id,
                    'category' => 'backup',
                    'title' => 'Backup automat verificat: bază de date '.self::size($backup->db_bytes).' + fișiere '.self::size($backup->files_bytes),
                    'description' => "Backup zilnic al bazei de date și al fișierelor, verificat după creare. Se păstrează ultimele {$backup->kept} copii.",
                    'performed_at' => $backup->finished_at ?? now(),
                ], null, 'system');
            }
        }
        $this->evaluate($site);

        return $backup;
    }

    /** Problemele de backup ale site-ului (doar pentru site-urile cu plugin care face backup). */
    public function evaluate(Site $site): void
    {
        if (empty($site->health['backup_schedule']) || $site->health['backup_schedule'] === 'off') {
            $this->scans->ingest($site, [], 'backup');

            return;
        }
        $issues = [];
        $last = SiteBackup::query()->where('site_id', $site->id)->latest('started_at')->first();
        $lastOk = SiteBackup::query()->where('site_id', $site->id)->where('status', 'ok')->where('verified', true)->latest('started_at')->first();
        $limit = $site->health['backup_schedule'] === 'weekly' ? 8 : 2;

        if ($last === null) {
            $issues[] = ['code' => 'backup.none', 'severity' => 'warning', 'title' => 'Site-ul nu are încă niciun backup'];
        } elseif ($last->status === 'failed') {
            $issues[] = ['code' => 'backup.failed', 'severity' => 'critical', 'title' => 'Ultimul backup a eșuat ('.$last->started_at->format('d.m.Y H:i').')', 'details' => $last->error];
        }
        if ($last !== null && ($lastOk === null || $lastOk->started_at->lt(now()->subDays($limit)))) {
            $days = $lastOk ? (int) $lastOk->started_at->diffInDays(now()) : null;
            $issues[] = ['code' => 'backup.stale', 'severity' => $days === null || $days > 7 ? 'critical' : 'warning',
                'title' => $days === null ? 'Niciun backup reușit până acum' : "Ultimul backup reușit e de acum {$days} zile"];
        }
        if ($lastOk && str_contains((string) $lastOk->location, '/wp-content/')) {
            $issues[] = ['code' => 'backup.public', 'severity' => 'info', 'title' => 'Backup-urile stau într-un folder al site-ului', 'details' => $lastOk->location];
        }
        $this->scans->ingest($site, $issues, 'backup');
    }

    public static function size(int $bytes): string
    {
        return $bytes >= 1_073_741_824 ? round($bytes / 1_073_741_824, 1).' GB' : ($bytes >= 1_048_576 ? round($bytes / 1_048_576, 1).' MB' : max(1, (int) round($bytes / 1024)).' KB');
    }
}
