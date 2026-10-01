<?php
/**
 * VITIM AI — verificare instalare (temporar). Se urcă în vitim-ai/public/, se deschide
 * https://ai.vitim.ro/verificare.php?token=SETUP_TOKEN și apoi SE ȘTERGE.
 * Nu afișează parole sau chei.
 */
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');
$root = dirname(__DIR__);
$env = [];
if (is_file($root.'/.env')) {
    foreach (file($root.'/.env', FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)$/', $line, $m)) {
            $env[$m[1]] = trim($m[2], " \t\"'");
        }
    }
}
$token = $env['SETUP_TOKEN'] ?? '';
if (strlen($token) < 16 || ! hash_equals($token, (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    $here = fn ($f) => is_file($root.'/'.$f) ? 'DA' : 'NU';
    exit("Deschide pagina cu ?token=VALOAREA_SETUP_TOKEN din .env (minimum 16 caractere).\n"
        ."Caut .env în folderul: $root\n"
        .".env găsit: ".$here('.env')."\n"
        ."artisan (aplicația) în același folder: ".$here('artisan')."\n"
        .".env.cpanel.example în același folder: ".$here('.env.cpanel.example')."\n"
        .".env greșit pus în public/: ".(is_file(__DIR__.'/.env') ? 'DA (mută-l un nivel mai sus!)' : 'NU')."\n");
}
$ok = fn ($c) => $c ? 'OK ' : 'NU ';
$hide = fn ($s) => preg_replace(["/(password|pass)[^,;)]*/i", "/'[^']*'@/"], ['$1 ***', "'***'@"], (string) $s);

echo "VITIM AI — verificare\n\n";
echo $ok(PHP_VERSION_ID >= 80300).'PHP '.PHP_VERSION."\n";
foreach (['pdo_mysql', 'mbstring', 'openssl', 'sodium', 'zip', 'fileinfo', 'tokenizer', 'ctype', 'xml', 'intl'] as $ext) {
    echo $ok(extension_loaded($ext))."extensia $ext\n";
}
echo $ok(is_file($root.'/vendor/autoload.php'))."vendor/ (fișierele aplicației)\n";
echo $ok(is_file($root.'/.env'))."fișierul .env\n";
echo $ok(str_starts_with($env['APP_KEY'] ?? '', 'base64:'))."APP_KEY generat (de cron)\n";
echo $ok(($env['APP_DEBUG'] ?? '') === 'false')."APP_DEBUG=false\n";
foreach (['storage', 'storage/framework/sessions', 'storage/framework/views', 'storage/framework/cache', 'storage/logs', 'bootstrap/cache'] as $d) {
    echo $ok(is_dir($root.'/'.$d) && is_writable($root.'/'.$d))."scriere în $d\n";
}
$deployed = @file_get_contents($root.'/storage/app/deployed_version');
echo $ok($deployed !== false).'cron a rulat vitim:deploy'.($deployed ? " (versiunea $deployed)" : '')."\n";

echo "\nBaza de date (".($env['DB_CONNECTION'] ?? '?')." / ".($env['DB_DATABASE'] ?? '?')."):\n";
try {
    $pdo = new PDO('mysql:host='.($env['DB_HOST'] ?? 'localhost').';port='.($env['DB_PORT'] ?? 3306).';dbname='.($env['DB_DATABASE'] ?? ''), $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "OK conexiune\n";
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach (['migrations', 'sessions', 'cache', 'users', 'organizations', 'contacts'] as $t) {
        echo $ok(in_array($t, $tables, true))."tabela $t\n";
    }
} catch (Throwable $e) {
    echo 'NU conexiune: '.$hide($e->getMessage())."\n";
}

echo "\nUltimele erori din storage/logs:\n";
$logs = glob($root.'/storage/logs/*.log') ?: [];
rsort($logs);
if (! $logs) {
    echo "(niciun fișier de log)\n";
}
foreach (array_slice($logs, 0, 1) as $log) {
    $lines = preg_grep('/\.(ERROR|CRITICAL|ALERT|EMERGENCY):/', file($log, FILE_IGNORE_NEW_LINES) ?: []);
    foreach (array_slice($lines, -5) as $l) {
        echo '- '.mb_substr($hide(preg_replace('/\{"exception".*$/', '', $l)), 0, 300)."\n";
    }
}
echo "\nDupă ce ai copiat rezultatul, ȘTERGE fișierul verificare.php.\n";
