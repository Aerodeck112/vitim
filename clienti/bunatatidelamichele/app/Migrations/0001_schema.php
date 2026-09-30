<?php
declare(strict_types=1);

use App\Core\DB;

return function (): void {
    DB::createTable('settings', [
        'skey' => 'string',
        'svalue' => 'text',
    ], [], [['skey']]);

    DB::createTable('users', [
        'id' => 'id',
        'name' => 'string',
        'email' => 'string',
        'password_hash' => 'string',
        'role' => 'short=admin',
        'totp_secret' => 'string?',
        'reset_token' => 'short?',
        'reset_expires' => 'datetime?',
        'active' => 'bool=1',
        'last_login_at' => 'datetime?',
        'last_login_ip' => 'short?',
        'created_at' => 'datetime?',
    ], [], [['email']]);

    $seo = [
        'meta_title' => 'string?',
        'meta_description' => 'text',
        'og_image' => 'string?',
        'canonical' => 'string?',
        'noindex' => 'bool=0',
    ];

    // ---------- Catalog ----------
    DB::createTable('categories', [
        'id' => 'id',
        'slug' => 'string',
        'name' => 'string',
        'h1' => 'string?',
        'intro' => 'text',
        'body' => 'text',
        'image' => 'string?',
        'icon' => 'string?',
        'faq' => 'json',
        'sort' => 'int=0',
        'published' => 'bool=1',
        'created_at' => 'datetime?',
        'updated_at' => 'datetime?',
    ] + $seo, [['published', 'sort']], [['slug']]);

    DB::createTable('products', [
        'id' => 'id',
        'slug' => 'string',
        'name' => 'string',
        'category_id' => 'int?',
        'brand' => 'string?',
        'sku' => 'short?',
        'gtin' => 'short?',
        'short_description' => 'text',
        'description' => 'text',
        'price' => 'decimal=0',
        'sale_price' => 'decimal?',
        'sale_until' => 'datetime?',
        'unit' => 'short=buc',
        'price_note' => 'string?',
        'min_qty' => 'int=1',
        'qty_step' => 'int=1',
        'weight_g' => 'int=0',
        'manage_stock' => 'bool=0',
        'stock' => 'int=0',
        'stock_status' => 'short=instock',
        'images' => 'json',
        'attributes' => 'json',
        'faq' => 'json',
        'badge' => 'short?',
        'featured' => 'bool=0',
        'sort' => 'int=0',
        'published' => 'bool=1',
        'sales_count' => 'int=0',
        'created_at' => 'datetime?',
        'updated_at' => 'datetime?',
    ] + $seo, [['category_id'], ['published', 'sort'], ['featured']], [['slug']]);

    // ---------- Coș și comenzi ----------
    DB::createTable('carts', [
        'id' => 'id',
        'token' => 'short',
        'items' => 'json',
        'coupon' => 'short?',
        'created_at' => 'datetime?',
        'updated_at' => 'datetime?',
    ], [['updated_at']], [['token']]);

    DB::createTable('orders', [
        'id' => 'id',
        'number' => 'short',
        'token' => 'short',
        'status' => 'short=noua',
        'payment_method' => 'short=ramburs',
        'payment_status' => 'short=neplatita',
        'customer_type' => 'short=pf',
        'first_name' => 'string',
        'last_name' => 'string',
        'email' => 'string',
        'phone' => 'short',
        'company' => 'string?',
        'cui' => 'short?',
        'reg_com' => 'short?',
        'billing_address' => 'string',
        'billing_city' => 'string',
        'billing_county' => 'string',
        'billing_postcode' => 'short?',
        'ship_same' => 'bool=1',
        'shipping_name' => 'string?',
        'shipping_phone' => 'short?',
        'shipping_address' => 'string?',
        'shipping_city' => 'string?',
        'shipping_county' => 'string?',
        'shipping_postcode' => 'short?',
        'customer_note' => 'text',
        'admin_note' => 'text',
        'shipping_method' => 'string?',
        'subtotal' => 'decimal=0',
        'discount' => 'decimal=0',
        'shipping_cost' => 'decimal=0',
        'payment_fee' => 'decimal=0',
        'total' => 'decimal=0',
        'coupon_code' => 'short?',
        'currency' => 'short=RON',
        'courier' => 'string?',
        'awb' => 'string?',
        'tracking_url' => 'string?',
        'bt_order_id' => 'string?',
        'bt_attempts' => 'int=0',
        'bt_status' => 'int?',
        'bt_action_code' => 'short?',
        'bt_message' => 'string?',
        'bt_approved_amount' => 'decimal=0',
        'bt_deposited_amount' => 'decimal=0',
        'bt_refunded_amount' => 'decimal=0',
        'bt_card' => 'short?',
        'paid_at' => 'datetime?',
        'stock_reduced' => 'bool=0',
        'terms_accepted_at' => 'datetime?',
        'ip' => 'short?',
        'user_agent' => 'string?',
        'utm' => 'json',
        'created_at' => 'datetime?',
        'updated_at' => 'datetime?',
    ], [['status'], ['payment_status'], ['email'], ['created_at'], ['bt_order_id']], [['number'], ['token']]);

    DB::createTable('order_items', [
        'id' => 'id',
        'order_id' => 'int',
        'product_id' => 'int?',
        'name' => 'string',
        'sku' => 'short?',
        'unit' => 'short?',
        'price' => 'decimal=0',
        'qty' => 'int=1',
        'total' => 'decimal=0',
        'image' => 'string?',
    ], [['order_id'], ['product_id']]);

    DB::createTable('order_events', [
        'id' => 'id',
        'order_id' => 'int',
        'type' => 'short=note',
        'message' => 'text',
        'data' => 'json',
        'user_id' => 'int?',
        'notify' => 'bool=0',
        'created_at' => 'datetime?',
    ], [['order_id']]);

    DB::createTable('coupons', [
        'id' => 'id',
        'code' => 'short',
        'description' => 'string?',
        'type' => 'short=percent',
        'value' => 'decimal=0',
        'min_total' => 'decimal=0',
        'free_shipping' => 'bool=0',
        'max_uses' => 'int=0',
        'used' => 'int=0',
        'starts_at' => 'datetime?',
        'expires_at' => 'datetime?',
        'active' => 'bool=1',
        'created_at' => 'datetime?',
        'updated_at' => 'datetime?',
    ], [], [['code']]);

    // ---------- Conținut ----------
    DB::createTable('pages', [
        'id' => 'id',
        'slug' => 'string',
        'title' => 'string',
        'subtitle' => 'string?',
        'body' => 'text',
        'in_footer' => 'bool=0',
        'footer_group' => 'short=info',
        'sort' => 'int=0',
        'published' => 'bool=1',
        'created_at' => 'datetime?',
        'updated_at' => 'datetime?',
    ] + $seo, [], [['slug']]);

    DB::createTable('posts', [
        'id' => 'id',
        'slug' => 'string',
        'title' => 'string',
        'excerpt' => 'text',
        'body' => 'text',
        'cover' => 'string?',
        'status' => 'short=draft',
        'published_at' => 'datetime?',
        'author_id' => 'int?',
        'faq' => 'json',
        'created_at' => 'datetime?',
        'updated_at' => 'datetime?',
    ] + $seo, [['status', 'published_at']], [['slug']]);

    DB::createTable('reviews', [
        'id' => 'id',
        'product_id' => 'int?',
        'name' => 'string',
        'city' => 'string?',
        'rating' => 'int=5',
        'text' => 'text',
        'verified' => 'bool=0',
        'published' => 'bool=0',
        'created_at' => 'datetime?',
    ], [['product_id', 'published']]);

    DB::createTable('messages', [
        'id' => 'id',
        'name' => 'string',
        'email' => 'string',
        'phone' => 'short?',
        'subject' => 'string?',
        'message' => 'text',
        'page' => 'string?',
        'ip' => 'short?',
        'user_agent' => 'string?',
        'spam_score' => 'int=0',
        'read_at' => 'datetime?',
        'replied_at' => 'datetime?',
        'created_at' => 'datetime?',
    ], [['read_at']]);

    DB::createTable('media', [
        'id' => 'id',
        'path' => 'string',
        'original_name' => 'string?',
        'mime' => 'short?',
        'width' => 'int=0',
        'height' => 'int=0',
        'size' => 'int=0',
        'alt' => 'string?',
        'variants' => 'json',
        'created_at' => 'datetime?',
    ], [['path']]);

    DB::createTable('redirects', [
        'id' => 'id',
        'from_path' => 'string',
        'to_path' => 'string',
        'code' => 'int=301',
        'hits' => 'int=0',
        'last_hit_at' => 'datetime?',
        'created_at' => 'datetime?',
    ], [], [['from_path']]);

    DB::createTable('not_found_log', [
        'id' => 'id',
        'path' => 'string',
        'referer' => 'string?',
        'hits' => 'int=1',
        'last_seen_at' => 'datetime?',
    ], [], [['path']]);

    DB::createTable('email_log', [
        'id' => 'id',
        'to_email' => 'string',
        'subject' => 'string?',
        'kind' => 'short?',
        'status' => 'short=sent',
        'error' => 'text',
        'created_at' => 'datetime?',
    ], [['created_at']]);
};
