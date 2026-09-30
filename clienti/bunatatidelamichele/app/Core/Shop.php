<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Magazinul: prețuri, produse, coș, cupoane, livrare și totaluri.
 * Coșul se ține în baza de date, identificat printr-un cookie aleator (fără date personale).
 */
final class Shop
{
    public const CART_COOKIE = 'bdm_cart';
    public const COUNT_COOKIE = 'bdm_cn';

    private static ?array $cart = null;

    // ---------------------------------------------------------------- formatare

    public static function money(float|string|null $v, bool $withCurrency = true): string
    {
        $s = number_format((float)$v, 2, ',', '.');
        return $withCurrency ? $s . ' ' . Settings::get('currency_label', 'lei') : $s;
    }

    /** Preț fără zecimale inutile, pentru titluri (ex. „130 lei”, „1,30 lei”). */
    public static function moneyShort(float|string|null $v): string
    {
        $f = (float)$v;
        return (floor($f) == $f ? number_format($f, 0, ',', '.') : number_format($f, 2, ',', '.')) . ' ' . Settings::get('currency_label', 'lei');
    }

    // ---------------------------------------------------------------- produse

    public static function price(array $p): float
    {
        $sale = self::onSale($p);
        return round($sale ? (float)$p['sale_price'] : (float)$p['price'], 2);
    }

    public static function onSale(array $p): bool
    {
        if ($p['sale_price'] === null || $p['sale_price'] === '' || (float)$p['sale_price'] <= 0 || (float)$p['sale_price'] >= (float)$p['price']) {
            return false;
        }
        return empty($p['sale_until']) || strtotime($p['sale_until'] . ' UTC') > time();
    }

    public static function discountPercent(array $p): int
    {
        return self::onSale($p) ? (int)round((1 - (float)$p['sale_price'] / max(0.01, (float)$p['price'])) * 100) : 0;
    }

    public static function images(array $p): array
    {
        return array_values(array_filter(json_list($p['images'] ?? '[]'), 'is_string'));
    }

    public static function image(array $p): string
    {
        return self::images($p)[0] ?? '';
    }

    public static function url(array $p): string
    {
        return '/produs/' . $p['slug'];
    }

    public static function inStock(array $p): bool
    {
        if (($p['stock_status'] ?? 'instock') === 'outofstock') {
            return false;
        }
        if (!empty($p['manage_stock']) && (int)$p['stock'] <= 0) {
            return ($p['stock_status'] ?? '') === 'onbackorder';
        }
        return true;
    }

    public static function stockLabel(array $p): array
    {
        if (!self::inStock($p)) {
            return ['out', 'Stoc epuizat'];
        }
        if (($p['stock_status'] ?? '') === 'onbackorder' && !empty($p['manage_stock']) && (int)$p['stock'] <= 0) {
            return ['back', 'Disponibil la comandă'];
        }
        if (!empty($p['manage_stock']) && (int)$p['stock'] <= (int)Settings::get('low_stock_threshold', '5')) {
            return ['low', 'Stoc limitat – ultimele ' . (int)$p['stock'] . ' bucăți'];
        }
        return ['in', 'În stoc'];
    }

    /** Cantitatea permisă pentru un produs (minim, pas, stoc). */
    public static function normalizeQty(array $p, int $qty): int
    {
        $min = max(1, (int)$p['min_qty']);
        $step = max(1, (int)$p['qty_step']);
        if ($qty <= 0) {
            return 0;
        }
        $qty = max($min, $qty);
        if ($step > 1) {
            $qty = $min + (int)ceil(($qty - $min) / $step) * $step;
        }
        if (!empty($p['manage_stock']) && ($p['stock_status'] ?? '') !== 'onbackorder') {
            $max = max(0, (int)$p['stock']);
            if ($step > 1 && $max >= $min) {
                $max = $min + (int)floor(($max - $min) / $step) * $step;
            }
            $qty = min($qty, $max);
        }
        return min($qty, 9999);
    }

    public static function publishedProducts(string $where = '1=1', array $params = [], string $order = 'p.sort, p.name', int $limit = 0): array
    {
        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.published = 1 AND ($where) ORDER BY $order";
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }
        return DB::all($sql, $params);
    }

    // ---------------------------------------------------------------- coș

    private static function token(bool $create = false): ?string
    {
        $t = $_COOKIE[self::CART_COOKIE] ?? '';
        if (is_string($t) && preg_match('/^[A-Za-z0-9_-]{24,64}$/', $t)) {
            return $t;
        }
        if (!$create) {
            return null;
        }
        $t = random_token(24);
        self::cookie(self::CART_COOKIE, $t, time() + 30 * 86400, true);
        $_COOKIE[self::CART_COOKIE] = $t;
        return $t;
    }

    private static function cookie(string $name, string $value, int $expires, bool $httpOnly): void
    {
        if (headers_sent()) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        setcookie($name, $value, ['expires' => $expires, 'path' => base_path() . '/', 'secure' => $secure, 'httponly' => $httpOnly, 'samesite' => 'Lax']);
    }

    /** @return array{items: array<int,int>, coupon: ?string} */
    public static function cart(): array
    {
        if (self::$cart !== null) {
            return self::$cart;
        }
        $t = self::token();
        $row = $t ? DB::row('SELECT items, coupon FROM carts WHERE token = ?', [$t]) : null;
        $items = [];
        foreach (json_list($row['items'] ?? '[]') as $pid => $qty) {
            if ((int)$pid > 0 && (int)$qty > 0) {
                $items[(int)$pid] = (int)$qty;
            }
        }
        return self::$cart = ['items' => $items, 'coupon' => $row['coupon'] ?? null];
    }

    private static function save(array $items, ?string $coupon): void
    {
        $t = self::token(true);
        $json = json_encode((object)$items);
        if (DB::val('SELECT id FROM carts WHERE token = ?', [$t])) {
            DB::update('carts', ['items' => $json, 'coupon' => $coupon, 'updated_at' => DB::now()], 'token = :t', ['t' => $t]);
        } else {
            DB::insert('carts', ['token' => $t, 'items' => $json, 'coupon' => $coupon, 'created_at' => DB::now(), 'updated_at' => DB::now()]);
        }
        self::$cart = ['items' => $items, 'coupon' => $coupon];
        self::cookie(self::COUNT_COOKIE, (string)array_sum($items), time() + 30 * 86400, false);
    }

    /** @return array{0: bool, 1: string} */
    public static function add(int $productId, int $qty): array
    {
        $p = DB::row('SELECT * FROM products WHERE id = ? AND published = 1', [$productId]);
        if (!$p) {
            return [false, 'Produsul nu mai este disponibil.'];
        }
        if (!self::inStock($p)) {
            return [false, 'Ne pare rău, produsul nu mai este în stoc.'];
        }
        $cart = self::cart();
        $current = $cart['items'][$productId] ?? 0;
        $new = self::normalizeQty($p, $current + max(1, $qty));
        if ($new <= $current) {
            return [false, 'Ai adăugat deja în coș toată cantitatea disponibilă.'];
        }
        $cart['items'][$productId] = $new;
        self::save($cart['items'], $cart['coupon']);
        return [true, 'Produsul a fost adăugat în coș.'];
    }

    public static function setQty(int $productId, int $qty): void
    {
        $cart = self::cart();
        $p = DB::row('SELECT * FROM products WHERE id = ?', [$productId]);
        $q = $p ? self::normalizeQty($p, $qty) : 0;
        if ($q <= 0) {
            unset($cart['items'][$productId]);
        } else {
            $cart['items'][$productId] = $q;
        }
        self::save($cart['items'], $cart['coupon']);
    }

    public static function setCoupon(?string $code): void
    {
        $cart = self::cart();
        self::save($cart['items'], $code ? mb_strtoupper($code) : null);
    }

    public static function clear(): void
    {
        $t = self::token();
        if ($t) {
            DB::delete('carts', 'token = ?', [$t]);
        }
        self::$cart = ['items' => [], 'coupon' => null];
        self::cookie(self::COUNT_COOKIE, '0', time() + 30 * 86400, false);
    }

    public static function count(): int
    {
        return array_sum(self::cart()['items']);
    }

    /** Liniile coșului, cu produsele actuale din baza de date. */
    public static function lines(?array $items = null): array
    {
        $items ??= self::cart()['items'];
        if (!$items) {
            return [];
        }
        $ids = array_keys($items);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $products = [];
        foreach (DB::all("SELECT * FROM products WHERE id IN ($ph)", $ids) as $p) {
            $products[(int)$p['id']] = $p;
        }
        $lines = [];
        foreach ($items as $pid => $qty) {
            $p = $products[$pid] ?? null;
            if (!$p || !(int)$p['published']) {
                continue;
            }
            $price = self::price($p);
            $available = self::inStock($p);
            $q = self::normalizeQty($p, (int)$qty);
            $lines[] = [
                'product' => $p,
                'id' => (int)$pid,
                'qty' => $q,
                'requested' => (int)$qty,
                'price' => $price,
                'total' => round($price * $q, 2),
                'available' => $available && $q > 0,
            ];
        }
        return $lines;
    }

    // ---------------------------------------------------------------- cupoane

    /** @return array{0: ?array, 1: string} */
    public static function validateCoupon(?string $code, float $subtotal): array
    {
        $code = trim((string)$code);
        if ($code === '') {
            return [null, ''];
        }
        $c = DB::row('SELECT * FROM coupons WHERE UPPER(code) = ? AND active = 1', [mb_strtoupper($code)]);
        if (!$c) {
            return [null, 'Codul de reducere nu există sau nu mai este activ.'];
        }
        if (!empty($c['starts_at']) && strtotime($c['starts_at'] . ' UTC') > time()) {
            return [null, 'Codul de reducere nu este încă valabil.'];
        }
        if (!empty($c['expires_at']) && strtotime($c['expires_at'] . ' UTC') < time()) {
            return [null, 'Codul de reducere a expirat.'];
        }
        if ((int)$c['max_uses'] > 0 && (int)$c['used'] >= (int)$c['max_uses']) {
            return [null, 'Codul de reducere a fost deja folosit de numărul maxim de ori.'];
        }
        if ((float)$c['min_total'] > 0 && $subtotal < (float)$c['min_total']) {
            return [null, 'Codul se aplică pentru comenzi de minimum ' . self::money($c['min_total']) . '.'];
        }
        return [$c, ''];
    }

    // ---------------------------------------------------------------- livrare și plată

    public static function shippingMethods(): array
    {
        $m = ['curier' => ['label' => (string)Settings::get('shipping_label', 'Livrare prin curier'), 'cost' => (float)Settings::get('shipping_cost', '0'), 'text' => (string)Settings::get('delivery_time')]];
        if (Settings::get('pickup_enabled') === '1') {
            $m['ridicare'] = ['label' => (string)Settings::get('pickup_label', 'Ridicare personală'), 'cost' => 0.0, 'text' => (string)Settings::get('pickup_address')];
        }
        return $m;
    }

    public static function paymentMethods(float $total = 0): array
    {
        $m = [];
        if (Settings::get('pay_card_enabled') === '1' && BtIpay::configured()) {
            $m['card'] = ['label' => (string)Settings::get('pay_card_label'), 'text' => (string)Settings::get('pay_card_text'), 'fee' => 0.0];
        }
        $codMax = (float)Settings::get('pay_cod_max', '0');
        if (Settings::get('pay_cod_enabled') === '1' && ($codMax <= 0 || $total <= $codMax)) {
            $m['ramburs'] = ['label' => (string)Settings::get('pay_cod_label'), 'text' => (string)Settings::get('pay_cod_text'), 'fee' => (float)Settings::get('pay_cod_fee', '0')];
        }
        if (Settings::get('pay_transfer_enabled') === '1') {
            $m['transfer'] = ['label' => (string)Settings::get('pay_transfer_label'), 'text' => (string)Settings::get('pay_transfer_text'), 'fee' => 0.0];
        }
        return $m;
    }

    public static function freeShippingOver(): float
    {
        return (float)Settings::get('free_shipping_over', '0');
    }

    /**
     * Totalurile comenzii.
     * @return array{lines: array, subtotal: float, discount: float, coupon: ?array, coupon_error: string, shipping: float, shipping_method: string, shipping_label: string, payment_fee: float, total: float, count: int, free_left: float}
     */
    public static function totals(?array $lines = null, ?string $couponCode = null, string $shipping = 'curier', string $payment = ''): array
    {
        $lines ??= self::lines();
        $couponCode ??= self::cart()['coupon'];
        $subtotal = 0.0;
        $count = 0;
        foreach ($lines as $l) {
            if ($l['available']) {
                $subtotal += $l['total'];
                $count += $l['qty'];
            }
        }
        $subtotal = round($subtotal, 2);
        [$coupon, $couponError] = self::validateCoupon($couponCode, $subtotal);
        $discount = 0.0;
        if ($coupon) {
            $discount = $coupon['type'] === 'percent' ? round($subtotal * min(100, (float)$coupon['value']) / 100, 2) : min($subtotal, (float)$coupon['value']);
        }
        $methods = self::shippingMethods();
        if (!isset($methods[$shipping])) {
            $shipping = array_key_first($methods);
        }
        $shipCost = $count > 0 ? (float)$methods[$shipping]['cost'] : 0.0;
        $free = self::freeShippingOver();
        $freeLeft = 0.0;
        if ($free > 0 && $shipping === 'curier') {
            if ($subtotal - $discount >= $free) {
                $shipCost = 0.0;
            } else {
                $freeLeft = round($free - ($subtotal - $discount), 2);
            }
        }
        if ($coupon && (int)$coupon['free_shipping']) {
            $shipCost = 0.0;
        }
        $pm = self::paymentMethods($subtotal - $discount + $shipCost);
        $fee = $payment !== '' && isset($pm[$payment]) ? (float)$pm[$payment]['fee'] : 0.0;
        $total = round(max(0, $subtotal - $discount) + $shipCost + $fee, 2);
        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'coupon' => $coupon,
            'coupon_error' => $couponCode && !$coupon ? $couponError : '',
            'shipping' => $shipCost,
            'shipping_method' => (string)$shipping,
            'shipping_label' => (string)($methods[$shipping]['label'] ?? ''),
            'payment_fee' => $fee,
            'total' => $total,
            'count' => $count,
            'free_left' => $freeLeft,
        ];
    }

    /** Coșuri abandonate mai vechi de 60 de zile (curățate din cron). */
    public static function gc(): void
    {
        DB::q('DELETE FROM carts WHERE updated_at < ?', [gmdate('Y-m-d H:i:s', strtotime('-60 days'))]);
    }

    /** Județele României (pentru formularul de livrare). */
    public static function counties(): array
    {
        return ['Alba', 'Arad', 'Argeș', 'Bacău', 'Bihor', 'Bistrița-Năsăud', 'Botoșani', 'Brăila', 'Brașov', 'București', 'Buzău', 'Călărași', 'Caraș-Severin', 'Cluj', 'Constanța', 'Covasna', 'Dâmbovița', 'Dolj', 'Galați', 'Giurgiu', 'Gorj', 'Harghita', 'Hunedoara', 'Ialomița', 'Iași', 'Ilfov', 'Maramureș', 'Mehedinți', 'Mureș', 'Neamț', 'Olt', 'Prahova', 'Sălaj', 'Satu Mare', 'Sibiu', 'Suceava', 'Teleorman', 'Timiș', 'Tulcea', 'Vâlcea', 'Vaslui', 'Vrancea'];
    }
}
