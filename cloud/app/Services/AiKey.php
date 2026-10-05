<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Cheia AI a platformei. Se poate seta din panoul super admin (salvată criptat, niciodată afișată înapoi)
 * sau din .env (ANTHROPIC_API_KEY). Cheia din panou are prioritate.
 */
final class AiKey
{
    public const SETTING = 'ai_api_key';

    public static function current(): string
    {
        return self::fromPanel() ?? (string) config('vitim.ai.api_key');
    }

    /** 'panel' | 'env' | 'none' */
    public static function source(): string
    {
        return match (true) {
            self::fromPanel() !== null => 'panel',
            (string) config('vitim.ai.api_key') !== '' => 'env',
            default => 'none',
        };
    }

    /** Doar ultimele 4 caractere, pentru recunoaștere. */
    public static function masked(): string
    {
        $key = self::current();

        return $key === '' ? '' : '••••••••'.substr($key, -4);
    }

    public static function save(string $key): void
    {
        PlatformSetting::put(self::SETTING, Crypt::encryptString(trim($key)));
    }

    public static function clear(): void
    {
        PlatformSetting::query()->whereKey(self::SETTING)->delete();
    }

    private static function fromPanel(): ?string
    {
        $stored = PlatformSetting::get(self::SETTING);
        if (! is_string($stored) || $stored === '') {
            return null;
        }
        try {
            $key = Crypt::decryptString($stored);
        } catch (DecryptException) {
            return null; // APP_KEY schimbat: cheia trebuie salvată din nou
        }

        return $key !== '' ? $key : null;
    }
}
