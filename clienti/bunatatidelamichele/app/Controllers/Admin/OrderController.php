<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\BtIpay;
use App\Core\DB;
use App\Core\Orders;
use App\Core\Sanitizer;
use App\Core\Shop;

final class OrderController extends AdminController
{
    protected string $area = 'orders';

    /** Curieri uzuali și adresa de urmărire ({awb} se înlocuiește cu numărul AWB). */
    public const COURIERS = [
        'Fan Courier' => 'https://www.fancourier.ro/awb-tracking/?tracking={awb}',
        'Sameday' => 'https://sameday.ro/#awb={awb}',
        'Cargus' => 'https://www.cargus.ro/personal/urmareste-coletul/?tracking_number={awb}',
        'DPD' => 'https://tracking.dpd.ro/?shipmentNumber={awb}&language=ro',
        'GLS' => 'https://gls-group.com/RO/ro/urmarire-colet?match={awb}',
        'Poșta Română' => 'https://www.posta-romana.ro/track-trace.html?id={awb}',
        'Altul' => '',
    ];

    private function order(string $id): array
    {
        $o = Orders::find((int)$id);
        if (!$o) {
            flash('err', 'Comanda nu există.');
            redirect('/admin/comenzi');
        }
        return $o;
    }

    private function filters(): array
    {
        $where = ['1=1'];
        $p = [];
        $st = str_input('stare');
        if ($st === 'de-procesat') {
            $where[] = "status IN ('noua','procesare')";
        } elseif (isset(Orders::STATUSES[$st])) {
            $where[] = 'status = :st';
            $p['st'] = $st;
        } elseif ($st === '') {
            $where[] = "status <> 'asteptare_plata' OR created_at >= :recent";
            $p['recent'] = gmdate('Y-m-d H:i:s', strtotime('-2 days'));
        }
        if (isset(Orders::PAYMENT_STATUSES[$ps = str_input('plata')])) {
            $where[] = 'payment_status = :ps';
            $p['ps'] = $ps;
        }
        if (isset(Orders::PAYMENT_METHODS[$pm = str_input('metoda')])) {
            $where[] = 'payment_method = :pm';
            $p['pm'] = $pm;
        }
        if (($q = str_input('q')) !== '') {
            $where[] = '(number LIKE :q1 OR email LIKE :q2 OR phone LIKE :q3 OR last_name LIKE :q4 OR first_name LIKE :q5 OR company LIKE :q6 OR awb LIKE :q7)';
            foreach (range(1, 7) as $i) {
                $p['q' . $i] = '%' . $q . '%';
            }
        }
        if (($from = str_input('de_la')) !== '' && strtotime($from)) {
            $where[] = 'created_at >= :from';
            $p['from'] = gmdate('Y-m-d H:i:s', (int)strtotime($from . ' 00:00:00'));
        }
        if (($to = str_input('pana_la')) !== '' && strtotime($to)) {
            $where[] = 'created_at <= :to';
            $p['to'] = gmdate('Y-m-d H:i:s', (int)strtotime($to . ' 23:59:59'));
        }
        return ['(' . implode(') AND (', $where) . ')', $p];
    }

    public function index(): string
    {
        [$w, $p] = $this->filters();
        $total = (int)DB::val("SELECT COUNT(*) FROM orders WHERE $w", $p);
        $pg = $this->paginate($total, 40, (int)($_GET['p'] ?? 1));
        $rows = DB::all("SELECT o.*, (SELECT SUM(qty) FROM order_items i WHERE i.order_id = o.id) AS items FROM orders o WHERE $w ORDER BY o.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
        $sum = (float)DB::val("SELECT COALESCE(SUM(total),0) FROM orders WHERE $w", $p);
        $counts = [];
        foreach (DB::all('SELECT status, COUNT(*) AS n FROM orders GROUP BY status') as $r) {
            $counts[$r['status']] = (int)$r['n'];
        }
        return $this->render('orders/index', ['rows' => $rows, 'pg' => $pg, 'sum' => $sum, 'counts' => $counts, 'title' => 'Comenzi']);
    }

    public function show(string $id): string
    {
        $o = $this->order($id);
        return $this->render('orders/show', [
            'o' => $o,
            'items' => Orders::items((int)$o['id']),
            'events' => Orders::events((int)$o['id']),
            'history' => DB::all('SELECT id, number, total, status, created_at FROM orders WHERE LOWER(email) = ? AND id <> ? ORDER BY id DESC LIMIT 10', [mb_strtolower($o['email']), $o['id']]),
            'title' => 'Comanda ' . $o['number'],
        ]);
    }

    public function printout(string $id): string
    {
        $o = $this->order($id);
        return \App\Core\View::partial('admin/orders/print', ['o' => $o, 'items' => Orders::items((int)$o['id'])]);
    }

    public function status(string $id): never
    {
        $o = $this->order($id);
        $st = str_input('status');
        $note = Sanitizer::text(str_input('note'), 1000);
        if (!isset(Orders::STATUSES[$st])) {
            flash('err', 'Stare invalidă.');
            redirect('/admin/comenzi/' . $id);
        }
        if ($st === 'expediata' && !$o['awb'] && str_input('awb') === '') {
            flash('info', 'Sfat: adaugă numărul AWB, ca să-l primească și clientul pe email.');
        }
        Orders::setStatus($o, $st, $note, !empty($_POST['notify']));
        if ($st === 'anulata' && $o['payment_status'] === 'autorizata' && $o['bt_order_id']) {
            flash('info', 'Plata este doar autorizată pe cardul clientului – apasă „Anulează autorizarea” ca suma să fie deblocată.');
        }
        if ($st === 'anulata' && $o['payment_status'] === 'platita' && $o['payment_method'] === 'card') {
            flash('info', 'Comanda a fost plătită cu cardul – dacă e cazul, fă rambursarea din secțiunea Plată.');
        }
        flash('ok', 'Starea comenzii a fost actualizată' . (!empty($_POST['notify']) ? ' și clientul a fost anunțat pe email.' : '.'));
        redirect('/admin/comenzi/' . $id);
    }

    public function shipping(string $id): never
    {
        $o = $this->order($id);
        $courier = Sanitizer::text(str_input('courier'), 60);
        $awb = Sanitizer::text(str_input('awb'), 60);
        $url = trim(str_input('tracking_url'));
        if ($url === '' && $awb !== '' && !empty(self::COURIERS[$courier])) {
            $url = str_replace('{awb}', rawurlencode($awb), self::COURIERS[$courier]);
        }
        if ($url !== '' && !preg_match('#^https?://#', $url)) {
            $url = '';
        }
        DB::update('orders', ['courier' => $courier ?: null, 'awb' => $awb ?: null, 'tracking_url' => $url ?: null, 'updated_at' => DB::now()], 'id = :id', ['id' => $o['id']]);
        Orders::event((int)$o['id'], 'livrare', 'Livrare: ' . ($courier ?: '—') . ($awb ? ', AWB ' . $awb : ''));
        if (!empty($_POST['ship'])) {
            Orders::setStatus(Orders::find((int)$o['id']), 'expediata', '', !empty($_POST['notify']));
            flash('ok', 'Comanda a fost marcată ca expediată' . (!empty($_POST['notify']) ? ', iar clientul a primit AWB-ul pe email.' : '.'));
        } else {
            flash('ok', 'Datele de livrare au fost salvate.');
        }
        redirect('/admin/comenzi/' . $id);
    }

    public function note(string $id): never
    {
        $o = $this->order($id);
        $note = Sanitizer::text(str_input('note'), 2000);
        if ($note !== '') {
            Orders::event((int)$o['id'], 'nota', $note);
            flash('ok', 'Nota a fost adăugată în istoricul comenzii.');
        }
        redirect('/admin/comenzi/' . $id);
    }

    public function customer(string $id): never
    {
        $o = $this->order($id);
        $fields = ['first_name', 'last_name', 'email', 'phone', 'company', 'cui', 'reg_com', 'billing_address', 'billing_city', 'billing_county', 'billing_postcode', 'shipping_name', 'shipping_phone', 'shipping_address', 'shipping_city', 'shipping_county', 'shipping_postcode'];
        $data = [];
        foreach ($fields as $f) {
            $data[$f] = Sanitizer::text(str_input($f), 200) ?: (in_array($f, ['first_name', 'last_name', 'email', 'phone', 'billing_address', 'billing_city', 'billing_county'], true) ? $o[$f] : null);
        }
        $data['ship_same'] = !empty($_POST['ship_same']) ? 1 : 0;
        $data['admin_note'] = Sanitizer::text(str_input('admin_note'), 3000);
        $data['updated_at'] = DB::now();
        DB::update('orders', $data, 'id = :id', ['id' => $o['id']]);
        Orders::event((int)$o['id'], 'editare', 'Datele clientului / adresele au fost modificate din panou.');
        flash('ok', 'Datele comenzii au fost salvate.');
        redirect('/admin/comenzi/' . $id);
    }

    public function payment(string $id, string $op): never
    {
        $o = $this->order($id);
        $ok = true;
        $msg = '';
        if ($op === 'marcheaza') {
            $ps = str_input('payment_status');
            if (isset(Orders::PAYMENT_STATUSES[$ps])) {
                DB::update('orders', ['payment_status' => $ps, 'paid_at' => $ps === 'platita' ? ($o['paid_at'] ?: DB::now()) : $o['paid_at'], 'updated_at' => DB::now()], 'id = :id', ['id' => $o['id']]);
                Orders::event((int)$o['id'], 'plata', 'Stare plată setată manual: ' . Orders::paymentLabel($ps));
                if ($ps === 'platita' && $o['status'] === 'asteptare_plata') {
                    Orders::setStatus(Orders::find((int)$o['id']), 'noua');
                }
                $msg = 'Starea plății a fost actualizată.';
            }
        } elseif (!$o['bt_order_id']) {
            [$ok, $msg] = [false, 'Comanda nu are o plată BT iPay.'];
        } else {
            $amount = (float)str_replace(',', '.', str_input('amount'));
            [$ok, $msg] = match ($op) {
                'verifica' => BtIpay::sync($o, 'verificare manuală'),
                'incaseaza' => BtIpay::deposit($o, $amount > 0 ? $amount : null),
                'anuleaza' => BtIpay::reverse($o),
                'ramburseaza' => $amount > 0 && $amount <= (float)$o['bt_deposited_amount'] - (float)$o['bt_refunded_amount'] + 0.001
                    ? BtIpay::refund($o, $amount)
                    : [false, 'Suma de rambursat trebuie să fie între 0 și ' . Shop::money((float)$o['bt_deposited_amount'] - (float)$o['bt_refunded_amount']) . '.'],
                default => [false, 'Operație necunoscută.'],
            };
            if ($op === 'verifica' && $ok) {
                $msg = 'Stare la BT iPay: ' . $msg;
            }
        }
        flash($ok ? 'ok' : 'err', $msg);
        redirect('/admin/comenzi/' . $id);
    }

    public function resend(string $id): never
    {
        $o = $this->order($id);
        str_input('what') === 'stare' ? Orders::sendStatus($o) : Orders::sendConfirmation($o);
        flash('ok', 'Emailul a fost retrimis clientului (' . $o['email'] . ').');
        redirect('/admin/comenzi/' . $id);
    }

    public function export(): never
    {
        [$w, $p] = $this->filters();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="comenzi-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Număr', 'Data', 'Stare', 'Plată', 'Stare plată', 'Nume', 'Email', 'Telefon', 'Firmă', 'CUI', 'Adresă livrare', 'Localitate', 'Județ', 'Cod poștal', 'Produse', 'Subtotal', 'Reducere', 'Livrare', 'Total', 'Curier', 'AWB', 'Mențiuni'], ';');
        foreach (DB::all("SELECT * FROM orders WHERE $w ORDER BY id DESC", $p) as $o) {
            $same = (int)$o['ship_same'] === 1;
            $items = implode(' | ', array_map(fn($i) => $i['qty'] . ' x ' . $i['name'], Orders::items((int)$o['id'])));
            fputcsv($out, [
                $o['number'], local_time($o['created_at']), Orders::statusLabel($o['status']), Orders::PAYMENT_METHODS[$o['payment_method']] ?? $o['payment_method'], Orders::paymentLabel($o['payment_status']),
                $same ? Orders::customerName($o) : $o['shipping_name'], $o['email'], $same ? $o['phone'] : ($o['shipping_phone'] ?: $o['phone']), $o['company'], $o['cui'],
                $same ? $o['billing_address'] : $o['shipping_address'], $same ? $o['billing_city'] : $o['shipping_city'], $same ? $o['billing_county'] : $o['shipping_county'], $same ? $o['billing_postcode'] : $o['shipping_postcode'],
                $items, number_format((float)$o['subtotal'], 2, ',', ''), number_format((float)$o['discount'], 2, ',', ''), number_format((float)$o['shipping_cost'], 2, ',', ''), number_format((float)$o['total'], 2, ',', ''),
                $o['courier'], $o['awb'], $o['customer_note'],
            ], ';');
        }
        fclose($out);
        exit;
    }

    public function customers(): string
    {
        $q = str_input('q');
        $w = $q !== '' ? 'WHERE email LIKE :q1 OR last_name LIKE :q2 OR phone LIKE :q3 OR company LIKE :q4' : '';
        $p = $q !== '' ? ['q1' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%", 'q4' => "%$q%"] : [];
        $v = DashboardController::VALID;
        $rows = DB::all("SELECT LOWER(email) AS em, MAX(first_name) AS first_name, MAX(last_name) AS last_name, MAX(phone) AS phone, MAX(company) AS company, MAX(billing_city) AS city, COUNT(*) AS orders,
            SUM(CASE WHEN $v THEN total ELSE 0 END) AS spent, MAX(created_at) AS last_at, MIN(created_at) AS first_at FROM orders $w GROUP BY LOWER(email) ORDER BY last_at DESC LIMIT 500", $p);
        return $this->render('orders/customers', ['rows' => $rows, 'q' => $q, 'title' => 'Clienți']);
    }
}
