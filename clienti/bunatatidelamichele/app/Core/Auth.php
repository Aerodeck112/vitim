<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public const ROLES = ['admin' => 'Administrator', 'manager' => 'Comenzi și produse', 'editor' => 'Editor conținut'];

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        session_name('bdm_admin');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => base_path() . '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', '43200');
        $dir = STORAGE_PATH . '/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        if (is_writable($dir)) {
            session_save_path($dir);
        }
        session_start();
        if (isset($_SESSION['uid'], $_SESSION['last']) && time() - $_SESSION['last'] > 43200) {
            self::logout();
            session_start();
        }
        $_SESSION['last'] = time();
    }

    public static function user(): ?array
    {
        static $user = false;
        if ($user !== false) {
            return $user;
        }
        $uid = $_SESSION['uid'] ?? null;
        if (!$uid || !empty($_SESSION['2fa_pending'])) {
            return $user = null;
        }
        $user = DB::row('SELECT * FROM users WHERE id = ? AND active = 1', [$uid]);
        return $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function can(string $area): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        $role = $u['role'];
        if ($role === 'admin') {
            return true;
        }
        $map = [
            'manager' => ['dashboard', 'orders', 'products', 'content', 'media', 'messages'],
            'editor' => ['dashboard', 'products', 'content', 'media', 'seo'],
        ];
        return in_array($area, $map[$role] ?? [], true);
    }

    public static function attempt(string $email, string $password): array
    {
        $ip = client_ip();
        if (!RateLimit::hit('login:' . $ip, 8, 900)) {
            return [false, 'Prea multe încercări. Încearcă din nou peste 15 minute.'];
        }
        $u = DB::row('SELECT * FROM users WHERE email = ? AND active = 1', [mb_strtolower(trim($email))]);
        if (!$u || !password_verify($password, $u['password_hash'])) {
            usleep(random_int(200000, 500000));
            return [false, 'Email sau parolă greșită.'];
        }
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            DB::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $u['id']]);
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        if (!empty($u['totp_secret'])) {
            $_SESSION['2fa_pending'] = true;
            return [true, '2fa'];
        }
        self::completeLogin((int)$u['id']);
        return [true, 'ok'];
    }

    public static function completeLogin(int $uid): void
    {
        unset($_SESSION['2fa_pending']);
        RateLimit::clear('login:' . client_ip());
        DB::update('users', ['last_login_at' => DB::now(), 'last_login_ip' => client_ip()], 'id = :id', ['id' => $uid]);
        session_regenerate_id(true);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        @session_destroy();
    }
}
