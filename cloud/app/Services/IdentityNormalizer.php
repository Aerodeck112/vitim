<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Forma canonică a identificatorilor, folosită la deduplicare și la lista de suprimări.
 * Telefon: format E.164 (+40712345678). Fără prefix internațional, se folosește țara firmei.
 */
final class IdentityNormalizer
{
    private const COUNTRY_CODES = [
        'RO' => '40', 'MD' => '373', 'HU' => '36', 'BG' => '359', 'AT' => '43', 'DE' => '49',
        'IT' => '39', 'ES' => '34', 'FR' => '33', 'GB' => '44', 'NL' => '31', 'BE' => '32',
    ];

    public static function email(?string $value): ?string
    {
        $value = mb_strtolower(trim((string) $value));

        return $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    public static function phone(?string $value, string $country = 'RO'): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $digits = (string) preg_replace('/\D/', '', $value);
        if (str_starts_with($value, '+')) {
            $e164 = $digits;
        } elseif (str_starts_with($digits, '00')) {
            $e164 = substr($digits, 2);
        } elseif (str_starts_with($digits, '0') && isset(self::COUNTRY_CODES[strtoupper($country)])) {
            $e164 = self::COUNTRY_CODES[strtoupper($country)].substr($digits, 1);
        } else {
            return null; // fără prefix de țară și fără 0 național: ambiguu, nu ghicim
        }
        $length = strlen($e164);

        return $length >= 8 && $length <= 15 && $e164[0] !== '0' ? '+'.$e164 : null;
    }

    public static function externalId(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' && mb_strlen($value) <= 190 ? $value : null;
    }
}
