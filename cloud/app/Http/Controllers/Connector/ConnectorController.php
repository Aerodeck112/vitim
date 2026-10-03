<?php

declare(strict_types=1);

namespace App\Http\Controllers\Connector;

use App\Enums\WorkCategory;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\WorkLog;
use App\Services\BackupMonitor;
use App\Services\CookieSettings;
use App\Services\ScanPayload;
use App\Services\ShopEvents;
use App\Services\SiteKeyService;
use App\Services\SiteScanService;
use App\Services\WorkLogService;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * API pentru pluginul WordPress și conectorul PHP. Fiecare cerere e semnată cu secretul site-ului
 * (HMAC-SHA256 peste "timestamp.nonce.body", fereastră de 5 minute, nonce unic). Firma vine doar din cheie.
 */
final class ConnectorController extends Controller
{
    public function __construct(
        private readonly SiteKeyService $keys,
        private readonly TenantContext $context,
    ) {}

    /** Starea site-ului, trimisă la fiecare oră. */
    public function heartbeat(Request $request): JsonResponse
    {
        $site = $this->authenticate($request);
        if (! $site) {
            return $this->unauthorized();
        }
        $data = Validator::make($request->json()->all(), [
            'platform' => ['required', Rule::in(['wordpress', 'custom'])],
            'site_url' => ['required', 'string', 'max:255'],
            'connector_version' => ['required', 'string', 'max:32'],
            'php_version' => ['nullable', 'string', 'max:32'],
            'core_version' => ['nullable', 'string', 'max:32'],
            'core_update' => ['nullable', 'string', 'max:32'],
            'theme' => ['nullable', 'string', 'max:120'],
            'plugins_total' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'plugin_updates' => ['nullable', 'array', 'max:200'],
            'plugin_updates.*.name' => ['required', 'string', 'max:190'],
            'plugin_updates.*.from' => ['nullable', 'string', 'max:32'],
            'plugin_updates.*.to' => ['nullable', 'string', 'max:32'],
            'theme_updates' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'disk_free_mb' => ['nullable', 'integer', 'min:0'],
            'https' => ['nullable', 'boolean'],
            'command_url' => ['nullable', 'string', 'max:255'],
            'remote_fixes' => ['nullable', 'boolean'],
            'backup_schedule' => ['nullable', Rule::in(['daily', 'weekly', 'off'])],
            'backup_keep' => ['nullable', 'integer', 'min:1', 'max:60'],
        ])->validate();

        $host = strtolower((string) parse_url($data['site_url'], PHP_URL_HOST));
        $matches = $host === $site->domain || $host === 'www.'.$site->domain;
        $this->context->runAs($site->organization, function () use ($site, $data, $matches): void {
            $site->forceFill([
                'health' => array_diff_key($data, array_flip(['connector_version'])) + ['received_at' => now()->toIso8601String()],
                'connector_version' => $data['connector_version'],
                'last_seen_at' => now(),
                'verification_status' => $matches ? 'verified' : 'mismatch',
            ])->save();
            app(BackupMonitor::class)->evaluate($site);
        });

        return response()->json(['ok' => true, 'site' => $site->domain, 'verified' => $matches, 'next_heartbeat_seconds' => 3600,
            'cookie_banner' => (bool) CookieSettings::for($site)['enabled']]);
    }

    /** Lucrări înregistrate automat pe site (ex. actualizări făcute din WordPress). Retrimiterea nu dublează. */
    public function worklog(Request $request, WorkLogService $logs): JsonResponse
    {
        $site = $this->authenticate($request);
        if (! $site) {
            return $this->unauthorized();
        }
        $data = Validator::make($request->json()->all(), [
            'entries' => ['required', 'array', 'min:1', 'max:50'],
            'entries.*.ref' => ['required', 'string', 'max:64'],
            'entries.*.category' => ['required', Rule::enum(WorkCategory::class)],
            'entries.*.title' => ['required', 'string', 'max:190'],
            'entries.*.description' => ['nullable', 'string', 'max:2000'],
            'entries.*.performed_at' => ['nullable', 'date'],
        ])->validate();

        [$saved, $skipped] = $this->context->runAs($site->organization, function () use ($site, $data, $logs): array {
            $saved = $skipped = 0;
            foreach ($data['entries'] as $entry) {
                // ref-ul e unic per site: același eveniment trimis de două ori nu apare de două ori
                $ref = substr(hash('sha256', $site->id.':'.$entry['ref']), 0, 64);
                if (WorkLog::query()->where('external_ref', $ref)->exists()) {
                    $skipped++;

                    continue;
                }
                $performed = isset($entry['performed_at']) ? Carbon::parse($entry['performed_at']) : now();
                $logs->create([
                    'site_id' => $site->id,
                    'category' => $entry['category'],
                    'title' => $entry['title'],
                    'description' => $entry['description'] ?? null,
                    'performed_at' => $performed->isFuture() ? now() : $performed,
                    'visible_to_client' => true,
                ], null, 'plugin', $ref);
                $saved++;
            }

            return [$saved, $skipped];
        });

        return response()->json(['ok' => true, 'saved' => $saved, 'skipped' => $skipped]);
    }

    /** Evenimentele magazinului WooCommerce (produs văzut, coș, comandă începută / plasată). Retrimiterea nu dublează. */
    public function events(Request $request, ShopEvents $shop): JsonResponse
    {
        $site = $this->authenticate($request);
        if (! $site) {
            return $this->unauthorized();
        }
        $data = Validator::make($request->json()->all(), [
            'events' => ['required', 'array', 'min:1', 'max:100'],
            'events.*.id' => ['required', 'string', 'max:80'],
            'events.*.type' => ['required', Rule::in(ShopEvents::TYPES)],
            'events.*.email' => ['nullable', 'string', 'max:190'],
            'events.*.phone' => ['nullable', 'string', 'max:40'],
            'events.*.first_name' => ['nullable', 'string', 'max:80'],
            'events.*.last_name' => ['nullable', 'string', 'max:80'],
            'events.*.contact_token' => ['nullable', 'string', 'max:60'],
            'events.*.value' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'events.*.occurred_at' => ['nullable', 'integer', 'min:0'],
            'events.*.marketing_consent' => ['nullable', 'boolean'],
            'events.*.consent_text' => ['nullable', 'string', 'max:300'],
            'events.*.data' => ['nullable', 'array'],
            'events.*.data.items' => ['nullable', 'array', 'max:50'],
            'events.*.data.items.*.name' => ['nullable', 'string', 'max:200'],
            'events.*.data.items.*.product_id' => ['nullable', 'max:40'],
            'events.*.data.items.*.qty' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'events.*.data.items.*.price' => ['nullable', 'numeric', 'min:0'],
            'events.*.data.items.*.url' => ['nullable', 'string', 'max:500'],
            'events.*.data.items.*.image' => ['nullable', 'string', 'max:500'],
            'events.*.data.*' => ['nullable'],
        ])->validate();
        $result = $this->context->runAs($site->organization, fn () => $shop->ingest($site, $data['events']));

        return response()->json(['ok' => true] + $result);
    }

    /** Catalogul de produse (pe bucăți de cel mult 100). */
    public function products(Request $request, ShopEvents $shop): JsonResponse
    {
        $site = $this->authenticate($request);
        if (! $site) {
            return $this->unauthorized();
        }
        $data = Validator::make($request->json()->all(), [
            'products' => ['required', 'array', 'min:1', 'max:100'],
            'products.*.id' => ['required', 'max:40'],
            'products.*.deleted' => ['nullable', 'boolean'],
            'products.*.name' => ['required_unless:products.*.deleted,true', 'nullable', 'string', 'max:200'],
            'products.*.price' => ['nullable', 'numeric', 'min:0'],
            'products.*.currency' => ['nullable', 'string', 'max:3'],
            'products.*.url' => ['nullable', 'string', 'max:500'],
            'products.*.image' => ['nullable', 'string', 'max:500'],
            'products.*.categories' => ['nullable', 'array', 'max:20'],
            'products.*.in_stock' => ['nullable', 'boolean'],
        ])->validate();
        $result = $this->context->runAs($site->organization, fn () => $shop->syncProducts($site, $data['products']));

        return response()->json(['ok' => true] + $result);
    }

    /** Rezultatul scanării de securitate / sănătate făcute de plugin. */
    public function scan(Request $request, SiteScanService $scans): JsonResponse
    {
        $site = $this->authenticate($request);
        if (! $site) {
            return $this->unauthorized();
        }
        $issues = Validator::make($request->json()->all(), ScanPayload::rules())->validate()['issues'];
        $this->context->runAs($site->organization, fn () => $scans->ingest($site, $issues));

        return response()->json(['ok' => true, 'open' => count($issues)]);
    }

    /** Rezultatul unui backup făcut de plugin pe hostingul clientului. */
    public function backup(Request $request, BackupMonitor $monitor): JsonResponse
    {
        $site = $this->authenticate($request);
        if (! $site) {
            return $this->unauthorized();
        }
        $data = Validator::make($request->json()->all(), [
            'status' => ['required', Rule::in(['ok', 'failed'])],
            'verified' => ['nullable', 'boolean'],
            'started_at' => ['required', 'date'],
            'finished_at' => ['nullable', 'date'],
            'db_bytes' => ['nullable', 'integer', 'min:0'],
            'files_bytes' => ['nullable', 'integer', 'min:0'],
            'files_count' => ['nullable', 'integer', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'kept' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'error' => ['nullable', 'string', 'max:2000'],
        ])->validate();
        $this->context->runAs($site->organization, fn () => $monitor->record($site, $data));

        return response()->json(['ok' => true]);
    }

    /** Versiunea curentă a pluginului (public): pluginul o folosește ca să se actualizeze din WordPress. */
    public function plugin(): JsonResponse
    {
        $info = json_decode((string) @file_get_contents(public_path('downloads/vitim-connector.json')), true);
        if (! is_array($info) || empty($info['version'])) {
            return response()->json(['error' => ['code' => 'not_found', 'message' => 'Pachetul pluginului lipsește.']], 404);
        }

        return response()->json(['version' => $info['version'], 'download_url' => asset('downloads/vitim-connector.zip'), 'requires_php' => '7.4']);
    }

    private function authenticate(Request $request): ?Site
    {
        return $this->keys->verifySignedRequest(
            (string) $request->header('X-Vitim-Key'),
            (string) $request->header('X-Vitim-Timestamp'),
            (string) $request->header('X-Vitim-Nonce'),
            $request->getContent(),
            (string) $request->header('X-Vitim-Signature'),
        );
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json(['error' => ['code' => 'invalid_signature', 'message' => 'Semnătură invalidă, cheie revocată sau ceas nesincronizat.']], 401);
    }
}
