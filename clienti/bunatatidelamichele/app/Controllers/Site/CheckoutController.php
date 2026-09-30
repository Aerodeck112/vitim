<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\BtIpay;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\Orders;
use App\Core\RateLimit;
use App\Core\Sanitizer;
use App\Core\Settings;
use App\Core\Shop;

final class CheckoutController extends SiteController
{
    private const FIELDS = ['customer_type', 'first_name', 'last_name', 'email', 'phone', 'company', 'cui', 'reg_com', 'billing_address', 'billing_city', 'billing_county', 'billing_postcode', 'shipping_name', 'shipping_phone', 'shipping_address', 'shipping_city', 'shipping_county', 'shipping_postcode', 'note', 'shipping_method', 'payment_method'];

    public function form(array $values = [], array $errors = []): string
    {
        $this->personal();
        $t = Shop::totals(null, null, (string)($values['shipping_method'] ?? 'curier'), (string)($values['payment_method'] ?? ''));
        if ($t['count'] === 0) {
            redirect('/cos');
        }
        $seo = $this->seo('Finalizare comandă', 'Completează datele de livrare și alege metoda de plată.');
        $seo->noindex = true;
        return $this->view('checkout', [
            't' => $t,
            'v' => $values + ['customer_type' => 'pf', 'ship_same' => '1', 'shipping_method' => 'curier', 'payment_method' => array_key_first(Shop::paymentMethods($t['total'])) ?? ''],
            'errors' => $errors,
            'shipping' => Shop::shippingMethods(),
            'payments' => Shop::paymentMethods($t['total']),
            'counties' => Shop::counties(),
            'token' => Csrf::formToken(),
        ], $seo);
    }

    public function place(): string
    {
        $this->personal();
        $v = [];
        foreach (self::FIELDS as $f) {
            $v[$f] = Sanitizer::text(str_input($f), $f === 'note' ? 1000 : 200);
        }
        $v['ship_same'] = !empty($_POST['ship_same']) ? '1' : '';
        $v['email'] = mb_strtolower($v['email']);
        $errors = [];

        if (!Csrf::sameOrigin() || !Csrf::verifyFormToken(str_input('_t'), 2)) {
            $errors[] = 'Sesiunea formularului a expirat. Verifică datele și trimite din nou.';
        }
        if (str_input('website') !== '') {
            $errors[] = 'Cerere respinsă.';
        }
        if (!RateLimit::hit('checkout:' . client_ip(), 12, 3600)) {
            $errors[] = 'Prea multe comenzi într-un timp scurt. Încearcă din nou mai târziu sau scrie-ne.';
        }
        $v['customer_type'] = $v['customer_type'] === 'pj' ? 'pj' : 'pf';
        $req = ['first_name' => 'Prenume', 'last_name' => 'Nume', 'email' => 'Email', 'phone' => 'Telefon', 'billing_address' => 'Adresă', 'billing_city' => 'Localitate', 'billing_county' => 'Județ'];
        if ($v['customer_type'] === 'pj') {
            $req += ['company' => 'Denumire firmă', 'cui' => 'CUI'];
        }
        if (!$v['ship_same']) {
            $req += ['shipping_name' => 'Nume destinatar', 'shipping_address' => 'Adresă de livrare', 'shipping_city' => 'Localitate livrare', 'shipping_county' => 'Județ livrare'];
        }
        foreach ($req as $k => $label) {
            if ($v[$k] === '') {
                $errors[$k] = "Completează câmpul „{$label}”.";
            }
        }
        if ($v['email'] !== '' && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Adresa de email nu este validă.';
        }
        $digits = preg_replace('/\D+/', '', $v['phone']);
        if ($v['phone'] !== '' && (strlen($digits) < 10 || strlen($digits) > 15)) {
            $errors['phone'] = 'Numărul de telefon nu este valid (ex. 07xx xxx xxx).';
        }
        foreach (['billing_county', 'shipping_county'] as $k) {
            if ($v[$k] !== '' && !in_array($v[$k], Shop::counties(), true)) {
                $errors[$k] = 'Alege județul din listă.';
            }
        }
        foreach (['billing_postcode', 'shipping_postcode'] as $k) {
            if ($v[$k] !== '' && !preg_match('/^\d{6}$/', $v[$k])) {
                $errors[$k] = 'Codul poștal are 6 cifre.';
            }
        }
        if ($v['customer_type'] === 'pj' && $v['cui'] !== '' && !preg_match('/^(RO)?\s*\d{2,10}$/i', $v['cui'])) {
            $errors['cui'] = 'CUI-ul nu pare valid (ex. RO12345678 sau 12345678).';
        }
        if (empty($_POST['terms'])) {
            $errors['terms'] = 'Pentru a plasa comanda trebuie să accepți Termenii și condițiile.';
        }

        $t = Shop::totals(null, null, $v['shipping_method'], $v['payment_method']);
        if ($t['count'] === 0) {
            redirect('/cos');
        }
        foreach ($t['lines'] as $l) {
            if (!$l['available']) {
                $errors[] = '„' . $l['product']['name'] . '” nu mai este disponibil. L-am scos din totalul comenzii – verifică coșul.';
            } elseif ($l['qty'] !== $l['requested']) {
                Shop::setQty($l['id'], $l['qty']);
                $errors[] = 'Cantitatea pentru „' . $l['product']['name'] . '” a fost ajustată la ' . $l['qty'] . ' (stoc disponibil).';
            }
        }
        $payments = Shop::paymentMethods($t['total']);
        if (!isset($payments[$v['payment_method']])) {
            $errors['payment_method'] = 'Alege o metodă de plată.';
        }
        $min = (float)Settings::get('min_order_total', '0');
        if ($min > 0 && $t['subtotal'] - $t['discount'] < $min) {
            $errors[] = 'Valoarea minimă a unei comenzi este ' . Shop::money($min) . '.';
        }
        if ($t['coupon_error']) {
            Shop::setCoupon(null);
            $errors[] = $t['coupon_error'] . ' L-am scos din comandă.';
        }
        if ($errors) {
            return $this->form($v, $errors);
        }

        $c = $v + ['ship_same' => (bool)$v['ship_same']];
        $c['cui'] = strtoupper(str_replace(' ', '', $c['cui']));
        $c['phone'] = trim($c['phone']);
        $order = Orders::create($c, $t, $v['payment_method']);
        Shop::clear();
        // ține minte datele clientului pentru următoarea comandă (doar în browserul lui)
        setcookie('bdm_last_order', $order['token'], ['expires' => time() + 86400 * 30, 'path' => base_path() . '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => str_starts_with(abs_url('/'), 'https://')]);

        if ($order['payment_method'] === 'card') {
            [$formUrl, $err] = BtIpay::register($order);
            if ($formUrl) {
                redirect($formUrl);
            }
            redirect(Orders::publicUrl($order) . '?plata=eroare');
        }
        Orders::sendConfirmation($order);
        Orders::notifyShop($order);
        redirect(Orders::publicUrl($order) . '?nou=1');
    }

    private function findByToken(string $token): ?array
    {
        return DB::row('SELECT * FROM orders WHERE token = ?', [$token]);
    }

    public function order(string $token): string
    {
        $this->personal();
        $o = $this->findByToken($token);
        if (!$o) {
            return $this->notFound();
        }
        // plată cu cardul încă neconfirmată: întrebăm banca (maxim o dată la 10 secunde)
        if ($o['payment_method'] === 'card' && $o['bt_order_id'] && in_array($o['payment_status'], ['neplatita'], true)
            && strtotime($o['updated_at'] . ' UTC') < time() - 10 && BtIpay::configured()) {
            BtIpay::sync($o, 'pagina comenzii');
            $o = $this->findByToken($token);
        }
        $seo = $this->seo('Comanda ' . $o['number'], 'Detaliile comenzii tale.');
        $seo->noindex = true;
        $flash = str_input('plata');
        return $this->view('order', [
            'o' => $o,
            'items' => Orders::items((int)$o['id']),
            'isNew' => str_input('nou') === '1',
            'payError' => $flash === 'eroare' || $flash === 'respinsa',
            'canPay' => $o['payment_method'] === 'card' && $o['status'] === 'asteptare_plata' && !in_array($o['payment_status'], ['platita', 'autorizata'], true),
            'codAvailable' => Settings::get('pay_cod_enabled') === '1',
            'cardAvailable' => Settings::get('pay_card_enabled') === '1' && BtIpay::configured(),
        ], $seo);
    }

    /** Reîncercare plată cu cardul sau trecere la plata ramburs, pentru comenzile neplătite. */
    public function pay(string $token): never
    {
        $this->personal();
        $o = $this->findByToken($token);
        if (!$o || !Csrf::sameOrigin()) {
            redirect('/');
        }
        $open = in_array($o['status'], ['asteptare_plata', 'noua'], true) && !in_array($o['payment_status'], ['platita', 'autorizata'], true);
        if (!$open || !RateLimit::hit('pay:' . $o['id'], 10, 3600)) {
            redirect(Orders::publicUrl($o));
        }
        if (str_input('metoda') === 'ramburs' && Settings::get('pay_cod_enabled') === '1') {
            if ($o['bt_order_id']) {
                BtIpay::sync($o, 'schimbare metodă');
                $o = $this->findByToken($token);
                if (in_array($o['payment_status'], ['platita', 'autorizata'], true)) {
                    redirect(Orders::publicUrl($o));
                }
            }
            $fee = (float)Settings::get('pay_cod_fee', '0');
            DB::update('orders', ['payment_method' => 'ramburs', 'payment_status' => 'neplatita', 'payment_fee' => $fee, 'total' => round((float)$o['total'] - (float)$o['payment_fee'] + $fee, 2), 'updated_at' => DB::now()], 'id = :id', ['id' => $o['id']]);
            $o = $this->findByToken($token);
            Orders::setStatus($o, 'noua', 'Clientul a ales plata ramburs după ce plata cu cardul nu a fost finalizată.');
            $o = $this->findByToken($token);
            Orders::sendConfirmation($o);
            Orders::notifyShop($o);
            redirect(Orders::publicUrl($o) . '?nou=1');
        }
        if ($o['payment_method'] !== 'card') {
            DB::update('orders', ['payment_method' => 'card', 'updated_at' => DB::now()], 'id = :id', ['id' => $o['id']]);
            $o = $this->findByToken($token);
        }
        if ($o['status'] !== 'asteptare_plata') {
            Orders::setStatus($o, 'asteptare_plata', 'Clientul a ales plata cu cardul.');
            $o = $this->findByToken($token);
        }
        [$formUrl] = BtIpay::register($o);
        if ($formUrl) {
            redirect($formUrl);
        }
        redirect(Orders::publicUrl($o) . '?plata=eroare');
    }

    public function track(): string
    {
        $this->personal();
        $error = '';
        if (request_method() === 'POST') {
            $num = strtoupper(trim(str_input('number')));
            $email = mb_strtolower(trim(str_input('email')));
            if (!RateLimit::hit('track:' . client_ip(), 10, 900)) {
                $error = 'Prea multe încercări. Încearcă din nou peste 15 minute.';
            } elseif ($num !== '' && $email !== '' && ($o = DB::row('SELECT token FROM orders WHERE UPPER(number) = ? AND LOWER(email) = ?', [$num, $email]))) {
                redirect('/comanda/' . $o['token']);
            } else {
                $error = 'Nu am găsit nicio comandă cu aceste date. Verifică numărul comenzii (îl găsești în emailul de confirmare) și adresa de email.';
            }
        }
        $last = $_COOKIE['bdm_last_order'] ?? '';
        $lastOrder = is_string($last) && preg_match('/^[A-Za-z0-9_-]{16,40}$/', $last) ? DB::row('SELECT number, token, created_at, total, status FROM orders WHERE token = ?', [$last]) : null;
        $seo = $this->seo('Urmărire comandă', 'Verifică starea comenzii tale cu numărul comenzii și adresa de email.', [['Urmărire comandă', '/urmarire-comanda']]);
        $seo->noindex = true;
        return $this->view('track', ['error' => $error, 'lastOrder' => $lastOrder], $seo);
    }
}
