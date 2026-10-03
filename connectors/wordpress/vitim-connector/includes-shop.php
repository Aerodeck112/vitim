<?php
/**
 * VITIM Connector — magazinul WooCommerce: evenimente pentru automatizări (produs văzut, adăugat în coș,
 * comandă începută, comandă plasată), catalogul de produse și linkul care reface coșul abandonat.
 * Totul pleacă semnat către panou, din WP-Cron (fără să încetinească paginile).
 */

if (! defined('ABSPATH')) {
    exit;
}

final class Vitim_Connector_Shop
{
    const QUEUE = 'vitim_connector_events';
    const PRODUCTS = 'vitim_connector_products';
    const SYNC = 'vitim_connector_shop_sync';
    const HOOK = 'vitim_connector_events_flush';
    const CART = 'vitim_cart_';
    const MAX_QUEUE = 2000;

    public static function boot()
    {
        add_action(self::HOOK, [__CLASS__, 'flush']);
        add_action('woocommerce_checkout_order_processed', [__CLASS__, 'orderPlaced'], 20, 1);
        add_action('woocommerce_store_api_checkout_order_processed', [__CLASS__, 'orderPlaced'], 20, 1);
        add_action('woocommerce_checkout_update_order_review', [__CLASS__, 'checkoutStarted']);
        // checkout-ul pe blocuri (implicit în magazinele noi): emailul ajunge prin Store API
        add_action('woocommerce_store_api_cart_update_customer_from_request', [__CLASS__, 'blockCheckoutStarted'], 20, 2);
        add_action('woocommerce_init', [__CLASS__, 'blockConsentField']);
        add_action('woocommerce_add_to_cart', [__CLASS__, 'addedToCart'], 20, 4);
        add_action('template_redirect', [__CLASS__, 'viewedProduct']);
        add_action('wp_loaded', [__CLASS__, 'restoreCart'], 20);
        add_action('woocommerce_review_order_before_submit', [__CLASS__, 'consentField']);
        add_action('woocommerce_checkout_create_order', [__CLASS__, 'saveConsent'], 10, 2);
        add_action('save_post_product', [__CLASS__, 'productChanged'], 20, 1);
        add_action('wp_trash_post', [__CLASS__, 'productDeleted']);
        add_action('before_delete_post', [__CLASS__, 'productDeleted']);
    }

    public static function active()
    {
        return class_exists('WooCommerce') && function_exists('wc_get_order');
    }

    /** @return array{consent: bool} */
    public static function settings()
    {
        $s = get_option('vitim_connector', []);

        return ['consent' => ! isset($s['shop_consent']) || (bool) $s['shop_consent']];
    }

    // ---------- evenimente ----------

    public static function orderPlaced($order)
    {
        $order = is_object($order) ? $order : wc_get_order($order);
        if (! $order || ! self::active()) {
            return;
        }
        $event = self::orderEvent($order);
        if ($event) {
            self::push($event);
            self::schedule(5);
        }
    }

    private static function orderEvent($order, $historical = false)
    {
        $email = $order->get_billing_email();
        if (! $email) {
            return null;
        }
        $items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $qty = max(1, (int) $item->get_quantity());
            $items[] = self::item($product, $item->get_name(), $qty, (float) $item->get_total() / $qty);
        }
        $created = $order->get_date_created();
        $event = [
            'id' => 'order_'.$order->get_id(),
            'type' => 'placed_order',
            'email' => $email,
            'phone' => $order->get_billing_phone(),
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'value' => (float) $order->get_total(),
            'occurred_at' => $created ? $created->getTimestamp() : time(),
            'data' => [
                'order_id' => (string) $order->get_order_number(),
                'currency' => $order->get_currency(),
                'items' => $items,
                'coupon' => implode(', ', $order->get_coupon_codes()),
            ],
        ];
        if (! $historical) {
            $event['contact_token'] = Vitim_Connector::consented('marketing') ? self::cookie() : null;
            $block = $order->get_meta('_wc_other/vitim/marketing') ?: $order->get_meta('_wc_additional/vitim/marketing');
            if ($order->get_meta('_vitim_marketing') === 'yes' || ($block && $block !== 'no' && $block !== '0')) {
                $event['marketing_consent'] = true;
                $event['consent_text'] = self::consentText();
            }
        }

        return $event;
    }

    /** Clientul și-a scris emailul în pagina de comandă clasică (WooCommerce actualizează sumarul prin AJAX). */
    public static function checkoutStarted($post)
    {
        parse_str((string) $post, $data);
        $field = function ($k) use ($data) { return isset($data[$k]) ? sanitize_text_field(wp_unslash($data[$k])) : null; };
        self::capture(isset($data['billing_email']) ? sanitize_email(wp_unslash($data['billing_email'])) : '', $field('billing_first_name'), $field('billing_last_name'), $field('billing_phone'));
    }

    public static function blockCheckoutStarted($customer, $request)
    {
        if (is_object($customer) && method_exists($customer, 'get_billing_email')) {
            self::capture((string) $customer->get_billing_email(), $customer->get_billing_first_name(), $customer->get_billing_last_name(), $customer->get_billing_phone());
        }
    }

    private static function capture($email, $first, $last, $phone)
    {
        if (! Vitim_Connector::consented('marketing')) {
            return; // fără acord de marketing în bannerul de cookie-uri: nu urmărim comportamentul pe site
        }
        if (! self::active() || ! WC()->cart || WC()->cart->is_empty() || ! is_email($email)) {
            return;
        }
        $hash = md5(strtolower($email).'|'.WC()->cart->get_cart_hash());
        if (WC()->session && WC()->session->get('vitim_sc') === $hash) {
            return; // același coș, același email: o singură dată
        }
        if (WC()->session) {
            WC()->session->set('vitim_sc', $hash);
        }
        self::push([
            'id' => 'checkout_'.substr($hash, 0, 24),
            'type' => 'started_checkout',
            'email' => $email,
            'first_name' => $first ?: null,
            'last_name' => $last ?: null,
            'phone' => $phone ?: null,
            'contact_token' => self::cookie(),
            'value' => (float) WC()->cart->get_total('edit'),
            'occurred_at' => time(),
            'data' => ['currency' => get_woocommerce_currency(), 'items' => self::cartItems(), 'checkout_url' => self::cartLink()],
        ]);
        self::schedule(60);
    }

    public static function addedToCart($key, $productId, $qty, $variationId)
    {
        if (! Vitim_Connector::consented('marketing')) {
            return; // fără acord de marketing în bannerul de cookie-uri: nu urmărim comportamentul pe site
        }
        $who = self::identity();
        if (! $who || ! self::active()) {
            return;
        }
        $product = wc_get_product($variationId ?: $productId);
        self::push($who + [
            'id' => 'cart_'.md5(wp_json_encode($who).'|'.$key.'|'.floor(time() / 600)),
            'type' => 'added_to_cart',
            'value' => $product ? (float) $product->get_price() * max(1, (int) $qty) : null,
            'occurred_at' => time(),
            'data' => ['currency' => get_woocommerce_currency(), 'product' => $product ? $product->get_name() : null, 'product_id' => (string) $productId,
                'items' => self::cartItems(), 'checkout_url' => self::cartLink()],
        ]);
        self::schedule(120);
    }

    public static function viewedProduct()
    {
        if (! Vitim_Connector::consented('marketing')) {
            return; // fără acord de marketing în bannerul de cookie-uri: nu urmărim comportamentul pe site
        }
        if (! self::active() || ! function_exists('is_product') || ! is_product()) {
            return;
        }
        $who = self::identity();
        $product = wc_get_product(get_the_ID());
        if (! $who || ! $product) {
            return;
        }
        $id = 'view_'.md5(wp_json_encode($who).'|'.$product->get_id().'|'.floor(time() / 3600)); // cel mult o dată pe oră per produs
        self::push($who + [
            'id' => $id,
            'type' => 'viewed_product',
            'value' => (float) $product->get_price(),
            'occurred_at' => time(),
            'data' => ['product' => $product->get_name(), 'product_id' => (string) $product->get_id(), 'url' => get_permalink($product->get_id()),
                'image' => self::image($product), 'price' => (string) $product->get_price(), 'currency' => get_woocommerce_currency()],
        ]);
        self::schedule(300);
    }

    // ---------- coșul salvat ----------

    /** Link care reface coșul (pentru emailul de coș abandonat), valabil 14 zile. */
    private static function cartLink()
    {
        if (! WC()->cart) {
            return null;
        }
        $token = WC()->session ? WC()->session->get('vitim_cart_token') : null;
        if (! $token) {
            $token = wp_generate_password(24, false, false);
            if (WC()->session) {
                WC()->session->set('vitim_cart_token', $token);
            }
        }
        $lines = [];
        foreach (WC()->cart->get_cart() as $line) {
            $lines[] = ['p' => (int) $line['product_id'], 'v' => (int) $line['variation_id'], 'q' => (int) $line['quantity'], 'a' => isset($line['variation']) ? (array) $line['variation'] : []];
        }
        set_transient(self::CART.$token, $lines, 14 * DAY_IN_SECONDS);

        return add_query_arg('vitim_cart', $token, wc_get_checkout_url());
    }

    public static function restoreCart()
    {
        if (empty($_GET['vitim_cart']) || ! self::active() || is_admin()) {
            return;
        }
        $token = preg_replace('/[^A-Za-z0-9]/', '', (string) wp_unslash($_GET['vitim_cart']));
        $lines = $token ? get_transient(self::CART.$token) : false;
        if (is_array($lines) && WC()->cart) {
            WC()->cart->empty_cart();
            foreach ($lines as $l) {
                WC()->cart->add_to_cart((int) $l['p'], max(1, (int) $l['q']), (int) $l['v'], (array) $l['a']);
            }
        }
        wp_safe_redirect(remove_query_arg('vitim_cart'));
        exit;
    }

    // ---------- acordul de marketing la comandă ----------

    private static function consentText()
    {
        return 'Vreau să primesc pe email oferte și noutăți de la '.get_bloginfo('name').'.';
    }

    public static function consentField()
    {
        if (! self::settings()['consent']) {
            return;
        }
        echo '<p class="form-row vitim-consent"><label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">'
            .'<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="vitim_marketing" value="1"> '
            .'<span>'.esc_html(self::consentText()).'</span></label></p>';
    }

    /** Aceeași bifă în checkout-ul pe blocuri (câmp suplimentar WooCommerce 8.9+). */
    public static function blockConsentField()
    {
        if (self::settings()['consent'] && function_exists('woocommerce_register_additional_checkout_field')) {
            woocommerce_register_additional_checkout_field([
                'id' => 'vitim/marketing',
                'label' => self::consentText(),
                'optionalLabel' => self::consentText(),
                'location' => 'order',
                'type' => 'checkbox',
            ]);
        }
    }

    public static function saveConsent($order, $data)
    {
        if (self::settings()['consent'] && ! empty($_POST['vitim_marketing'])) { // phpcs:ignore WordPress.Security.NonceVerification -- formularul de comandă WooCommerce își verifică singur nonce-ul
            $order->update_meta_data('_vitim_marketing', 'yes');
        }
    }

    // ---------- catalog ----------

    public static function productChanged($postId)
    {
        if (wp_is_post_revision($postId) || get_post_status($postId) === 'auto-draft') {
            return;
        }
        $ids = (array) get_option(self::PRODUCTS, []);
        $ids[(int) $postId] = get_post_status($postId) === 'publish' ? 1 : 0;
        update_option(self::PRODUCTS, $ids, false);
        self::schedule(60);
    }

    public static function productDeleted($postId)
    {
        if (get_post_type($postId) !== 'product') {
            return;
        }
        $ids = (array) get_option(self::PRODUCTS, []);
        $ids[(int) $postId] = 0;
        update_option(self::PRODUCTS, $ids, false);
        self::schedule(60);
    }

    private static function productPayload($product)
    {
        $terms = get_the_terms($product->get_id(), 'product_cat');

        return [
            'id' => (string) $product->get_id(),
            'name' => $product->get_name(),
            'price' => $product->get_price() !== '' ? (float) $product->get_price() : null,
            'currency' => get_woocommerce_currency(),
            'url' => get_permalink($product->get_id()),
            'image' => self::image($product),
            'categories' => is_array($terms) ? array_values(array_map(function ($t) { return $t->name; }, $terms)) : [],
            'in_stock' => $product->is_in_stock(),
        ];
    }

    /**
     * La fiecare rulare orară: catalogul complet (pe pagini) și comenzile din ultimele 12 luni, o singură dată,
     * ca segmentele „a cumpărat”, „a cheltuit” să funcționeze din prima zi. Istoricul nu pornește automatizări.
     */
    public static function sync()
    {
        if (! self::active()) {
            return;
        }
        $state = wp_parse_args((array) get_option(self::SYNC, []), ['products_page' => 1, 'products_done' => 0, 'orders_page' => 1, 'orders_done' => 0, 'refreshed' => 0]);
        if ($state['products_done'] && $state['refreshed'] < time() - 7 * DAY_IN_SECONDS) {
            $state['products_done'] = 0; // o dată pe săptămână, catalogul se retrimite complet (prețuri, stoc)
            $state['products_page'] = 1;
        }
        for ($i = 0; $i < 5 && ! $state['products_done']; $i++) {
            $products = wc_get_products(['status' => 'publish', 'limit' => 100, 'page' => (int) $state['products_page'], 'orderby' => 'ID', 'order' => 'ASC']);
            if (! $products) {
                $state['products_done'] = 1;
                $state['refreshed'] = time();
                break;
            }
            $result = Vitim_Connector::post('products', ['products' => array_map([__CLASS__, 'productPayload'], $products)]);
            if (! $result['ok']) {
                break;
            }
            $state['products_page']++;
        }
        for ($i = 0; $i < 3 && ! $state['orders_done']; $i++) {
            $orders = wc_get_orders(['limit' => 100, 'page' => (int) $state['orders_page'], 'orderby' => 'ID', 'order' => 'ASC', 'type' => 'shop_order',
                'status' => ['wc-processing', 'wc-completed'], 'date_created' => '>'.(time() - YEAR_IN_SECONDS)]);
            if (! $orders) {
                $state['orders_done'] = 1;
                break;
            }
            $events = array_values(array_filter(array_map(function ($o) { return self::orderEvent($o, true); }, $orders)));
            if ($events && ! Vitim_Connector::post('events', ['events' => $events])['ok']) {
                break;
            }
            $state['orders_page']++;
        }
        update_option(self::SYNC, $state, false);
        self::flush();
    }

    /** Trimite evenimentele din coadă și produsele modificate. */
    public static function flush()
    {
        if (! self::active()) {
            return;
        }
        $queue = get_option(self::QUEUE, []);
        if (is_array($queue) && $queue) {
            $batch = array_slice($queue, 0, 100);
            if (Vitim_Connector::post('events', ['events' => array_values($batch)])['ok']) {
                update_option(self::QUEUE, array_slice($queue, count($batch)), false);
                if (count($queue) > count($batch)) {
                    self::schedule(30);
                }
            }
        }
        $ids = (array) get_option(self::PRODUCTS, []);
        if ($ids) {
            $chunk = array_slice($ids, 0, 100, true);
            $payload = [];
            foreach ($chunk as $id => $live) {
                $product = $live ? wc_get_product($id) : null;
                $payload[] = $product ? self::productPayload($product) : ['id' => (string) $id, 'deleted' => true];
            }
            if (Vitim_Connector::post('products', ['products' => $payload])['ok']) {
                update_option(self::PRODUCTS, array_diff_key((array) get_option(self::PRODUCTS, []), $chunk), false);
            }
        }
    }

    /** @return array{events: int, products: int} */
    public static function pending()
    {
        $q = get_option(self::QUEUE, []);
        $p = get_option(self::PRODUCTS, []);

        return ['events' => is_array($q) ? count($q) : 0, 'products' => is_array($p) ? count($p) : 0];
    }

    // ---------- utilitare ----------

    private static function push(array $event)
    {
        $queue = get_option(self::QUEUE, []);
        $queue = is_array($queue) ? $queue : [];
        $queue[] = array_filter($event, function ($v) { return $v !== null && $v !== ''; });
        update_option(self::QUEUE, array_slice($queue, -self::MAX_QUEUE), false);
    }

    private static function schedule($delay)
    {
        if (! wp_next_scheduled(self::HOOK)) {
            wp_schedule_single_event(time() + (int) $delay, self::HOOK);
        }
    }

    /** Vizitatorul recunoscut: din cookie-ul VITIM (venit dintr-un email / abonat), cont WordPress sau emailul din coș. */
    private static function identity()
    {
        $token = self::cookie();
        if ($token) {
            return ['contact_token' => $token];
        }
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if ($user && is_email($user->user_email)) {
                return ['email' => $user->user_email, 'first_name' => $user->first_name ?: null];
            }
        }
        if (function_exists('WC') && WC()->customer && is_email(WC()->customer->get_billing_email())) {
            return ['email' => WC()->customer->get_billing_email()];
        }

        return null;
    }

    private static function cookie()
    {
        $v = isset($_COOKIE['vitim_ct']) ? (string) wp_unslash($_COOKIE['vitim_ct']) : '';

        return preg_match('/^\d+\.\d+\.[a-f0-9]{16}$/', $v) ? $v : null;
    }

    private static function cartItems()
    {
        $items = [];
        foreach (WC()->cart->get_cart() as $line) {
            $product = isset($line['data']) ? $line['data'] : null;
            $qty = max(1, (int) $line['quantity']);
            $items[] = self::item($product, $product ? $product->get_name() : '', $qty, $product ? (float) $product->get_price() : null);
        }

        return array_slice($items, 0, 50);
    }

    private static function item($product, $name, $qty, $price)
    {
        return array_filter([
            'name' => $name,
            'product_id' => $product ? (string) ($product->get_parent_id() ?: $product->get_id()) : null,
            'qty' => $qty,
            'price' => $price !== null ? round($price, 2) : null,
            'url' => $product ? get_permalink($product->get_parent_id() ?: $product->get_id()) : null,
            'image' => $product ? self::image($product) : null,
        ], function ($v) { return $v !== null && $v !== ''; });
    }

    private static function image($product)
    {
        $id = $product->get_image_id();
        $url = $id ? wp_get_attachment_image_url($id, 'woocommerce_thumbnail') : '';

        return $url ?: null;
    }
}
