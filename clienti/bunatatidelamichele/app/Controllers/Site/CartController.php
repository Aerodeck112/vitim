<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Csrf;
use App\Core\Seo;
use App\Core\Settings;
use App\Core\Shop;

final class CartController extends SiteController
{
    private function guard(): void
    {
        if (!Csrf::sameOrigin()) {
            if ($this->wantsJson()) {
                json_out(['ok' => false, 'message' => 'Cerere invalidă. Reîncarcă pagina.'], 403);
            }
            redirect('/cos');
        }
    }

    private function payload(string $message = '', bool $ok = true): array
    {
        $t = Shop::totals();
        $lines = [];
        foreach ($t['lines'] as $l) {
            $lines[] = [
                'id' => $l['id'],
                'name' => $l['product']['name'],
                'url' => url(Shop::url($l['product'])),
                'image' => ($img = Shop::image($l['product'])) ? upload_url($img) : '',
                'qty' => $l['qty'],
                'price' => Shop::money($l['price']),
                'total' => Shop::money($l['total']),
                'available' => $l['available'],
            ];
        }
        return [
            'ok' => $ok,
            'message' => $message,
            'count' => $t['count'],
            'subtotal' => Shop::money($t['subtotal']),
            'total' => Shop::money($t['total']),
            'lines' => $lines,
            'free_left' => $t['free_left'] > 0 ? Shop::money($t['free_left']) : '',
        ];
    }

    public function summary(): never
    {
        $this->personal();
        json_out($this->payload());
    }

    public function add(): never
    {
        $this->guard();
        [$ok, $msg] = Shop::add((int)input('product_id', 0), max(1, (int)input('qty', 1)));
        if ($this->wantsJson()) {
            json_out($this->payload($msg, $ok), $ok ? 200 : 422);
        }
        if (!$ok) {
            redirect('/cos?eroare=' . rawurlencode($msg));
        }
        redirect(input('then') === 'checkout' ? '/finalizare' : '/cos');
    }

    public function update(): never
    {
        $this->guard();
        if ($rm = (int)input('remove', 0)) {
            Shop::setQty($rm, 0);
        }
        foreach ((array)($_POST['qty'] ?? []) as $pid => $q) {
            if ((int)$pid !== $rm) {
                Shop::setQty((int)$pid, (int)$q);
            }
        }
        if ($this->wantsJson()) {
            json_out($this->payload());
        }
        redirect('/cos');
    }

    public function coupon(): never
    {
        $this->guard();
        if (input('remove')) {
            Shop::setCoupon(null);
            redirect('/cos');
        }
        $code = mb_substr(trim(str_input('code')), 0, 40);
        $t = Shop::totals();
        [$c, $err] = Shop::validateCoupon($code, $t['subtotal']);
        if ($c) {
            Shop::setCoupon($c['code']);
            redirect('/cos?cupon=ok');
        }
        redirect('/cos?eroare=' . rawurlencode($err ?: 'Introdu un cod de reducere.'));
    }

    public function show(): string
    {
        $this->personal();
        $t = Shop::totals();
        $seo = $this->seo('Coșul de cumpărături', 'Produsele din coșul tău.');
        $seo->noindex = true;
        $error = mb_substr(str_input('eroare'), 0, 200);
        return $this->view('cart', ['t' => $t, 'error' => $error, 'note' => (string)Settings::get('cart_note')], $seo);
    }
}
