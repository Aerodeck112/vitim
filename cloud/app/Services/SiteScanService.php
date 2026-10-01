<?php

declare(strict_types=1);

namespace App\Services;

use App\Audit\Guidance;
use App\Models\Site;
use App\Models\SiteIssue;
use Illuminate\Support\Facades\DB;

/**
 * Rezultatul unei scanări (plugin) sau al unui audit (extern): problemele noi se deschid, cele din aceeași sursă
 * care nu mai apar se închid singure. După fiecare rezultat se recalculează scorurile pe categorii.
 */
final class SiteScanService
{
    /** Constatări ale pluginului pe care auditul extern le acoperă deja (nu le afișăm de două ori). */
    private const COVERED_BY_AUDIT = ['no_https'];

    /** @param list<array{code: string, severity: string, title: string, details?: ?string, fix?: ?string}> $issues */
    public function ingest(Site $site, array $issues, string $source = 'plugin'): void
    {
        if ($source === 'plugin') {
            $issues = array_values(array_filter($issues, fn (array $i) => ! in_array($i['code'], self::COVERED_BY_AUDIT, true)));
        }
        DB::transaction(function () use ($site, $issues, $source): void {
            $now = now();
            $seen = [];
            foreach ($issues as $issue) {
                $fix = isset($issue['fix']) && $issue['fix'] !== '' && Remediation::parse($issue['fix']) ? $issue['fix'] : null;
                $seen[] = $issue['code'];
                $existing = SiteIssue::query()->where('site_id', $site->id)->where('code', $issue['code'])->first();
                $data = [
                    'category' => Guidance::category($issue['code']), 'source' => $source, 'severity' => $issue['severity'],
                    'title' => $issue['title'], 'details' => $issue['details'] ?? null, 'fix' => $fix, 'last_seen_at' => $now,
                ];
                if ($existing) {
                    $existing->fill($data + ($existing->status === 'resolved' ? ['status' => 'open', 'first_seen_at' => $now, 'resolved_at' => null] : []))->save();
                } else {
                    SiteIssue::create($data + ['site_id' => $site->id, 'code' => $issue['code'], 'status' => 'open', 'first_seen_at' => $now]);
                }
            }
            SiteIssue::query()->where('site_id', $site->id)->where('source', $source)->where('status', 'open')
                ->whereNotIn('code', $seen ?: [''])->update(['status' => 'resolved', 'resolved_at' => $now]);

            $open = SiteIssue::query()->where('site_id', $site->id)->where('status', 'open')->get(['category', 'severity']);
            $stamp = ['plugin' => 'last_scan_at', 'audit' => 'last_audit_at'][$source] ?? null;
            $site->forceFill(($stamp ? [$stamp => $now] : []) + [
                'scores' => Guidance::scores($open->map(fn ($i) => ['category' => $i->category, 'severity' => $i->severity])),
            ])->save();
        });
    }
}
