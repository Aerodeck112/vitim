<?php

declare(strict_types=1);

namespace App\Security;

/**
 * TOTP (RFC 6238, SHA-1, 6 cifre, 30 s) — compatibil Google Authenticator, Microsoft Authenticator, 2FAS.
 * Portat din site-ul vitim.ro (app/Core/Totp.php).
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function secret(int $length = 32): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::ALPHABET[random_int(0, 31)];
        }

        return $secret;
    }

    public static function uri(string $secret, string $account, string $issuer = 'VITIM AI'): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account).'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&digits=6&period=30';
    }

    public static function verify(string $secret, string $code, int $window = 1, ?int $time = null): bool
    {
        $code = (string) preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return false;
        }
        $slice = intdiv($time ?? time(), 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($secret, $slice + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function code(string $secret, int $slice): string
    {
        $hash = hash_hmac('sha1', pack('N*', 0).pack('N*', $slice), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0xF;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24) | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8) | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $b32): string
    {
        $b32 = strtoupper((string) preg_replace('/[^A-Z2-7]/i', '', $b32));
        $bits = '';
        foreach (str_split($b32) as $char) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr((int) bindec($byte));
            }
        }

        return $out;
    }
}
