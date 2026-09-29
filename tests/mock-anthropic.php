<?php
/**
 * Server local care imită POST /v1/messages, pentru testarea asistentului fără cheie reală.
 *   php -S 127.0.0.1:8090 tests/mock-anthropic.php
 * Cererile primite se salvează în tests/tmp/requests.jsonl pentru verificări.
 */
$raw = (string)file_get_contents('php://input');
@mkdir(__DIR__ . '/tmp', 0755, true);
file_put_contents(__DIR__ . '/tmp/requests.jsonl', json_encode([
    'path' => $_SERVER['REQUEST_URI'],
    'headers' => ['anthropic-beta' => $_SERVER['HTTP_ANTHROPIC_BETA'] ?? '', 'x-api-key' => $_SERVER['HTTP_X_API_KEY'] ?? ''],
    'body' => json_decode($raw, true),
], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

header('Content-Type: application/json');
$req = json_decode($raw, true) ?: [];
if (($_SERVER['HTTP_X_API_KEY'] ?? '') === 'bad-key') {
    http_response_code(401);
    echo json_encode(['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']]);
    exit;
}
$msgs = $req['messages'] ?? [];
$last = end($msgs);
$lastText = is_string($last['content'] ?? null) ? $last['content'] : '';
$all = json_encode($msgs, JSON_UNESCAPED_UNICODE);

$thinking = ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig-' . count($msgs)];
$content = [];
$stop = 'end_turn';
if (is_array($last['content'] ?? null) && ($last['content'][0]['type'] ?? '') === 'tool_result') {
    $content = [$thinking, ['type' => 'text', 'text' => 'Mulțumesc, **Ion**! Am transmis cererea colegilor, care te sună azi. Între timp poți citi [ghidul nostru](/blog/ce-faci-cand-hard-diskul-a-cedat).']];
} elseif (preg_match('/\bda\b/iu', $lastText) && str_contains($all, '0722')) {
    $content = [$thinking, ['type' => 'text', 'text' => 'Perfect, salvez cererea.'], ['type' => 'tool_use', 'id' => 'toolu_test1', 'name' => 'save_lead', 'input' => [
        'name' => 'Ion Chat', 'phone' => '0722 999 888', 'email' => '', 'company' => 'Chat SRL', 'county' => 'Mureș',
        'service' => 'Recuperare date', 'summary' => 'Hard disk extern nerecunoscut, facturi pe 3 ani. Vrea evaluare.', 'consent' => true,
    ]]];
    $stop = 'tool_use';
} elseif (str_contains($lastText, 'refuz')) {
    $content = [];
    $stop = 'refusal';
} else {
    $content = [$thinking, ['type' => 'text', 'text' => "Sigur! Pentru recuperare de date:\n- **nu mai porni** discul\n- sună-ne la 0744 599 333\n\nDetalii: [Recuperare date](/servicii/recuperare-date) și [link extern](https://evil.example)."]];
}
echo json_encode([
    'id' => 'msg_' . bin2hex(random_bytes(6)),
    'type' => 'message',
    'role' => 'assistant',
    'model' => $req['model'] ?? 'claude-opus-5-5',
    'content' => $content,
    'stop_reason' => $stop,
    'stop_sequence' => null,
    'stop_details' => $stop === 'refusal' ? ['type' => 'refusal', 'category' => null, 'explanation' => null] : null,
    'usage' => ['input_tokens' => 120, 'output_tokens' => 45, 'cache_read_input_tokens' => 3000, 'cache_creation_input_tokens' => 0],
], JSON_UNESCAPED_UNICODE);
