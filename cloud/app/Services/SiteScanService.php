<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Site;
use App\Models\SiteIssue;
use Illuminate\Support\Facades\DB;

/** Rezultatul unei scanări: problemele noi se deschid, cele care nu mai apar se închid singure. */
final class SiteScanService
{
    /** @param list<array{code: string, severity: string, title: string, details?: ?string, fix?: ?string}> $issues */
    public function ingest(Site $site, array $issues): void
    {
        DB::transaction(function () use ($site, $issues): void {
            $now = now();
            $seen = [];
            foreach ($issues as $issue) {
                $fix = isset($issue['fix']) && $issue['fix'] !== '' && Remediation::parse($issue['fix']) ? $issue['fix'] : null;
                $seen[] = $issue['code'];
                $existing = SiteIssue::query()->where('site_id', $site->id)->where('code', $issue['code'])->first();
                $data = ['severity' => $issue['severity'], 'title' => $issue['title'], 'details' => $issue['details'] ?? null, 'fix' => $fix, 'last_seen_at' => $now];
                if ($existing) {
                    $existing->fill($data + ($existing->status === 'resolved' ? ['status' => 'open', 'first_seen_at' => $now, 'resolved_at' => null] : []))->save();
                } else {
                    SiteIssue::create($data + ['site_id' => $site->id, 'code' => $issue['code'], 'status' => 'open', 'first_seen_at' => $now]);
                }
            }
            SiteIssue::query()->where('site_id', $site->id)->where('status', 'open')->whereNotIn('code', $seen ?: [''])
                ->update(['status' => 'resolved', 'resolved_at' => $now]);
            $site->forceFill(['last_scan_at' => $now])->save();
        });
    }
}
