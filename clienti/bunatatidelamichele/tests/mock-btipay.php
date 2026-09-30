<?php
declare(strict_types=1);

/**
 * Simulator BT iPay pentru teste locale (NU se urcă pe server).
 *   php -S 127.0.0.1:8091 tests/mock-btipay.php
 * În panou → Setări → Plăți: „Server API – TEST” = http://127.0.0.1:8091 (doar în mediul local;
 * panoul acceptă numai https, așa că testele e2e îl setează direct în baza de date).
 *
 * Implementează: register.do, registerPreAuth.do, getOrderStatusExtended.do, deposit.do, reverse.do, refund.do
 * și o pagină de plată cu butoanele „Plătește” / „Refuză”.
 */

$db = __DIR__ . '/tmp/btmock.json';
@mkdir(dirname($db), 0755, true);
$state = is_file($db) ? (json_decode((string)file_get_contents($db), true) ?: []) : [];
$save = function () use (&$state, $db) { file_put_contents($db, json_encode($state, JSON_PRETTY_PRINT)); };
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$in = $_POST + $_GET;
$json = function (array $d) { header('Content-Type: application/json'); echo json_encode($d); exit; };
$auth = fn() => ($in['userName'] ?? '') === 'test_api' && ($in['password'] ?? '') === 'test_pass';

if (str_starts_with($path, '/payment/rest/')) {
    if (!$auth()) {
        $json(['errorCode' => '5', 'errorMessage' => 'Access denied']);
    }
    $ep = basename($path);
    if ($ep === 'register.do' || $ep === 'registerPreAuth.do') {
        foreach (['orderNumber', 'amount', 'currency', 'returnUrl', 'orderBundle', 'jsonParams'] as $req) {
            if (empty($in[$req])) {
                $json(['errorCode' => '4', 'errorMessage' => "Missing $req"]);
            }
        }
        foreach ($state as $o) {
            if ($o['orderNumber'] === $in['orderNumber']) {
                $json(['errorCode' => '1', 'errorMessage' => 'Order with this number was already processed']);
            }
        }
        $bundle = json_decode($in['orderBundle'], true);
        if (empty($bundle['customerDetails']['email']) || empty($bundle['customerDetails']['billingInfo']['city'])) {
            $json(['errorCode' => '4', 'errorMessage' => 'orderBundle incomplete']);
        }
        $id = sprintf('%08x-%04x-%04x-%04x-%012x', random_int(0, 0xffffffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffffffffffff));
        $state[$id] = ['orderNumber' => $in['orderNumber'], 'amount' => (int)$in['amount'], 'returnUrl' => $in['returnUrl'], 'twoPhase' => $ep === 'registerPreAuth.do', 'status' => 0, 'action' => -100, 'deposited' => 0, 'approved' => 0, 'refunded' => 0, 'bundle' => $bundle, 'jsonParams' => $in['jsonParams']];
        $save();
        $host = 'http://' . $_SERVER['HTTP_HOST'];
        $json(['orderId' => $id, 'formUrl' => $host . '/payment/merchants/pay.html?mdOrder=' . $id]);
    }
    $id = $in['orderId'] ?? '';
    if ($ep === 'getOrderStatusExtended.do' && !isset($state[$id])) {
        $json(['errorCode' => '6', 'errorMessage' => 'Order not found', 'orderStatus' => null]);
    }
    if (!isset($state[$id])) {
        $json(['errorCode' => '6', 'errorMessage' => 'Order not found']);
    }
    $o = &$state[$id];
    switch ($ep) {
        case 'getOrderStatusExtended.do':
            $desc = [0 => 'Success', 915 => 'Fonduri insuficiente', -100 => 'Nu s-a efectuat plata'][$o['action']] ?? 'Declined';
            $json(['errorCode' => '0', 'errorMessage' => 'Success', 'orderNumber' => $o['orderNumber'], 'orderStatus' => $o['status'], 'actionCode' => $o['action'], 'actionCodeDescription' => $desc, 'amount' => $o['amount'], 'currency' => '946',
                'cardAuthInfo' => $o['status'] ? ['pan' => '411111**1111', 'cardholderName' => 'TEST'] : [], 'paymentAmountInfo' => ['approvedAmount' => $o['approved'], 'depositedAmount' => $o['deposited'], 'refundedAmount' => $o['refunded'], 'paymentState' => 'X']]);
        case 'deposit.do':
            if ($o['status'] !== 1) {
                $json(['errorCode' => '7', 'errorMessage' => 'Invalid order state']);
            }
            $o['status'] = 2;
            $o['deposited'] = min((int)$in['amount'] ?: $o['approved'], $o['approved']);
            $save();
            $json(['errorCode' => '0', 'errorMessage' => 'Success']);
        case 'reverse.do':
            if ($o['status'] !== 1) {
                $json(['errorCode' => '7', 'errorMessage' => 'Invalid order state']);
            }
            $o['status'] = 3;
            $save();
            $json(['errorCode' => '0', 'errorMessage' => 'Success']);
        case 'refund.do':
            if (!in_array($o['status'], [2, 7], true) || (int)$in['amount'] > $o['deposited'] - $o['refunded']) {
                $json(['errorCode' => '7', 'errorMessage' => 'Invalid amount or state']);
            }
            $o['refunded'] += (int)$in['amount'];
            $o['status'] = $o['refunded'] >= $o['deposited'] ? 4 : 7;
            $save();
            $json(['errorCode' => '0', 'errorMessage' => 'Success']);
    }
    $json(['errorCode' => '7', 'errorMessage' => 'Unknown endpoint']);
}

if ($path === '/payment/merchants/pay.html') {
    $id = $in['mdOrder'] ?? '';
    if (!isset($state[$id])) {
        exit('Comandă necunoscută');
    }
    $o = &$state[$id];
    if (isset($in['result'])) {
        if ($in['result'] === 'ok') {
            $o['status'] = $o['twoPhase'] ? 1 : 2;
            $o['action'] = 0;
            $o['approved'] = $o['amount'];
            $o['deposited'] = $o['twoPhase'] ? 0 : $o['amount'];
        } else {
            $o['status'] = 6;
            $o['action'] = 915;
        }
        $save();
        header('Location: ' . $o['returnUrl'] . (str_contains($o['returnUrl'], '?') ? '&' : '?') . 'orderId=' . $id . '&lang=ro');
        exit;
    }
    echo '<!doctype html><meta charset="utf-8"><title>BT iPay – simulator</title><body style="font-family:sans-serif;padding:40px"><h1>BT iPay (simulator)</h1><p>Comanda ' . htmlspecialchars($o['orderNumber']) . ' – ' . number_format($o['amount'] / 100, 2) . ' RON</p>'
        . '<a id="pay-ok" href="?mdOrder=' . $id . '&result=ok">Plătește</a> · <a id="pay-fail" href="?mdOrder=' . $id . '&result=fail">Refuză (fonduri insuficiente)</a></body>';
    exit;
}
http_response_code(404);
echo 'not found';
