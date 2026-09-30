<?php
declare(strict_types=1);

/**
 * VITIM — asistent de instalare.
 * Se folosește o singură dată. După instalare, această pagină este blocată automat.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\DB;
use App\Core\Migrator;
use App\Core\Settings;

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$installed = is_installed();
$errors = [];
$done = false;

// Site instalat, dar fără niciun administrator activ (ex. baza de date a fost refăcută de la zero):
// se poate crea un cont nou, confirmând parola bazei de date din app/config.php (dovada că ai acces la server).
$needAdmin = false;
$adminCreated = false;
if ($installed && DB::connected()) {
    try {
        $needAdmin = DB::tableExists('users') && !DB::val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1");
    } catch (Throwable $e) {
        log_error($e);
    }
}
if ($needAdmin && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $name = trim((string)($_POST['admin_name'] ?? ''));
    $email = mb_strtolower(trim((string)($_POST['admin_email'] ?? '')));
    $pass = (string)($_POST['admin_pass'] ?? '');
    if (config('db.driver') === 'mysql' && !hash_equals((string)config('db.pass', ''), (string)($_POST['db_pass'] ?? ''))) {
        $errors[] = 'Parola bazei de date nu corespunde celei din app/config.php.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Emailul nu este valid.';
    }
    if (strlen($pass) < 10) {
        $errors[] = 'Parola trebuie să aibă minimum 10 caractere.';
    }
    if (!$errors) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        if ($id = DB::val('SELECT id FROM users WHERE email = ?', [$email])) {
            DB::update('users', ['password_hash' => $hash, 'role' => 'admin', 'active' => 1], 'id = :id', ['id' => $id]);
        } else {
            DB::insert('users', ['name' => $name ?: 'Administrator', 'email' => $email, 'password_hash' => $hash, 'role' => 'admin', 'active' => 1, 'created_at' => DB::now()]);
        }
        log_error('Instalator: cont de administrator creat pentru ' . $email);
        $adminCreated = true;
    }
}

// Cerințe
$checks = [
    ['PHP 8.1+', PHP_VERSION_ID >= 80100, 'Versiune curentă: ' . PHP_VERSION],
    ['Extensia PDO', extension_loaded('pdo'), ''],
    ['PDO MySQL sau SQLite', extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite'), 'mysql: ' . (extension_loaded('pdo_mysql') ? 'da' : 'nu') . ', sqlite: ' . (extension_loaded('pdo_sqlite') ? 'da' : 'nu')],
    ['mbstring', extension_loaded('mbstring'), ''],
    ['GD (imagini)', extension_loaded('gd'), function_exists('imagewebp') ? 'cu suport WebP' : 'fără WebP'],
    ['ZipArchive (actualizări)', class_exists('ZipArchive'), ''],
    ['OpenSSL', extension_loaded('openssl'), ''],
    ['DOM / XML', extension_loaded('dom'), ''],
    ['cURL', extension_loaded('curl'), 'opțional'],
    ['app/ inscriptibil', is_writable(APP_PATH), 'pentru config.php'],
    ['storage/ inscriptibil', is_writable(STORAGE_PATH), ''],
    ['uploads/ inscriptibil', is_writable(UPLOADS_PATH), ''],
];
$critical = array_filter($checks, fn($c) => !$c[1] && !in_array($c[0], ['cURL', 'GD (imagini)'], true));

if (!$installed && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !$critical) {
    $in = fn($k, $d = '') => trim((string)($_POST[$k] ?? $d));
    $driver = $in('db_driver', 'mysql') === 'sqlite' ? 'sqlite' : 'mysql';
    $siteUrl = rtrim($in('site_url'), '/');
    $adminName = $in('admin_name');
    $adminEmail = mb_strtolower($in('admin_email'));
    $adminPass = (string)($_POST['admin_pass'] ?? '');

    if (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
        $errors[] = 'Adresa site-ului nu este validă (ex: https://vitim.ro).';
    }
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Emailul administratorului nu este valid.';
    }
    if (strlen($adminPass) < 10) {
        $errors[] = 'Parola trebuie să aibă minimum 10 caractere.';
    }
    $db = ['driver' => $driver];
    if ($driver === 'mysql') {
        $db += ['host' => $in('db_host', 'localhost'), 'port' => (int)$in('db_port', '3306'), 'name' => $in('db_name'), 'user' => $in('db_user'), 'pass' => (string)($_POST['db_pass'] ?? '')];
        if ($db['name'] === '' || $db['user'] === '') {
            $errors[] = 'Completează numele bazei de date și utilizatorul (din cPanel → MySQL Databases).';
        }
    } else {
        $db['path'] = STORAGE_PATH . '/database.sqlite';
    }

    if (!$errors) {
        try {
            DB::connect($db);
            $GLOBALS['__config'] = [
                'db' => $db,
                'site_url' => $siteUrl,
                'app_key' => bin2hex(random_bytes(32)),
                'debug' => false,
            ];
            Migrator::run();
            $now = DB::now();
            if (!DB::val('SELECT id FROM users WHERE email = ?', [$adminEmail])) {
                $uid = DB::insert('users', [
                    'name' => $adminName ?: 'Administrator',
                    'email' => $adminEmail,
                    'password_hash' => password_hash($adminPass, PASSWORD_DEFAULT),
                    'role' => 'admin',
                    'active' => 1,
                    'created_at' => $now,
                ]);
                DB::q('UPDATE posts SET author_id = ? WHERE author_id IS NULL', [$uid]);
            }
            Settings::reset();
            foreach (['phone', 'email', 'company_name', 'company_city'] as $k) {
                if ($in($k) !== '') {
                    Settings::set($k, $in($k));
                }
            }
            if ($in('email') !== '') {
                Settings::set('mail_from', $in('email'));
                Settings::set('notify_email', $in('email'));
            }
            Settings::set('db_version', APP_VERSION);
            Settings::set('installed_at', $now);

            $cfg = "<?php\n// Generat de instalator la " . date('Y-m-d H:i') . ". Nu publica acest fișier.\nreturn " . var_export($GLOBALS['__config'], true) . ";\n";
            if (@file_put_contents(CONFIG_FILE, $cfg, LOCK_EX) === false) {
                throw new RuntimeException('Nu pot scrie app/config.php. Setează permisiunile directorului app/ la 755 și reîncearcă.');
            }
            @chmod(CONFIG_FILE, 0640);
            $done = true;
        } catch (Throwable $e) {
            log_error($e);
            $errors[] = 'Eroare: ' . $e->getMessage();
        }
    }
}

$guessUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_path();
$v = fn($k, $d = '') => e($_POST[$k] ?? $d);
?><!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Instalare VITIM</title>
<style>
:root{--b:#2f6bff}*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#05070d;color:#eef1f8;line-height:1.55}
.wrap{max-width:760px;margin:0 auto;padding:40px 18px 80px}h1{font-size:30px;margin:0 0 6px;letter-spacing:-.02em}h2{font-size:18px;margin:34px 0 12px}
.muted{color:#aeb6c8}.card{background:#0d1220;border:1px solid rgba(255,255,255,.1);border-radius:18px;padding:24px;margin-top:18px}
label{display:block;font-size:14px;color:#aeb6c8;margin:14px 0 6px}input,select{width:100%;padding:12px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.16);background:#05070d;color:#eef1f8;font-size:15px}
.row{display:grid;grid-template-columns:1fr 1fr;gap:14px}@media(max-width:600px){.row{grid-template-columns:1fr}}
button,.btn{display:inline-block;margin-top:24px;background:linear-gradient(120deg,#2f7bff,#6a5cff);color:#fff;border:0;border-radius:999px;padding:14px 26px;font-weight:600;font-size:16px;cursor:pointer;text-decoration:none}
table{width:100%;border-collapse:collapse;font-size:14px}td{padding:7px 4px;border-bottom:1px solid rgba(255,255,255,.07)}.ok{color:#34d399}.no{color:#f87171}
.err{background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.4);color:#fca5a5;padding:14px 16px;border-radius:12px;margin-top:18px}
.okbox{background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.4);padding:18px;border-radius:12px}
small{color:#7a8398}code{background:rgba(255,255,255,.08);padding:2px 6px;border-radius:6px}
</style>
</head>
<body>
<div class="wrap">
  <h1>Instalare VITIM</h1>
  <p class="muted">Site + panou de control + CRM + email marketing. Durează aproximativ un minut.</p>

<?php if ($adminCreated): ?>
  <div class="card okbox"><h2 style="margin-top:0">✅ Cont de administrator creat</h2><p>Te poți autentifica acum cu emailul și parola alese.</p><a class="btn" href="<?= e(base_path()) ?>/admin/login">Intră în panoul de control</a></div>
<?php elseif ($needAdmin): ?>
  <?php foreach ($errors as $err): ?><div class="err"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post" class="card" autocomplete="off">
    <h2 style="margin-top:0">Creează contul de administrator</h2>
    <p class="muted">Site-ul este instalat, dar baza de date nu are niciun administrator (de exemplu, a fost refăcută de la zero). Creează acum contul cu care intri în panou.</p>
    <?php if (config('db.driver') === 'mysql'): ?>
    <label>Parola bazei de date (cea din <code>app/config.php</code>, linia 'pass')</label>
    <input type="password" name="db_pass" required>
    <small>Confirmă că ai acces la server. Nu este parola contului de admin.</small>
    <?php endif; ?>
    <label>Numele tău</label>
    <input name="admin_name" value="<?= e($_POST['admin_name'] ?? '') ?>">
    <label>Email (cu el te autentifici)</label>
    <input type="email" name="admin_email" value="<?= e($_POST['admin_email'] ?? '') ?>" required>
    <label>Parolă nouă (minimum 10 caractere)</label>
    <input type="password" name="admin_pass" minlength="10" required>
    <button type="submit">Creează contul</button>
  </form>
<?php elseif ($installed && !$done): ?>
  <div class="card"><h2 style="margin-top:0">Site-ul este deja instalat</h2><p class="muted">Din motive de securitate, instalatorul este dezactivat. Poți șterge directorul <code>install/</code> de pe server.</p><a class="btn" href="<?= e(base_path()) ?>/admin">Mergi la panoul de control</a></div>
<?php elseif ($done): ?>
  <div class="card okbox">
    <h2 style="margin-top:0">✅ Instalare reușită!</h2>
    <p>Baza de date a fost creată și conținutul inițial (servicii, zone, pagini, articole, redirecționări) a fost încărcat.</p>
    <p><strong>Pașii următori:</strong></p>
    <ol>
      <li>Intră în panou și completează <strong>Setări → Firmă</strong> (CUI, Nr. Reg. Com., adresă).</li>
      <li>Configurează <strong>Setări → Email (SMTP)</strong> și trimite un email de test.</li>
      <li>Activează autentificarea în doi pași din <strong>Contul meu</strong>.</li>
      <li>Adaugă cron-ul din <strong>Sistem</strong> în cPanel → Cron Jobs.</li>
      <li>Șterge directorul <code>install/</code> de pe server (opțional, e oricum blocat).</li>
    </ol>
    <a class="btn" href="<?= e(base_path()) ?>/admin/login">Intră în panoul de control</a>
  </div>
<?php else: ?>
  <div class="card">
    <h2 style="margin-top:0">1. Verificarea serverului</h2>
    <table>
      <?php foreach ($checks as [$name, $ok, $info]): ?>
      <tr><td><?= e($name) ?></td><td class="<?= $ok ? 'ok' : 'no' ?>"><?= $ok ? '✓' : '✗' ?></td><td><small><?= e($info) ?></small></td></tr>
      <?php endforeach; ?>
    </table>
    <?php if ($critical): ?><div class="err">Rezolvă cerințele marcate cu ✗ (din cPanel → Select PHP Version / MultiPHP Manager și permisiuni 755 pe directoare), apoi reîncarcă pagina.</div><?php endif; ?>
  </div>

  <?php foreach ($errors as $err): ?><div class="err"><?= e($err) ?></div><?php endforeach; ?>

  <form method="post" class="card" autocomplete="off">
    <h2 style="margin-top:0">2. Site</h2>
    <label>Adresa site-ului</label>
    <input name="site_url" value="<?= $v('site_url', $guessUrl) ?>" required>
    <small>Fără slash la final. Ex: https://vitim.ro</small>

    <h2>3. Baza de date</h2>
    <label>Tip</label>
    <select name="db_driver" id="drv" onchange="document.getElementById('mysql').style.display=this.value==='mysql'?'block':'none'">
      <option value="mysql"<?= ($_POST['db_driver'] ?? 'mysql') === 'mysql' ? ' selected' : '' ?>>MySQL / MariaDB (recomandat pe cPanel)</option>
      <option value="sqlite"<?= ($_POST['db_driver'] ?? '') === 'sqlite' ? ' selected' : '' ?>>SQLite (fișier local, fără configurare)</option>
    </select>
    <div id="mysql" style="display:<?= ($_POST['db_driver'] ?? 'mysql') === 'mysql' ? 'block' : 'none' ?>">
      <small>Creează baza de date și utilizatorul din cPanel → <em>MySQL® Databases</em> (sau <em>MySQL Database Wizard</em>) și acordă-i „ALL PRIVILEGES”.</small>
      <div class="row">
        <div><label>Server</label><input name="db_host" value="<?= $v('db_host', 'localhost') ?>"></div>
        <div><label>Port</label><input name="db_port" value="<?= $v('db_port', '3306') ?>"></div>
      </div>
      <label>Numele bazei de date</label><input name="db_name" value="<?= $v('db_name') ?>" placeholder="cpaneluser_vitim">
      <div class="row">
        <div><label>Utilizator</label><input name="db_user" value="<?= $v('db_user') ?>" placeholder="cpaneluser_vitim"></div>
        <div><label>Parolă</label><input name="db_pass" type="password" value=""></div>
      </div>
    </div>

    <h2>4. Firma</h2>
    <div class="row">
      <div><label>Denumire firmă</label><input name="company_name" value="<?= $v('company_name', 'VITIM SRL') ?>"></div>
      <div><label>Oraș</label><input name="company_city" value="<?= $v('company_city', 'Târgu Mureș') ?>"></div>
    </div>
    <div class="row">
      <div><label>Telefon</label><input name="phone" value="<?= $v('phone', '0744 599 333') ?>"></div>
      <div><label>Email public</label><input name="email" type="email" value="<?= $v('email', 'office@vitim.ro') ?>"></div>
    </div>

    <h2>5. Contul de administrator</h2>
    <div class="row">
      <div><label>Nume</label><input name="admin_name" value="<?= $v('admin_name') ?>" required></div>
      <div><label>Email de autentificare</label><input name="admin_email" type="email" value="<?= $v('admin_email') ?>" required></div>
    </div>
    <label>Parolă (minimum 10 caractere)</label><input name="admin_pass" type="password" minlength="10" required autocomplete="new-password">

    <button type="submit"<?= $critical ? ' disabled' : '' ?>>Instalează</button>
  </form>
<?php endif; ?>
</div>
</body>
</html>
