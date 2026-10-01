<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Site;
use App\Models\SiteCommand;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Trimite o remediere din lista permisă către pluginul site-ului. Cererea e semnată cu secretul site-ului
 * (aceeași schemă ca în sens invers); pluginul verifică semnătura, acțiunea și dacă remedierea de la distanță e permisă.
 */
final class SiteCommandService
{
    /** Acțiuni de securizare înregistrate în jurnalul clientului (actualizările se înregistrează de plugin). */
    private const LOGGED = ['delete_debug_log', 'delete_readme', 'disable_xmlrpc', 'disable_file_edit', 'block_php_uploads', 'allow_indexing', 'reinstall_core'];

    public function __construct(
        private readonly SiteScanService $scans,
        private readonly WorkLogService $logs,
        private readonly AuditLogger $audit,
    ) {}

    public function run(Site $site, string $fix, ?User $user): SiteCommand
    {
        $parsed = Remediation::parse($fix);
        if ($parsed === null) {
            throw ValidationException::withMessages(['fix' => 'Acțiune nepermisă.']);
        }
        [$action, $target] = $parsed;
        $url = $this->endpoint($site);
        $key = $site->keys()->whereNull('revoked_at')->latest('id')->first();
        if ($key === null) {
            throw ValidationException::withMessages(['fix' => 'Site-ul nu are o cheie activă.']);
        }

        $command = SiteCommand::create(['site_id' => $site->id, 'action' => $action, 'target' => $target, 'status' => 'running', 'requested_by' => $user?->id]);
        $body = (string) json_encode(['command_id' => $command->id, 'action' => $action, 'target' => $target], JSON_UNESCAPED_SLASHES);
        $ts = (string) time();
        $nonce = Str::random(32);
        $started = hrtime(true);
        try {
            $response = Http::timeout(180)->acceptJson()->withHeaders([
                'X-Vitim-Key' => $key->public_key,
                'X-Vitim-Timestamp' => $ts,
                'X-Vitim-Nonce' => $nonce,
                'X-Vitim-Signature' => hash_hmac('sha256', $ts.'.'.$nonce.'.'.$body, (string) $key->secret),
            ])->withBody($body, 'application/json')->post($url);
            $json = (array) $response->json();
            $ok = $response->successful() && ($json['ok'] ?? false) === true;
            $message = (string) ($json['message'] ?? ($response->successful() ? 'Răspuns neașteptat de la plugin.' : 'HTTP '.$response->status()));
        } catch (ConnectionException $e) {
            $ok = false;
            $json = [];
            $message = 'Site-ul nu a răspuns: '.Str::limit($e->getMessage(), 200);
        }

        $command->forceFill([
            'status' => $ok ? 'done' : 'failed',
            'result' => Str::limit($message, 2000),
            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
        ])->save();
        if (isset($json['issues']) && is_array($json['issues'])) {
            $this->scans->ingest($site, ScanPayload::issues($json['issues']));
        }
        $this->audit->record('site.command', $site, ['action' => $action, 'target' => $target, 'status' => $command->status]);
        if ($ok && in_array($action, self::LOGGED, true)) {
            $this->logs->create(['site_id' => $site->id, 'category' => 'security', 'title' => Remediation::label($fix).($target ? " ({$target})" : '')], $user?->id, 'system');
        }

        return $command;
    }

    /** Adresa raportată de plugin, acceptată doar pe domeniul site-ului (și doar https în producție). */
    private function endpoint(Site $site): string
    {
        $url = (string) ($site->health['command_url'] ?? '');
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $schemeOk = ($parts['scheme'] ?? '') === 'https' || (! app()->isProduction() && ($parts['scheme'] ?? '') === 'http');
        if ($url === '' || ! $schemeOk || ! in_array($host, [$site->domain, 'www.'.$site->domain], true)) {
            throw ValidationException::withMessages(['fix' => 'Pluginul nu a raportat încă o adresă validă pentru remedieri (actualizează pluginul la 1.1.0 și apasă „Trimite acum” în WordPress).']);
        }

        return $url;
    }
}
