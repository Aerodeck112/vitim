<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Throwable;

/**
 * Legătura unui site Laravel cu panoul VITIM AI (ai.vitim.ro). Un singur fișier, fără pachete în plus.
 *
 * Instalare (vezi și README.md de lângă acest fișier):
 *  1. Copiază fișierul în app/Support/Vitim.php.
 *  2. În .env: VITIM_CONNECTION_CODE=VITIM1-...   (codul de conectare al site-ului, din panoul VITIM)
 *     În config/services.php: 'vitim' => ['code' => env('VITIM_CONNECTION_CODE')],
 *  3. În layoutul public, înainte de </body>:   {{ \App\Support\Vitim::widget() }}
 *  4. În routes/console.php:   Schedule::call(fn () => \App\Support\Vitim::heartbeat())->hourly();
 *  5. În controllerul formularului de contact, după salvare:
 *       \App\Support\Vitim::lead(['name' => ..., 'email' => ..., 'phone' => ..., 'message' => ..., 'intent' => 'quote_request']);
 *
 * Fără cod de conectare, toate metodele nu fac nimic: site-ul merge la fel. O eroare de rețea nu oprește niciodată site-ul.
 */
final class Vitim
{
    public const VERSION = '1.0.0';

    /** Intențiile acceptate de panou pentru o cerere. */
    public const INTENTS = ['buy_intent', 'quote_request', 'appointment', 'support', 'product_information', 'complaint', 'human_request', 'other'];

    /** Scriptul asistentului de chat (și al vizitatorilor „acum pe site”). Gol dacă site-ul nu e conectat. */
    public static function widget(): HtmlString
    {
        $config = self::config();

        return new HtmlString($config === null ? '' : '<script src="'.e($config['u']).'/widget/v1/loader.js" data-site="'.e($config['k']).'" async></script>');
    }

    /** Semnalul orar către panou: site-ul e în viață, cu versiunile lui. Se pune în scheduler. */
    public static function heartbeat(): bool
    {
        $free = @disk_free_space(base_path());

        return self::post('heartbeat', array_filter([
            'platform' => 'custom',
            'site_url' => (string) config('app.url'),
            'connector_version' => self::VERSION,
            'php_version' => PHP_VERSION,
            'core_version' => 'Laravel '.app()->version(),
            'app_version' => is_file(base_path('VERSION')) ? Str::limit(trim((string) file_get_contents(base_path('VERSION'))), 30, '') : null,
            'disk_free_mb' => $free !== false ? (int) ($free / 1_048_576) : null,
            'https' => str_starts_with((string) config('app.url'), 'https://'),
        ], fn ($v) => $v !== null));
    }

    /**
     * Trimite în panou o cerere din formularul site-ului. Are nevoie de email sau telefon.
     * marketing_consent se trimite doar dacă omul a bifat explicit acordul pentru noutăți.
     *
     * @param  array{name?: ?string, email?: ?string, phone?: ?string, message?: ?string, intent?: string, page?: ?string, marketing_consent?: bool, consent_text?: ?string, ref?: string}  $data
     */
    public static function lead(array $data): bool
    {
        if (empty($data['email']) && empty($data['phone'])) {
            return false;
        }
        $data['ref'] ??= (string) Str::uuid();
        $data['intent'] = in_array($data['intent'] ?? '', self::INTENTS, true) ? $data['intent'] : 'other';
        $data['page'] ??= self::page();

        return self::post('lead', array_filter($data, fn ($v) => $v !== null && $v !== ''));
    }

    /** O lucrare în jurnalul clientului („Lucrări VITIM”), de exemplu după o actualizare a aplicației. */
    public static function worklog(string $category, string $title, ?string $description = null): bool
    {
        return self::post('worklog', ['entries' => [array_filter(['ref' => sha1($category.$title.date('Y-m-d')), 'category' => $category, 'title' => Str::limit($title, 180, ''),
            'description' => $description, 'performed_at' => now()->toIso8601String()], fn ($v) => $v !== null)]]);
    }

    public static function connected(): bool
    {
        return self::config() !== null;
    }

    /** @param array<string, mixed> $payload */
    private static function post(string $endpoint, array $payload): bool
    {
        $config = self::config();
        if ($config === null) {
            return false;
        }
        try {
            $body = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $ts = (string) time();
            $nonce = bin2hex(random_bytes(16));

            return Http::timeout(8)->connectTimeout(4)->acceptJson()->withHeaders([
                'X-Vitim-Key' => $config['k'],
                'X-Vitim-Timestamp' => $ts,
                'X-Vitim-Nonce' => $nonce,
                'X-Vitim-Signature' => hash_hmac('sha256', $ts.'.'.$nonce.'.'.$body, $config['s']),
            ])->withBody($body, 'application/json')->post($config['u'].'/connector/v1/'.$endpoint)->successful();
        } catch (Throwable $e) {
            report($e); // panoul indisponibil nu trebuie să strice formularul sau cronul site-ului

            return false;
        }
    }

    /** @return array{u: string, k: string, s: string}|null */
    private static function config(): ?array
    {
        $code = trim((string) config('services.vitim.code'));
        if (! str_starts_with($code, 'VITIM1-')) {
            return null;
        }
        $data = json_decode((string) base64_decode(strtr(substr($code, 7), '-_', '+/'), true), true);
        if (! is_array($data) || empty($data['u']) || empty($data['k']) || empty($data['s']) || ! str_starts_with((string) $data['u'], 'https://')) {
            return null;
        }

        return ['u' => rtrim((string) $data['u'], '/'), 'k' => (string) $data['k'], 's' => (string) $data['s']];
    }

    private static function page(): ?string
    {
        try {
            if (app()->runningInConsole()) {
                return null;
            }
            $path = Str::limit((string) parse_url((string) request()->headers->get('referer'), PHP_URL_PATH), 290, '');

            return $path !== '' ? $path : null;
        } catch (Throwable) {
            return null;
        }
    }
}
