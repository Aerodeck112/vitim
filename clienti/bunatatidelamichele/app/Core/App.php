<?php
declare(strict_types=1);

namespace App\Core;

final class App
{
    public static string $path = '/';

    /** Paginile personale (coș, finalizare, comandă) nu intră în cache-ul de pagini. */
    public static bool $noCache = false;

    public static function run(): void
    {
        if (!is_installed()) {
            header('Location: ' . base_path() . '/install/');
            exit;
        }

        $uri = rawurldecode(strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/');
        $bp = base_path();
        if ($bp !== '' && str_starts_with($uri, $bp)) {
            $uri = substr($uri, strlen($bp)) ?: '/';
        }
        $uri = '/' . ltrim($uri, '/');
        $uri = (string)preg_replace('#/+#', '/', $uri);

        // fără slash final (URL canonic unic) – redirecționare 301
        if ($uri !== '/' && str_ends_with($uri, '/') && !str_starts_with($uri, '/admin')) {
            self::tryRedirect(rtrim($uri, '/')); // adrese vechi WordPress → direct la destinație, fără lanț de redirecționări
            $qs = $_SERVER['QUERY_STRING'] ?? '';
            redirect(rtrim($uri, '/') . ($qs !== '' ? '?' . $qs : ''), 301);
        }
        self::$path = $uri;
        $isAdmin = $uri === '/admin' || str_starts_with($uri, '/admin/');

        self::securityHeaders($isAdmin);

        if ($isAdmin) {
            Auth::startSession();
        } elseif (Cache::serve($uri)) {
            return;
        }

        // actualizare în curs → migrările rulează automat
        if (!$isAdmin && Settings::get('db_version', '') !== APP_VERSION) {
            try {
                Migrator::run();
                Settings::set('db_version', APP_VERSION);
            } catch (\Throwable $e) {
                log_error($e);
            }
        }

        if (!$isAdmin && Settings::get('maintenance_mode') === '1' && !isset($_COOKIE['bdm_admin']) && !str_starts_with($uri, '/plata/')) {
            http_response_code(503);
            header('Retry-After: 3600');
            echo View::partial('site/maintenance');
            return;
        }

        self::snapshotContact();

        $router = new Router();
        require APP_PATH . '/routes.php';

        $method = request_method();
        $match = $router->match($method, $uri);

        if (!$match && !$isAdmin) {
            self::tryRedirect($uri);
        }
        if (!$match) {
            self::notFound($uri, $isAdmin);
            return;
        }

        [$handler, $params] = $match;
        $out = Router::call($handler, $params);
        if (is_string($out)) {
            $code = http_response_code() ?: 200;
            if (!$isAdmin && $method === 'GET' && !self::$noCache) {
                Cache::store($uri, $out, (int)$code);
            }
            if (self::$noCache && !$isAdmin) {
                header('Cache-Control: no-store, private');
            }
            echo $out;
        }
    }

    /** Datele de contact salvate pe disc, ca să poată fi afișate și când baza de date nu răspunde. */
    private static function snapshotContact(): void
    {
        $f = STORAGE_PATH . '/cache/contact.json';
        if (is_file($f) && filemtime($f) > time() - 3600) {
            return;
        }
        @file_put_contents($f, json_encode([
            'brand' => Settings::get('brand_name', 'Bunătăți de la Michele'),
            'phone' => Settings::get('phone', ''),
            'email' => Settings::get('email', ''),
            'whatsapp' => Settings::get('whatsapp', ''),
        ], JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Baza de date nu e disponibilă: servim pagina din cache (chiar dacă e mai veche) sau o pagină
     * „revenim imediat” cu datele de contact, cu cod 503 (Google nu penalizează o indisponibilitate scurtă).
     */
    public static function offline(\Throwable $e): never
    {
        $ref = substr(sha1(uniqid('', true)), 0, 8);
        log_error('#' . $ref . ' Baza de date nu răspunde – ' . get_class($e) . ': ' . $e->getMessage());

        $uri = '/' . ltrim(rawurldecode(strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/'), '/');
        $bp = base_path();
        if ($bp !== '' && str_starts_with($uri, $bp)) {
            $uri = '/' . ltrim(substr($uri, strlen($bp)), '/');
        }
        $uri = $uri !== '/' ? rtrim($uri, '/') : $uri;
        if (request_method() === 'GET' && !str_starts_with($uri, '/admin') && ($key = Cache::key($uri))) {
            $f = Cache::file($key);
            $meta = json_decode((string)@file_get_contents($f . '.meta'), true) ?: [];
            if (is_file($f) && (int)($meta['code'] ?? 200) === 200) {
                header('Content-Type: text/html; charset=utf-8');
                header('X-Cache: STALE');
                header('Cache-Control: no-store');
                readfile($f);
                exit;
            }
        }

        $c = json_decode((string)@file_get_contents(STORAGE_PATH . '/cache/contact.json'), true) ?: [];
        $c += ['brand' => 'Bunătăți de la Michele', 'phone' => '', 'email' => '', 'whatsapp' => ''];
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || str_starts_with($uri, '/api/')) {
            http_response_code(503);
            header('Content-Type: application/json; charset=utf-8');
            header('Retry-After: 300');
            echo json_encode(['ok' => false, 'error' => 'Magazinul este temporar indisponibil. Te rugăm să revii în câteva minute' . ($c['email'] ? ' sau să ne scrii la ' . $c['email'] : '') . '.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        http_response_code(503);
        header('Retry-After: 300');
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        $tel = preg_replace('/[^0-9+]/', '', (string)$c['phone']);

        $btn = 'display:block;margin:10px auto;max-width:280px;padding:14px 18px;border-radius:12px;text-decoration:none;font-weight:600;';
        echo '<!doctype html><html lang="ro"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . e($c['brand']) . ' – revenim imediat</title>'
            . '<body style="font-family:system-ui,sans-serif;background:#f6efe6;color:#2b1a12;display:grid;place-items:center;min-height:100vh;margin:0;padding:24px;box-sizing:border-box"><div style="text-align:center;max-width:460px">'
            . '<p style="font-weight:700;letter-spacing:.06em;opacity:.8">' . e($c['brand']) . '</p>'
            . '<h1 style="font-size:26px;margin:.2em 0 .5em">Revenim în câteva minute</h1>'
            . '<p style="opacity:.8;line-height:1.5">Magazinul este temporar indisponibil. Comenzile tale sunt în siguranță. Între timp ne poți contacta direct.</p>'
            . ($tel ? '<a style="' . $btn . 'background:#b5602c;color:#fff" href="tel:' . e($tel) . '">Sună: ' . e($c['phone']) . '</a>' : '')
            . ($c['email'] ? '<a style="' . $btn . 'border:1px solid #d9c8b4;color:#2b1a12" href="mailto:' . e($c['email']) . '">' . e($c['email']) . '</a>' : '')
            . '<p style="opacity:.4;font-size:12px;margin-top:24px">Cod: ' . $ref . '</p></div></body></html>';
        exit;
    }

    public static function notFound(string $uri, bool $isAdmin = false): void
    {
        http_response_code(404);
        if ($isAdmin) {
            echo View::render('admin/404', [], 'admin/layout');
            return;
        }
        try {
            if (!preg_match('#\.(php|env|git|sql|bak|zip|xml\.gz)$#i', $uri) && strlen($uri) < 250) {
                $row = DB::row('SELECT id FROM not_found_log WHERE path = ?', [$uri]);
                if ($row) {
                    DB::q('UPDATE not_found_log SET hits = hits + 1, last_seen_at = ? WHERE id = ?', [DB::now(), $row['id']]);
                } else {
                    DB::insert('not_found_log', ['path' => $uri, 'referer' => mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 250), 'hits' => 1, 'last_seen_at' => DB::now()]);
                }
            }
        } catch (\Throwable) {
        }
        echo (new \App\Controllers\Site\PageController())->notFound();
    }

    public static function tryRedirect(string $uri): void
    {
        $candidates = [$uri, $uri . '/', rtrim($uri, '/')];
        $r = DB::row('SELECT * FROM redirects WHERE from_path IN (?, ?, ?) LIMIT 1', $candidates);
        if (!$r) {
            return;
        }
        DB::q('UPDATE redirects SET hits = hits + 1, last_hit_at = ? WHERE id = ?', [DB::now(), $r['id']]);
        if ((int)$r['code'] === 410) {
            http_response_code(410);
            echo (new \App\Controllers\Site\PageController())->notFound(true);
            exit;
        }
        redirect($r['to_path'], in_array((int)$r['code'], [301, 302, 307, 308], true) ? (int)$r['code'] : 301);
    }

    private static function securityHeaders(bool $admin): void
    {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: SAMEORIGIN');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()');
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        if ($https) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        if ($admin) {
            header('Cache-Control: no-store, private');
            header('X-Robots-Tag: noindex, nofollow');
        }
    }
}
