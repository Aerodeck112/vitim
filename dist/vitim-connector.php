<?php
/**
 * VITIM Connector pentru site-uri PHP (fără WordPress). Versiunea 1.0.0.
 *
 * Instalare:
 *  1. Completează cele două rânduri de mai jos (codul de conectare din panoul VITIM și adresa site-ului).
 *  2. Urcă fișierul în folderul principal al contului de hosting (ex. /home/cont/), NU în public_html.
 *  3. cPanel → Cron Jobs → Once Per Hour → comanda:
 *        php ~/vitim-connector.php
 *
 * Opțional, o lucrare manuală în jurnal (din Terminal sau dintr-un alt cron):
 *        php ~/vitim-connector.php lucrare updates "Actualizare aplicație la 1.4.0"
 * Tipuri: updates, backup, security, repair, content, seo, development, support, other.
 */

const VITIM_CONNECTION_CODE = ''; // COMPLETEAZĂ: VITIM1-...
const VITIM_SITE_URL = '';        // COMPLETEAZĂ: https://www.firma.ro
// Opțional: folderul site-ului, pentru versiunea aplicației (fișierul VERSION) și spațiul liber
const VITIM_SITE_DIR = '';        // ex. /home/cont/public_html

const VITIM_CONNECTOR_VERSION = '1.0.0';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Doar din cron / linia de comandă.\n");
}

function vitim_config(): array
{
    $code = trim(VITIM_CONNECTION_CODE);
    if (strpos($code, 'VITIM1-') !== 0 || VITIM_SITE_URL === '') {
        fwrite(STDERR, "Completează VITIM_CONNECTION_CODE și VITIM_SITE_URL în vitim-connector.php\n");
        exit(1);
    }
    $data = json_decode((string) base64_decode(strtr(substr($code, 7), '-_', '+/'), true), true);
    if (! is_array($data) || empty($data['u']) || empty($data['k']) || empty($data['s'])) {
        fwrite(STDERR, "Codul de conectare nu e valid.\n");
        exit(1);
    }

    return $data;
}

function vitim_post(array $config, string $endpoint, array $payload): array
{
    $body = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ts = (string) time();
    $nonce = bin2hex(random_bytes(16));
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Vitim-Key: '.$config['k'],
        'X-Vitim-Timestamp: '.$ts,
        'X-Vitim-Nonce: '.$nonce,
        'X-Vitim-Signature: '.hash_hmac('sha256', $ts.'.'.$nonce.'.'.$body, $config['s']),
    ];
    $url = rtrim($config['u'], '/').'/connector/v1/'.$endpoint;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
        $response = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body, 'timeout' => 20, 'ignore_errors' => true]]);
        $response = (string) @file_get_contents($url, false, $context);
        $status = isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m) ? (int) $m[1] : 0;
        $error = $status ? '' : 'conexiune eșuată';
    }

    return ['status' => $status, 'body' => json_decode($response, true), 'error' => $error];
}

function vitim_health(): array
{
    $dir = VITIM_SITE_DIR !== '' ? rtrim(VITIM_SITE_DIR, '/') : null;
    $version = $dir && is_file($dir.'/VERSION') ? trim((string) file_get_contents($dir.'/VERSION')) : null;
    $free = $dir ? @disk_free_space($dir) : false;

    return [
        'platform' => 'custom',
        'site_url' => VITIM_SITE_URL,
        'connector_version' => VITIM_CONNECTOR_VERSION,
        'php_version' => PHP_VERSION,
        'app_version' => $version ? substr($version, 0, 32) : null,
        'disk_free_mb' => $free !== false ? (int) ($free / 1048576) : null,
        'https' => strpos(VITIM_SITE_URL, 'https://') === 0,
    ];
}

$config = vitim_config();
$args = array_slice($argv, 1);

if (($args[0] ?? '') === 'lucrare') {
    $category = $args[1] ?? 'other';
    $title = trim($args[2] ?? '');
    if ($title === '') {
        fwrite(STDERR, "Folosire: php vitim-connector.php lucrare <tip> \"Ce s-a făcut\"\n");
        exit(1);
    }
    $result = vitim_post($config, 'worklog', ['entries' => [[
        'ref' => 'cli:'.substr(hash('sha256', $category.$title.date('Y-m-d H:i')), 0, 40),
        'category' => $category,
        'title' => mb_substr($title, 0, 190),
        'performed_at' => date('c'),
    ]]]);
} else {
    $result = vitim_post($config, 'heartbeat', vitim_health());
}

if ($result['status'] === 200) {
    echo "OK\n";
    exit(0);
}
fwrite(STDERR, 'Eroare: '.($result['body']['error']['message'] ?? $result['body']['message'] ?? $result['error'] ?: 'HTTP '.$result['status'])."\n");
exit(1);
