<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Comenzi: creare, stări, plăți, stoc, istoric și emailuri către client și magazin.
 */
final class Orders
{
    /** Stările comenzii: [etichetă, culoare, text pentru client] */
    public const STATUSES = [
        'asteptare_plata' => ['Așteaptă plata', '#f59e0b', 'Comanda așteaptă confirmarea plății cu cardul.'],
        'noua' => ['Nouă', '#3b82f6', 'Am primit comanda ta și o pregătim.'],
        'procesare' => ['În pregătire', '#8b5cf6', 'Comanda ta este în curs de pregătire.'],
        'expediata' => ['Expediată', '#0ea5e9', 'Comanda ta a fost predată curierului.'],
        'livrata' => ['Livrată', '#10b981', 'Comanda ta a fost livrată. Poftă bună la cafea!'],
        'anulata' => ['Anulată', '#6b7280', 'Comanda a fost anulată.'],
        'returnata' => ['Returnată', '#ef4444', 'Comanda a fost returnată.'],
    ];

    public const PAYMENT_STATUSES = [
        'neplatita' => ['Neplătită', '#f59e0b'],
        'autorizata' => ['Autorizată (de încasat)', '#8b5cf6'],
        'platita' => ['Plătită', '#10b981'],
        'esuata' => ['Plată eșuată', '#ef4444'],
        'anulata' => ['Autorizare anulată', '#6b7280'],
        'rambursata' => ['Rambursată', '#6b7280'],
        'rambursata_partial' => ['Rambursată parțial', '#f97316'],
    ];

    public const PAYMENT_METHODS = ['card' => 'Card online (BT iPay)', 'ramburs' => 'Ramburs', 'transfer' => 'Transfer bancar'];

    public static function statusLabel(string $s): string
    {
        return self::STATUSES[$s][0] ?? $s;
    }

    public static function paymentLabel(string $s): string
    {
        return self::PAYMENT_STATUSES[$s][0] ?? $s;
    }

    public static function find(int $id): ?array
    {
        return DB::row('SELECT * FROM orders WHERE id = ?', [$id]);
    }

    public static function items(int $orderId): array
    {
        return DB::all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
    }

    public static function events(int $orderId): array
    {
        return DB::all('SELECT e.*, u.name AS user_name FROM order_events e LEFT JOIN users u ON u.id = e.user_id WHERE e.order_id = ? ORDER BY e.id DESC', [$orderId]);
    }

    public static function event(int $orderId, string $type, string $message, array $data = [], bool $notify = false): void
    {
        DB::insert('order_events', [
            'order_id' => $orderId,
            'type' => $type,
            'message' => $message,
            'data' => $data ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'user_id' => (int)(Auth::user()['id'] ?? 0) ?: null,
            'notify' => $notify ? 1 : 0,
            'created_at' => DB::now(),
        ]);
    }

    public static function customerName(array $o): string
    {
        return trim($o['first_name'] . ' ' . $o['last_name']);
    }

    public static function publicUrl(array $o): string
    {
        return '/comanda/' . $o['token'];
    }

    /**
     * Creează comanda din coș.
     * @param array $c datele clientului (validate)
     * @param array $t totalurile (Shop::totals)
     */
    public static function create(array $c, array $t, string $payment): array
    {
        return DB::transaction(function () use ($c, $t, $payment) {
            $now = DB::now();
            $utm = $_COOKIE['bdm_utm'] ?? '';
            $id = DB::insert('orders', [
                'number' => 'tmp-' . random_token(6),
                'token' => random_token(18),
                'status' => $payment === 'card' ? 'asteptare_plata' : 'noua',
                'payment_method' => $payment,
                'payment_status' => 'neplatita',
                'customer_type' => $c['customer_type'],
                'first_name' => $c['first_name'],
                'last_name' => $c['last_name'],
                'email' => $c['email'],
                'phone' => $c['phone'],
                'company' => $c['company'] ?: null,
                'cui' => $c['cui'] ?: null,
                'reg_com' => $c['reg_com'] ?: null,
                'billing_address' => $c['billing_address'],
                'billing_city' => $c['billing_city'],
                'billing_county' => $c['billing_county'],
                'billing_postcode' => $c['billing_postcode'] ?: null,
                'ship_same' => $c['ship_same'] ? 1 : 0,
                'shipping_name' => $c['ship_same'] ? null : $c['shipping_name'],
                'shipping_phone' => $c['ship_same'] ? null : ($c['shipping_phone'] ?: null),
                'shipping_address' => $c['ship_same'] ? null : $c['shipping_address'],
                'shipping_city' => $c['ship_same'] ? null : $c['shipping_city'],
                'shipping_county' => $c['ship_same'] ? null : $c['shipping_county'],
                'shipping_postcode' => $c['ship_same'] ? null : ($c['shipping_postcode'] ?: null),
                'customer_note' => $c['note'],
                'shipping_method' => $t['shipping_method'],
                'subtotal' => $t['subtotal'],
                'discount' => $t['discount'],
                'shipping_cost' => $t['shipping'],
                'payment_fee' => $t['payment_fee'],
                'total' => $t['total'],
                'coupon_code' => $t['coupon']['code'] ?? null,
                'terms_accepted_at' => $now,
                'ip' => client_ip(),
                'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
                'utm' => is_string($utm) && strlen($utm) < 600 ? $utm : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $number = Settings::get('order_prefix', 'BDM') . ((int)Settings::get('order_start', '1000') + $id);
            DB::update('orders', ['number' => $number], 'id = :id', ['id' => $id]);
            foreach ($t['lines'] as $l) {
                if (!$l['available']) {
                    continue;
                }
                DB::insert('order_items', [
                    'order_id' => $id,
                    'product_id' => $l['id'],
                    'name' => $l['product']['name'],
                    'sku' => $l['product']['sku'] ?: null,
                    'unit' => $l['product']['unit'] ?: 'buc',
                    'price' => $l['price'],
                    'qty' => $l['qty'],
                    'total' => $l['total'],
                    'image' => Shop::image($l['product']) ?: null,
                ]);
            }
            if (!empty($t['coupon'])) {
                DB::q('UPDATE coupons SET used = used + 1 WHERE id = ?', [$t['coupon']['id']]);
            }
            $order = self::find($id);
            self::reduceStock($order);
            self::event($id, 'creata', 'Comandă plasată pe site (' . (self::PAYMENT_METHODS[$payment] ?? $payment) . ').');
            return self::find($id);
        });
    }

    // ---------------------------------------------------------------- stoc

    public static function reduceStock(array $o): void
    {
        if ((int)$o['stock_reduced']) {
            return;
        }
        foreach (self::items((int)$o['id']) as $it) {
            if ($it['product_id']) {
                DB::q('UPDATE products SET stock = CASE WHEN manage_stock = 1 THEN stock - ? ELSE stock END, sales_count = sales_count + ? WHERE id = ?', [(int)$it['qty'], (int)$it['qty'], (int)$it['product_id']]);
            }
        }
        DB::update('orders', ['stock_reduced' => 1], 'id = :id', ['id' => $o['id']]);
    }

    public static function restoreStock(array $o): void
    {
        if (!(int)$o['stock_reduced']) {
            return;
        }
        foreach (self::items((int)$o['id']) as $it) {
            if ($it['product_id']) {
                DB::q('UPDATE products SET stock = CASE WHEN manage_stock = 1 THEN stock + ? ELSE stock END, sales_count = CASE WHEN sales_count >= ? THEN sales_count - ? ELSE 0 END WHERE id = ?', [(int)$it['qty'], (int)$it['qty'], (int)$it['qty'], (int)$it['product_id']]);
            }
        }
        DB::update('orders', ['stock_reduced' => 0], 'id = :id', ['id' => $o['id']]);
        self::event((int)$o['id'], 'stoc', 'Stocul produselor a fost refăcut.');
    }

    // ---------------------------------------------------------------- stări

    /** Plata cu cardul a fost confirmată de bancă (o singură dată: trimite emailurile și trece comanda la „Nouă”). */
    public static function paymentReceived(array $o, string $paymentStatus): void
    {
        $fresh = self::find((int)$o['id']);
        if (!$fresh || $fresh['payment_status'] === $paymentStatus) {
            return;
        }
        $wasPaid = in_array($fresh['payment_status'], ['platita', 'autorizata'], true);
        $upd = ['payment_status' => $paymentStatus, 'updated_at' => DB::now()];
        if (!$fresh['paid_at']) {
            $upd['paid_at'] = DB::now();
        }
        if ($fresh['status'] === 'asteptare_plata') {
            $upd['status'] = 'noua';
        }
        if ($fresh['status'] === 'anulata') {
            // plată întârziată pentru o comandă anulată automat: o reactivăm
            $upd['status'] = 'noua';
            self::reduceStock($fresh);
        }
        DB::update('orders', $upd, 'id = :id', ['id' => $fresh['id']]);
        if (!$wasPaid) {
            $order = self::find((int)$fresh['id']);
            self::sendConfirmation($order);
            self::notifyShop($order, $paymentStatus === 'autorizata' ? 'Plată autorizată – de încasat din panou' : 'Plată cu cardul confirmată');
        }
    }

    public static function setPaymentStatus(array $o, string $status): void
    {
        if ($o['payment_status'] === $status) {
            return;
        }
        DB::update('orders', ['payment_status' => $status, 'updated_at' => DB::now()], 'id = :id', ['id' => $o['id']]);
    }

    /** Schimbă starea comenzii (din panou sau automat). */
    public static function setStatus(array $o, string $status, string $note = '', bool $notify = false): void
    {
        if (!isset(self::STATUSES[$status]) || $o['status'] === $status) {
            if ($note !== '') {
                self::event((int)$o['id'], 'nota', $note);
            }
            return;
        }
        DB::update('orders', ['status' => $status, 'updated_at' => DB::now()], 'id = :id', ['id' => $o['id']]);
        if (in_array($status, ['anulata', 'returnata'], true)) {
            self::restoreStock($o);
        } elseif (in_array($o['status'], ['anulata', 'returnata'], true)) {
            self::reduceStock(self::find((int)$o['id']));
        }
        if ($status === 'livrata' && $o['payment_method'] === 'ramburs' && $o['payment_status'] === 'neplatita') {
            DB::update('orders', ['payment_status' => 'platita', 'paid_at' => DB::now()], 'id = :id', ['id' => $o['id']]);
        }
        self::event((int)$o['id'], 'stare', 'Stare: ' . self::statusLabel($o['status']) . ' → ' . self::statusLabel($status) . ($note !== '' ? ". $note" : ''), [], $notify);
        if ($notify) {
            self::sendStatus(self::find((int)$o['id']), $note);
        }
    }

    /** Comenzile cu card neplătite, după termenul de așteptare: verificăm la BT, apoi le anulăm (stocul revine). */
    public static function cancelStale(): int
    {
        $mins = max(15, (int)Settings::get('hold_unpaid_minutes', '60'));
        $limit = gmdate('Y-m-d H:i:s', time() - $mins * 60);
        $n = 0;
        foreach (DB::all("SELECT * FROM orders WHERE status = 'asteptare_plata' AND payment_status IN ('neplatita','esuata') AND created_at < ? AND updated_at < ? LIMIT 50", [$limit, $limit]) as $o) {
            if ($o['bt_order_id'] && BtIpay::configured()) {
                BtIpay::sync($o, 'cron');
                $o = self::find((int)$o['id']);
                if ($o['status'] !== 'asteptare_plata') {
                    continue;
                }
            }
            self::setStatus($o, 'anulata', 'Anulată automat: plata cu cardul nu a fost finalizată în ' . $mins . ' de minute.');
            $n++;
        }
        return $n;
    }

    // ---------------------------------------------------------------- emailuri

    private static function itemsTable(array $o): string
    {
        $rows = '';
        foreach (self::items((int)$o['id']) as $it) {
            $rows .= '<tr><td style="padding:10px 0;border-bottom:1px solid #eee4d8">' . e($it['name']) . '<br><span style="color:#8a7663;font-size:13px">' . (int)$it['qty'] . ' × ' . e(Shop::money($it['price'])) . '</span></td><td style="padding:10px 0;border-bottom:1px solid #eee4d8;text-align:right;white-space:nowrap">' . e(Shop::money($it['total'])) . '</td></tr>';
        }
        $sum = function (string $label, float $v, bool $bold = false) {
            return '<tr><td style="padding:6px 0;' . ($bold ? 'font-weight:700;font-size:17px' : 'color:#6b5a4a') . '">' . e($label) . '</td><td style="padding:6px 0;text-align:right;' . ($bold ? 'font-weight:700;font-size:17px' : '') . '">' . e(Shop::money($v)) . '</td></tr>';
        };
        $rows .= $sum('Subtotal', (float)$o['subtotal']);
        if ((float)$o['discount'] > 0) {
            $rows .= $sum('Reducere' . ($o['coupon_code'] ? ' (' . $o['coupon_code'] . ')' : ''), -(float)$o['discount']);
        }
        $rows .= $sum((string)($o['shipping_method'] === 'ridicare' ? 'Ridicare personală' : 'Livrare'), (float)$o['shipping_cost']);
        if ((float)$o['payment_fee'] > 0) {
            $rows .= $sum('Taxă ramburs', (float)$o['payment_fee']);
        }
        $rows .= $sum('Total', (float)$o['total'], true);
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0;font-size:15px">' . $rows . '</table>';
    }

    public static function addressHtml(array $o, bool $shipping = true): string
    {
        if ($shipping && !(int)$o['ship_same']) {
            $lines = [$o['shipping_name'], $o['shipping_address'], trim($o['shipping_postcode'] . ' ' . $o['shipping_city']) . ', jud. ' . $o['shipping_county'], $o['shipping_phone'] ?: $o['phone']];
        } else {
            $lines = [self::customerName($o), $o['company'] ? $o['company'] . ($o['cui'] ? ', CUI ' . $o['cui'] : '') : '', $o['billing_address'], trim($o['billing_postcode'] . ' ' . $o['billing_city']) . ', jud. ' . $o['billing_county'], $o['phone']];
        }
        return implode('<br>', array_map('e', array_filter($lines)));
    }

    private static function paymentInstructions(array $o): string
    {
        if ($o['payment_method'] === 'transfer' && $o['payment_status'] !== 'platita') {
            return '<div style="background:#faf5ee;border-radius:12px;padding:14px 16px;margin:16px 0"><strong>Date pentru plata prin transfer bancar</strong><br>Beneficiar: ' . e(Settings::get('company_name')) . '<br>CUI: ' . e(Settings::get('company_cui')) . '<br>IBAN: ' . e(Settings::get('company_iban')) . '<br>Banca: ' . e(Settings::get('company_bank')) . '<br>Suma: <strong>' . e(Shop::money($o['total'])) . '</strong><br>Detalii plată: <strong>Comanda ' . e($o['number']) . '</strong></div>';
        }
        if ($o['payment_method'] === 'ramburs') {
            return '<p>Plata se face <strong>ramburs</strong>, la livrare: ' . e(Shop::money($o['total'])) . '.</p>';
        }
        if ($o['payment_method'] === 'card' && in_array($o['payment_status'], ['platita', 'autorizata'], true)) {
            return '<p>✅ Plata cu cardul a fost confirmată de Banca Transilvania' . ($o['bt_card'] ? ' (card ' . e($o['bt_card']) . ')' : '') . '.</p>';
        }
        return '';
    }

    public static function sendConfirmation(array $o): void
    {
        $url = abs_url(self::publicUrl($o));
        $body = '<h2>Mulțumim pentru comandă, ' . e($o['first_name']) . '!</h2>'
            . '<p>Am primit comanda <strong>' . e($o['number']) . '</strong> din ' . e(ro_date($o['created_at'], true)) . '. Îți scriem din nou când o predăm curierului.</p>'
            . self::paymentInstructions($o)
            . self::itemsTable($o)
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.6"><tr><td valign="top" style="padding-right:10px"><strong>Livrare</strong><br>' . e($o['shipping_method'] === 'ridicare' ? (string)Settings::get('pickup_label') : (string)Settings::get('shipping_label')) . '<br>' . self::addressHtml($o) . '</td><td valign="top"><strong>Facturare</strong><br>' . self::addressHtml($o, false) . '</td></tr></table>'
            . '<p style="margin:26px 0"><a href="' . e($url) . '" style="background:#15100c;color:#e3c98d;padding:12px 22px;border-radius:999px;text-decoration:none;font-weight:600">Vezi comanda</a></p>'
            . '<p style="color:#8a7663;font-size:14px">' . e((string)Settings::get('email_order_footer')) . '</p>';
        [$ok] = Mailer::send($o['email'], 'Comanda ' . $o['number'] . ' a fost înregistrată – ' . Settings::get('brand_name'), Mailer::layout($body, ['preheader' => 'Comanda ' . $o['number'] . ': ' . Shop::money($o['total'])]), ['kind' => 'comanda', 'to_name' => self::customerName($o)]);
        self::event((int)$o['id'], 'email', ($ok ? 'Email de confirmare trimis clientului.' : 'Emailul de confirmare NU a putut fi trimis – verifică Setări → Email.'));
    }

    public static function notifyShop(array $o, string $title = 'Comandă nouă'): void
    {
        $to = array_filter(array_map('trim', explode(',', (string)Settings::get('notify_email'))));
        if (!$to) {
            return;
        }
        $link = abs_url('/admin/comenzi/' . $o['id']);
        $body = '<h2>' . e($title) . ': ' . e($o['number']) . '</h2>'
            . '<p><strong>' . e(self::customerName($o)) . '</strong> · ' . e($o['phone']) . ' · ' . e($o['email']) . '<br>Plată: ' . e(self::PAYMENT_METHODS[$o['payment_method']] ?? $o['payment_method']) . ' – ' . e(self::paymentLabel($o['payment_status'])) . '</p>'
            . ($o['customer_note'] ? '<p style="background:#faf5ee;padding:12px;border-radius:10px"><strong>Mențiuni client:</strong> ' . nl2br(e($o['customer_note'])) . '</p>' : '')
            . self::itemsTable($o)
            . '<p><strong>Livrare:</strong><br>' . self::addressHtml($o) . '</p>'
            . '<p style="margin:24px 0"><a href="' . e($link) . '" style="background:#2b1a12;color:#fff;padding:12px 22px;border-radius:999px;text-decoration:none;font-weight:600">Deschide în panou</a></p>';
        foreach ($to as $addr) {
            Mailer::send($addr, '🛒 ' . $title . ' ' . $o['number'] . ' – ' . Shop::money($o['total']), Mailer::layout($body), ['kind' => 'notificare', 'reply_to' => $o['email'], 'reply_name' => self::customerName($o)]);
        }
    }

    public static function sendStatus(array $o, string $note = ''): void
    {
        $st = self::STATUSES[$o['status']] ?? null;
        if (!$st) {
            return;
        }
        $url = abs_url(self::publicUrl($o));
        $body = '<h2>Comanda ' . e($o['number']) . ': ' . e(mb_strtolower($st[0])) . '</h2><p>Salut ' . e($o['first_name']) . ',</p><p>' . e($st[2]) . '</p>';
        if ($o['status'] === 'expediata' && $o['awb']) {
            $body .= '<div style="background:#faf5ee;border-radius:12px;padding:14px 16px;margin:16px 0">Curier: <strong>' . e($o['courier'] ?: '—') . '</strong><br>Număr de urmărire (AWB): <strong>' . e($o['awb']) . '</strong>'
                . ($o['tracking_url'] ? '<br><a href="' . e($o['tracking_url']) . '">Urmărește coletul</a>' : '') . '</div>';
        }
        if ($note !== '') {
            $body .= '<p>' . nl2br(e($note)) . '</p>';
        }
        $body .= '<p style="margin:24px 0"><a href="' . e($url) . '" style="background:#15100c;color:#e3c98d;padding:12px 22px;border-radius:999px;text-decoration:none;font-weight:600">Vezi comanda</a></p>';
        [$ok] = Mailer::send($o['email'], 'Comanda ' . $o['number'] . ' – ' . $st[0], Mailer::layout($body), ['kind' => 'comanda', 'to_name' => self::customerName($o)]);
        self::event((int)$o['id'], 'email', $ok ? 'Clientul a fost anunțat pe email (' . $st[0] . ').' : 'Emailul către client nu a putut fi trimis.');
    }
}
