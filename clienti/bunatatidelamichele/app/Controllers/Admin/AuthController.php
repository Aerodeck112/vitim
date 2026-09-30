<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\Mailer;
use App\Core\RateLimit;
use App\Core\Totp;
use App\Core\View;

final class AuthController
{
    private function page(string $view, array $data = []): string
    {
        return View::partial('admin/auth/' . $view, $data);
    }

    public function root(): never
    {
        redirect(Auth::check() ? '/admin/dashboard' : '/admin/login');
    }

    public function login(): string
    {
        if (Auth::check()) {
            redirect('/admin/dashboard');
        }
        $error = '';
        if (request_method() === 'POST') {
            if (!Csrf::verify()) {
                $error = 'Sesiunea a expirat. Reîncarcă pagina și încearcă din nou.';
            } else {
                [$ok, $msg] = Auth::attempt(str_input('email'), (string)($_POST['password'] ?? ''));
                if ($ok && $msg === '2fa') {
                    redirect('/admin/2fa');
                }
                if ($ok) {
                    $to = $_SESSION['after_login'] ?? '/admin/dashboard';
                    unset($_SESSION['after_login']);
                    redirect(is_string($to) && str_starts_with($to, base_path() . '/admin') ? preg_replace('#^' . preg_quote(base_path(), '#') . '#', '', $to) : '/admin/dashboard');
                }
                $error = $msg;
            }
        }
        return $this->page('login', ['error' => $error]);
    }

    public function twofa(): string
    {
        if (empty($_SESSION['uid']) || empty($_SESSION['2fa_pending'])) {
            redirect('/admin/login');
        }
        $error = '';
        if (request_method() === 'POST') {
            $u = DB::row('SELECT * FROM users WHERE id = ?', [$_SESSION['uid']]);
            if (!Csrf::verify()) {
                $error = 'Sesiunea a expirat.';
            } elseif (!RateLimit::hit('2fa:' . $_SESSION['uid'], 6, 900)) {
                Auth::logout();
                redirect('/admin/login');
            } elseif ($u && Totp::verify((string)$u['totp_secret'], str_input('code'))) {
                Auth::completeLogin((int)$u['id']);
                redirect('/admin/dashboard');
            } else {
                $error = 'Cod incorect. Verifică ora telefonului și încearcă din nou.';
            }
        }
        return $this->page('2fa', ['error' => $error]);
    }

    public function logout(): never
    {
        if (Csrf::verify()) {
            Auth::logout();
        }
        redirect('/admin/login');
    }

    public function forgot(): string
    {
        $sent = false;
        if (request_method() === 'POST' && Csrf::verify()) {
            $email = mb_strtolower(str_input('email'));
            if (RateLimit::hit('forgot:' . client_ip(), 5, 3600)) {
                $u = DB::row('SELECT * FROM users WHERE email = ? AND active = 1', [$email]);
                if ($u) {
                    $token = random_token(32);
                    DB::update('users', ['reset_token' => hash('sha256', $token), 'reset_expires' => gmdate('Y-m-d H:i:s', time() + 3600)], 'id = :id', ['id' => $u['id']]);
                    $link = abs_url('/admin/resetare/' . $token);
                    $body = '<p>Salut ' . e($u['name']) . ',</p><p>Cineva (sperăm că tu) a cerut resetarea parolei pentru panoul de control. Linkul este valabil o oră:</p><p style="margin:24px 0"><a href="' . e($link) . '" style="background:#2f6bff;color:#fff;padding:12px 22px;border-radius:999px;text-decoration:none;font-weight:600">Setează o parolă nouă</a></p><p style="color:#6b7489;font-size:14px">Dacă nu ai cerut tu resetarea, ignoră acest email. IP: ' . e(client_ip()) . '</p>';
                    Mailer::send($u['email'], 'Resetare parolă – panou de control', Mailer::layout($body), ['kind' => 'system']);
                }
            }
            $sent = true;
        }
        return $this->page('forgot', ['sent' => $sent]);
    }

    public function reset(string $token): string
    {
        $u = DB::row('SELECT * FROM users WHERE reset_token = ? AND reset_expires > ?', [hash('sha256', $token), DB::now()]);
        if (!$u) {
            return $this->page('forgot', ['sent' => false, 'error' => 'Linkul de resetare este invalid sau a expirat. Cere unul nou.']);
        }
        $error = '';
        if (request_method() === 'POST' && Csrf::verify()) {
            $p1 = (string)($_POST['password'] ?? '');
            if (strlen($p1) < 10) {
                $error = 'Parola trebuie să aibă minimum 10 caractere.';
            } elseif ($p1 !== ($_POST['password2'] ?? '')) {
                $error = 'Parolele nu coincid.';
            } else {
                DB::update('users', ['password_hash' => password_hash($p1, PASSWORD_DEFAULT), 'reset_token' => null, 'reset_expires' => null], 'id = :id', ['id' => $u['id']]);
                flash('ok', 'Parola a fost schimbată. Te poți autentifica.');
                redirect('/admin/login');
            }
        }
        return $this->page('reset', ['error' => $error]);
    }
}
