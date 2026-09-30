<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Limitare simplă pe fișiere (funcționează pe orice hosting partajat).
 */
final class RateLimit
{
    private static function file(string $key): string
    {
        return STORAGE_PATH . '/ratelimit/' . sha1($key) . '.json';
    }

    /** Întoarce false dacă limita a fost depășită. */
    public static function hit(string $key, int $max, int $window): bool
    {
        $f = self::file($key);
        $now = time();
        $data = ['n' => 0, 'reset' => $now + $window];
        if (is_file($f)) {
            $d = json_decode((string)file_get_contents($f), true);
            if (is_array($d) && ($d['reset'] ?? 0) > $now) {
                $data = $d;
            }
        }
        $data['n']++;
        @file_put_contents($f, json_encode($data), LOCK_EX);
        return $data['n'] <= $max;
    }

    public static function clear(string $key): void
    {
        @unlink(self::file($key));
    }

    public static function gc(): void
    {
        foreach (glob(STORAGE_PATH . '/ratelimit/*.json') ?: [] as $f) {
            if (filemtime($f) < time() - 86400) {
                @unlink($f);
            }
        }
    }
}
