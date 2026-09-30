<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\BtIpay;
use App\Core\DB;
use App\Core\Orders;
use App\Core\RateLimit;
use App\Core\Settings;

/**
 * Întoarcerea de la BT iPay și notificările (callback) trimise de bancă.
 * Starea plății se citește întotdeauna direct de la bancă (getOrderStatusExtended), niciodată din URL.
 */
final class PaymentController extends SiteController
{
    public function btReturn(): never
    {
        $this->personal();
        $btId = str_input('orderId');
        if (!preg_match('/^[A-Za-z0-9-]{8,64}$/', $btId) || !RateLimit::hit('btret:' . client_ip(), 30, 600)) {
            redirect('/');
        }
        $o = DB::row('SELECT * FROM orders WHERE bt_order_id = ?', [$btId]);
        if (!$o) {
            log_error('BT iPay retur: orderId necunoscut ' . $btId);
            redirect('/urmarire-comanda');
        }
        BtIpay::sync($o, 'retur client');
        $o = Orders::find((int)$o['id']);
        $ok = in_array($o['payment_status'], ['platita', 'autorizata'], true);
        redirect(Orders::publicUrl($o) . ($ok ? '?nou=1' : '?plata=respinsa'));
    }

    public function btCallback(): never
    {
        $this->personal();
        $params = array_map(fn($v) => is_string($v) ? $v : '', $_GET + $_POST);
        $token = (string)Settings::get('bt_callback_token');
        if ($token === '' || !hash_equals($token, (string)($params['t'] ?? ''))) {
            http_response_code(403);
            exit('forbidden');
        }
        if (!BtIpay::verifyCallback($params)) {
            log_error('BT iPay callback: checksum invalid ' . json_encode($params));
            http_response_code(400);
            exit('checksum');
        }
        $btId = (string)($params['mdOrder'] ?? $params['orderId'] ?? '');
        $o = $btId !== '' ? DB::row('SELECT * FROM orders WHERE bt_order_id = ?', [$btId]) : null;
        if (!$o && !empty($params['orderNumber'])) {
            // orderNumber poate avea sufixul reîncercării (BDM1001-2)
            $num = (string)preg_replace('/-\d+$/', '', (string)$params['orderNumber']);
            $o = DB::row('SELECT * FROM orders WHERE number = ?', [$num]);
            if ($o && $btId !== '' && $o['bt_order_id'] !== $btId) {
                DB::update('orders', ['bt_order_id' => $btId], 'id = :id', ['id' => $o['id']]);
                $o['bt_order_id'] = $btId;
            }
        }
        if ($o) {
            BtIpay::sync($o, 'notificare bancă – ' . mb_substr((string)($params['operation'] ?? ''), 0, 30));
        } else {
            log_error('BT iPay callback: comandă necunoscută ' . json_encode($params));
        }
        header('Content-Type: text/plain');
        echo 'OK';
        exit;
    }
}
