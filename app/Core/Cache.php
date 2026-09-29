<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Cache de pagini HTML complete pentru vizitatori — TTFB foarte mic chiar și pe hosting partajat.
 */
final class Cache
{
    /** Parametri de campanie ignorați la cheia de cache (trafic din reclame servit tot din cache). */
    private const IGNORED_PARAMS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'ttclid', 'mc_cid', 'mc_eid', '_ga', 'ref'];

    public static function enabled(): bool
    {
        return (bool)(int)Settings::get('page_cache', '1') && !config('debug');
    }

    public static function key(string $path): ?string
    {
        $q = $_GET;
        foreach (self::IGNORED_PARAMS as $p) {
            unset($q[$p]);
        }
        if ($q) {
            return null; // nu punem în cache variante cu parametri necunoscuți
        }
        return sha1($path);
    }

    public static function file(string $key): string
    {
        return STORAGE_PATH . '/cache/pages/' . $key . '.html';
    }

    public static function serve(string $path): bool
    {
        if (!self::enabled() || request_method() !== 'GET' || isset($_COOKIE['vitim_sess'])) {
            return false;
        }
        $key = self::key($path);
        if (!$key) {
            return false;
        }
        $f = self::file($key);
        $ttl = (int)Settings::get('page_cache_ttl', '3600');
        if (!is_file($f) || filemtime($f) < time() - $ttl) {
            return false;
        }
        $html = (string)file_get_contents($f);
        $meta = json_decode((string)@file_get_contents($f . '.meta'), true) ?: [];
        http_response_code((int)($meta['code'] ?? 200));
        header('Content-Type: text/html; charset=utf-8');
        header('X-Cache: HIT');
        header('Cache-Control: public, max-age=0, must-revalidate');
        $etag = '"' . md5($html) . '"';
        header('ETag: ' . $etag);
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            return true;
        }
        echo $html;
        return true;
    }

    public static function store(string $path, string $html, int $code = 200): void
    {
        if (!self::enabled() || request_method() !== 'GET' || isset($_COOKIE['vitim_sess']) || !in_array($code, [200, 404], true)) {
            return;
        }
        $key = self::key($path);
        if (!$key) {
            return;
        }
        @file_put_contents(self::file($key), $html, LOCK_EX);
        @file_put_contents(self::file($key) . '.meta', json_encode(['code' => $code, 'path' => $path]), LOCK_EX);
    }

    public static function clear(): int
    {
        $n = 0;
        foreach (glob(STORAGE_PATH . '/cache/pages/*') ?: [] as $f) {
            if (@unlink($f)) {
                $n++;
            }
        }
        foreach (glob(STORAGE_PATH . '/cache/og/*') ?: [] as $f) {
            @unlink($f);
        }
        return (int)($n / 2);
    }
}
