<?php
declare(strict_types=1);

namespace App\Core;

final class App
{
    public static string $path = '/';

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
            self::tryRedirect(rtrim($uri, '/')); // adrese vechi (ex. WordPress) → direct la destinație, fără lanț de redirecționări
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

        if (!$isAdmin && Settings::get('maintenance_mode') === '1' && !isset($_COOKIE['vitim_sess'])) {
            http_response_code(503);
            header('Retry-After: 3600');
            echo View::partial('site/maintenance');
            return;
        }

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
            if (!$isAdmin && $method === 'GET') {
                Cache::store($uri, $out, (int)$code);
            }
            echo $out;
        }
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
