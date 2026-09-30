<?php
declare(strict_types=1);

/**
 * Bunătăți de la Michele — pornirea aplicației.
 */

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('Este necesar PHP 8.1 sau mai nou. Versiunea curentă: ' . PHP_VERSION . '. Schimbă versiunea din cPanel → Select PHP Version / MultiPHP Manager.');
}

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', __DIR__);
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('CONFIG_FILE', APP_PATH . '/config.php');
define('APP_VERSION', trim((string)@file_get_contents(ROOT_PATH . '/VERSION')) ?: '0.0.0');

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Bucharest');

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

if (is_file(APP_PATH . '/vendor/autoload.php')) {
    require APP_PATH . '/vendor/autoload.php';
}

require APP_PATH . '/helpers.php';

foreach (['cache', 'cache/pages', 'logs', 'backups', 'tmp', 'ratelimit'] as $d) {
    if (!is_dir(STORAGE_PATH . '/' . $d)) {
        @mkdir(STORAGE_PATH . '/' . $d, 0755, true);
    }
}

$GLOBALS['__config'] = is_file(CONFIG_FILE) ? (require CONFIG_FILE) : null;

$debug = (bool)(config('debug') ?? false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

set_exception_handler(function (\Throwable $e) use ($debug): void {
    // cod scurt afișat vizitatorului și scris în storage/logs/app.log, ca eroarea să poată fi găsită ușor
    $ref = substr(sha1(uniqid('', true)), 0, 8);
    log_error('#' . $ref . ' ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, (string)$e . PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    if ($debug) {
        echo '<pre style="white-space:pre-wrap;font:13px monospace;padding:20px">' . e((string)$e) . '</pre>';
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>Eroare</title><body style="font-family:system-ui;background:#f6efe6;color:#2b1a12;display:grid;place-items:center;min-height:100vh;margin:0"><div style="text-align:center"><h1>Ceva nu a mers bine</h1><p>Te rugăm să încerci din nou în câteva momente.</p><p><a style="color:#a8823f" href="/">Înapoi la prima pagină</a></p><p style="opacity:.45;font-size:12px">Cod: ' . $ref . '</p></div>';
    }
});

if ($GLOBALS['__config']) {
    try {
        \App\Core\DB::connect($GLOBALS['__config']['db']);
    } catch (\Throwable $e) {
        if (PHP_SAPI === 'cli') {
            throw $e;
        }
        // baza de date nu răspunde (server MySQL oprit, parolă schimbată, limită de conexiuni):
        // vizitatorii primesc ultima versiune salvată a paginii sau o pagină cu datele de contact
        \App\Core\App::offline($e);
    }
}
