<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = random_token(32);
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function verify(): bool
    {
        $t = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return is_string($t) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $t);
    }

    /**
     * Token pentru formulare publice (paginile sunt în cache, deci nu folosim sesiunea).
     * Conține momentul emiterii, semnat HMAC.
     */
    public static function formToken(): string
    {
        $t = (string)time();
        return $t . '.' . substr(sign('form|' . $t), 0, 32);
    }

    /** Verifică tokenul public: semnat, emis cu 3 sec – 12 ore în urmă. */
    public static function verifyFormToken(?string $token, int $minAge = 3): bool
    {
        if (!$token || !str_contains($token, '.')) {
            return false;
        }
        [$t, $sig] = explode('.', $token, 2);
        if (!ctype_digit($t) || !hash_equals(substr(sign('form|' . $t), 0, 32), $sig)) {
            return false;
        }
        $age = time() - (int)$t;
        return $age >= $minAge && $age <= 43200;
    }

    public static function sameOrigin(): bool
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
        if ($origin === '') {
            return true; // unele browsere/proxy nu trimit; rămân celelalte protecții
        }
        $host = (string)parse_url($origin, PHP_URL_HOST);
        if ($host === '') {
            return false;
        }
        $reqHost = (string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
        $siteHost = (string)parse_url((string)config('site_url', ''), PHP_URL_HOST);
        return strcasecmp($host, $reqHost) === 0 || ($siteHost !== '' && strcasecmp($host, $siteHost) === 0);
    }
}
