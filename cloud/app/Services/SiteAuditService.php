<?php

declare(strict_types=1);

namespace App\Services;

use App\Audit\SiteAuditor;
use App\Models\Site;

/** Rulează auditul extern al unui site și salvează rezultatul (în contextul firmei site-ului). */
final class SiteAuditService
{
    public function __construct(private readonly SiteAuditor $auditor, private readonly SiteScanService $scans) {}

    public function run(Site $site): int
    {
        $findings = $this->auditor->audit($site);
        // pe WordPress cu pluginul 1.4.0+ o parte din probleme se rezolvă la buton
        $version = in_array($site->platform?->value, ['wordpress', 'woocommerce'], true) ? $site->connector_version : null;
        foreach ($findings as $i => $finding) {
            $findings[$i]['fix'] = Remediation::forAudit($finding['code'], $version) ?? $finding['fix'];
        }
        $this->scans->ingest($site, $findings, 'audit');

        return count($findings);
    }
}
