<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Codul de conectare pe care îl lipești în plugin / conector: adresa platformei + cheia publică + secretul,
 * într-un singur șir. Se afișează o singură dată, ca secretul.
 */
final class ConnectionCode
{
    private const PREFIX = 'VITIM1-';

    public static function encode(string $platformUrl, string $publicKey, string $secret): string
    {
        $json = (string) json_encode(['u' => rtrim($platformUrl, '/'), 'k' => $publicKey, 's' => $secret], JSON_UNESCAPED_SLASHES);

        return self::PREFIX.rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /** @return array{u: string, k: string, s: string}|null */
    public static function decode(string $code): ?array
    {
        $code = trim($code);
        if (! str_starts_with($code, self::PREFIX)) {
            return null;
        }
        $data = json_decode((string) base64_decode(strtr(substr($code, strlen(self::PREFIX)), '-_', '+/')), true);

        return is_array($data) && isset($data['u'], $data['k'], $data['s']) ? $data : null;
    }
}
