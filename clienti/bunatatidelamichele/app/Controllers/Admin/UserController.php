<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Sanitizer;
use App\Core\Totp;

final class UserController extends AdminController
{
    protected string $area = 'settings';

    public function __construct()
    {
        // „Contul meu” și 2FA sunt disponibile oricărui utilizator autentificat
        if (str_starts_with(\App\Core\App::$path, '/admin/cont')) {
            $this->area = 'dashboard';
        }
        parent::__construct();
    }

    public function index(): string
    {
        return $this->render('users/index', ['rows' => DB::all('SELECT * FROM users ORDER BY name'), 'title' => 'Utilizatori']);
    }

    public function edit(?string $id = null): string
    {
        $u = $id ? DB::row('SELECT * FROM users WHERE id = ?', [(int)$id]) : null;
        $errors = [];
        if ($this->isPost()) {
            $data = [
                'name' => Sanitizer::text(str_input('name'), 120),
                'email' => mb_strtolower(Sanitizer::text(str_input('email'), 160)),
                'role' => array_key_exists(str_input('role'), Auth::ROLES) ? str_input('role') : 'manager',
                'active' => !empty($_POST['active']) ? 1 : 0,
            ];
            $pass = (string)($_POST['password'] ?? '');
            if ($data['name'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Completează numele și un email valid.';
            }
            if (DB::val('SELECT id FROM users WHERE email = ? AND id <> ?', [$data['email'], (int)($u['id'] ?? 0)])) {
                $errors[] = 'Există deja un utilizator cu acest email.';
            }
            if (!$u && strlen($pass) < 10) {
                $errors[] = 'Parola trebuie să aibă minimum 10 caractere.';
            }
            if ($u && (int)$u['id'] === (int)Auth::user()['id'] && ($data['role'] !== 'admin' || !$data['active'])) {
                $errors[] = 'Nu îți poți elimina propriile drepturi de administrator.';
            }
            if (!$errors) {
                if ($pass !== '') {
                    if (strlen($pass) < 10) {
                        $errors[] = 'Parola trebuie să aibă minimum 10 caractere.';
                    } else {
                        $data['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
                    }
                }
            }
            if (!$errors) {
                if ($u) {
                    DB::update('users', $data, 'id = :id', ['id' => $u['id']]);
                } else {
                    $data['created_at'] = DB::now();
                    DB::insert('users', $data);
                }
                flash('ok', 'Utilizator salvat.');
                redirect('/admin/utilizatori');
            }
            $u = array_merge($u ?? [], $_POST);
        }
        return $this->render('users/edit', ['u' => $u, 'errors' => $errors, 'title' => $u && !empty($u['id']) ? 'Editează utilizator' : 'Utilizator nou']);
    }

    public function delete(string $id): never
    {
        if ((int)$id === (int)Auth::user()['id']) {
            flash('err', 'Nu îți poți șterge propriul cont.');
        } elseif ((int)DB::val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id <> ?", [(int)$id]) === 0) {
            flash('err', 'Trebuie să rămână cel puțin un administrator.');
        } else {
            DB::delete('users', 'id = ?', [(int)$id]);
            flash('ok', 'Utilizator șters.');
        }
        redirect('/admin/utilizatori');
    }

    public function account(): string
    {
        $u = Auth::user();
        $errors = [];
        if ($this->isPost()) {
            $name = Sanitizer::text(str_input('name'), 120);
            $cur = (string)($_POST['current'] ?? '');
            $new = (string)($_POST['password'] ?? '');
            $upd = ['name' => $name ?: $u['name']];
            if ($new !== '') {
                if (!password_verify($cur, $u['password_hash'])) {
                    $errors[] = 'Parola curentă nu este corectă.';
                } elseif (strlen($new) < 10) {
                    $errors[] = 'Parola nouă trebuie să aibă minimum 10 caractere.';
                } elseif ($new !== ($_POST['password2'] ?? '')) {
                    $errors[] = 'Parolele noi nu coincid.';
                } else {
                    $upd['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
                }
            }
            if (!$errors) {
                DB::update('users', $upd, 'id = :id', ['id' => $u['id']]);
                flash('ok', 'Contul a fost actualizat.');
                redirect('/admin/cont');
            }
        }
        return $this->render('users/account', ['u' => $u, 'errors' => $errors, 'title' => 'Contul meu']);
    }

    public function twofa(): string
    {
        $u = Auth::user();
        $error = '';
        if ($this->isPost()) {
            if (str_input('action') === 'disable') {
                if (password_verify((string)($_POST['password'] ?? ''), $u['password_hash'])) {
                    DB::update('users', ['totp_secret' => null], 'id = :id', ['id' => $u['id']]);
                    flash('ok', 'Autentificarea în doi pași a fost dezactivată.');
                    redirect('/admin/cont/2fa');
                }
                $error = 'Parola nu este corectă.';
            } else {
                $secret = (string)($_SESSION['totp_setup'] ?? '');
                if ($secret !== '' && Totp::verify($secret, str_input('code'))) {
                    DB::update('users', ['totp_secret' => $secret], 'id = :id', ['id' => $u['id']]);
                    unset($_SESSION['totp_setup']);
                    flash('ok', 'Autentificarea în doi pași este activă. De acum vei introduce un cod la fiecare autentificare.');
                    redirect('/admin/cont/2fa');
                }
                $error = 'Codul nu este corect. Verifică ora telefonului și încearcă din nou.';
            }
        }
        if (empty($u['totp_secret']) && empty($_SESSION['totp_setup'])) {
            $_SESSION['totp_setup'] = Totp::secret();
        }
        $secret = (string)($_SESSION['totp_setup'] ?? '');
        return $this->render('users/twofa', [
            'u' => $u,
            'enabled' => !empty($u['totp_secret']),
            'secret' => $secret,
            'uri' => Totp::uri($secret, $u['email'], (string)setting('brand_name', 'Michele')),
            'error' => $error,
            'title' => 'Autentificare în doi pași',
        ]);
    }
}
