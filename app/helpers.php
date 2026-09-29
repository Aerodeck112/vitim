<?php
declare(strict_types=1);

use App\Core\Settings;

function config(string $key, mixed $default = null): mixed
{
    $cfg = $GLOBALS['__config'] ?? null;
    if (!$cfg) {
        return $default;
    }
    $cur = $cfg;
    foreach (explode('.', $key) as $k) {
        if (!is_array($cur) || !array_key_exists($k, $cur)) {
            return $default;
        }
        $cur = $cur[$k];
    }
    return $cur;
}

function e(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function setting(string $key, mixed $default = ''): mixed
{
    return Settings::get($key, $default);
}

/** Calea de bază (dacă site-ul e instalat într-un subdirector). */
function base_path(): string
{
    static $bp = null;
    if ($bp === null) {
        $cfg = config('base_path');
        if ($cfg !== null) {
            $bp = rtrim((string)$cfg, '/');
        } else {
            $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
            $dir = rtrim(dirname($script), '/');
            if (str_ends_with($dir, '/install')) {
                $dir = substr($dir, 0, -8);
            }
            $bp = ($dir === '.' || $dir === '/') ? '' : $dir;
        }
    }
    return $bp;
}

function url(string $path = '/'): string
{
    if (preg_match('#^(https?:)?//#', $path) || str_starts_with($path, 'mailto:') || str_starts_with($path, 'tel:')) {
        return $path;
    }
    return base_path() . '/' . ltrim($path, '/');
}

/** URL absolut (pentru canonical, sitemap, OG, email). */
function abs_url(string $path = '/'): string
{
    if (preg_match('#^https?://#', $path)) {
        return $path;
    }
    $base = rtrim((string)config('site_url', ''), '/');
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http';
        $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_path();
    }
    $p = '/' . ltrim($path, '/');
    return $base . ($p === '/' ? '/' : $p);
}

function asset(string $path): string
{
    $file = ROOT_PATH . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr(md5((string)filemtime($file) . APP_VERSION), 0, 8) : APP_VERSION;
    return url('/assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function upload_url(?string $path): string
{
    if (!$path) {
        return '';
    }
    if (preg_match('#^https?://#', $path)) {
        return $path;
    }
    return url('/uploads/' . ltrim($path, '/'));
}

function slugify(string $text): string
{
    $map = ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
        'Ă' => 'a', 'Â' => 'a', 'Î' => 'i', 'Ș' => 's', 'Ş' => 's', 'Ț' => 't', 'Ţ' => 't'];
    $text = strtr($text, $map);
    if (function_exists('transliterator_transliterate')) {
        $text = (string)transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
    }
    $text = strtolower($text);
    $text = (string)preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'pagina';
}

function redirect(string $to, int $code = 302): never
{
    header('Location: ' . (preg_match('#^https?://#', $to) ? $to : url($to)), true, $code);
    exit;
}

function json_out(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function input(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function str_input(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function client_ip(): string
{
    return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function log_error(\Throwable|string $e): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . (is_string($e) ? $e : get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()) . PHP_EOL;
    @file_put_contents(STORAGE_PATH . '/logs/app.log', $line, FILE_APPEND | LOCK_EX);
}

function flash(string $type, string $msg): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $default;
}

function json_list(?string $json): array
{
    if (!$json) {
        return [];
    }
    $d = json_decode($json, true);
    return is_array($d) ? $d : [];
}

function excerpt(string $html, int $len = 160): string
{
    $t = trim((string)preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
    if (mb_strlen($t) <= $len) {
        return $t;
    }
    $cut = mb_substr($t, 0, $len);
    $sp = mb_strrpos($cut, ' ');
    return rtrim(mb_substr($cut, 0, $sp ?: $len), ' ,.;:') . '…';
}

function ro_date(?string $datetime, bool $withTime = false): string
{
    if (!$datetime) {
        return '';
    }
    $months = ['', 'ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
    $ts = strtotime($datetime . (str_contains($datetime, 'T') || str_ends_with($datetime, 'Z') ? '' : ' UTC'));
    if (!$ts) {
        return '';
    }
    $s = date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . date('Y', $ts);
    return $withTime ? $s . ', ' . date('H:i', $ts) : $s;
}

/** Transformă UTC din DB în ora locală pentru afișare în admin. */
function local_time(?string $utc, string $fmt = 'd.m.Y H:i'): string
{
    if (!$utc) {
        return '—';
    }
    $ts = strtotime($utc . ' UTC');
    return $ts ? date($fmt, $ts) : '—';
}

function reading_time(string $html): int
{
    $words = str_word_count(strip_tags($html), 0, 'ăâîșțĂÂÎȘȚ');
    return max(1, (int)ceil($words / 220));
}

function random_token(int $bytes = 24): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

function app_key(): string
{
    return (string)config('app_key', 'vitim-default-key');
}

function sign(string $data): string
{
    return hash_hmac('sha256', $data, app_key());
}

function phone_href(string $phone): string
{
    $p = preg_replace('/[^0-9+]/', '', $phone);
    if (str_starts_with($p, '07')) {
        $p = '+4' . $p;
    }
    return 'tel:' . $p;
}

function whatsapp_href(string $phone, string $text = ''): string
{
    $p = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($p, '07')) {
        $p = '4' . $p;
    }
    return 'https://wa.me/' . $p . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

function icon(string $name, string $class = 'ico'): string
{
    return \App\Core\Icons::svg($name, $class);
}

function is_installed(): bool
{
    return $GLOBALS['__config'] !== null;
}
