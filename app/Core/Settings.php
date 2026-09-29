<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Setări cheie/valoare salvate în baza de date, cu valori implicite.
 */
final class Settings
{
    private static ?array $cache = null;

    /** Setări sensibile, criptate în baza de date cu cheia aplicației. */
    private const SECRETS = ['smtp_pass', 'turnstile_secret'];

    public static function defaults(): array
    {
        return require APP_PATH . '/Data/settings_defaults.php';
    }

    public static function load(): void
    {
        if (self::$cache !== null) {
            return;
        }
        self::$cache = self::defaults();
        if (DB::connected() && DB::tableExists('settings')) {
            foreach (DB::all('SELECT skey, svalue FROM settings') as $r) {
                self::$cache[$r['skey']] = $r['svalue'];
            }
        }
    }

    public static function get(string $key, mixed $default = ''): mixed
    {
        self::load();
        $v = self::$cache[$key] ?? null;
        if (is_string($v) && str_starts_with($v, 'enc:')) {
            $v = self::decrypt(substr($v, 4));
        }
        return ($v === null || $v === '') ? $default : $v;
    }

    public static function json(string $key): array
    {
        $v = self::get($key, '[]');
        if (is_array($v)) {
            return $v;
        }
        $d = json_decode((string)$v, true);
        return is_array($d) ? $d : [];
    }

    public static function set(string $key, mixed $value): void
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $value = (string)$value;
        if (in_array($key, self::SECRETS, true) && $value !== '' && function_exists('sodium_crypto_secretbox')) {
            $value = 'enc:' . self::encrypt($value);
        }
        if (DB::val('SELECT COUNT(*) FROM settings WHERE skey = ?', [$key])) {
            DB::update('settings', ['svalue' => $value], 'skey = :k', ['k' => $key]);
        } else {
            DB::insert('settings', ['skey' => $key, 'svalue' => $value]);
        }
        self::load();
        self::$cache[$key] = $value;
    }

    private static function key(): string
    {
        return sodium_crypto_generichash(app_key(), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    private static function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    private static function decrypt(string $b64): string
    {
        $raw = base64_decode($b64, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES || !function_exists('sodium_crypto_secretbox_open')) {
            return '';
        }
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), self::key());
        return $plain === false ? '' : $plain;
    }

    public static function setMany(array $values): void
    {
        foreach ($values as $k => $v) {
            self::set($k, $v);
        }
    }

    public static function reset(): void
    {
        self::$cache = null;
    }
}
