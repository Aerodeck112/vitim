<?php
/**
 * Performanță fără redesign: WooCommerce, wishlist și quick view nu se mai
 * încarcă pe paginile fără magazin; emoji dezactivate; Google Fonts cu swap.
 *
 * Câștigul real vine în Faza 1 (temă nouă, fără Slider Revolution).
 */

defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function () {
    if (!podreg_e_frontend()) {
        return;
    }
    $magazin = (function_exists('is_woocommerce') && is_woocommerce())
        || (function_exists('is_cart') && is_cart())
        || (function_exists('is_checkout') && is_checkout())
        || (function_exists('is_account_page') && is_account_page());
    if ($magazin) {
        return;
    }

    $stiluri = [
        'woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-general', 'wc-blocks-style',
        'tinvwl-webfont-font', 'tinvwl-webfont', 'tinvwl',
        'slick', 'perfect-scrollbar', 'perfect-scrollbar-wpc', 'woosq-feather', 'woosq-frontend',
        'trx_addons-woocommerce', 'trx_addons-woocommerce-responsive',
        'plank-woocommerce', 'plank-woocommerce-responsive',
    ];
    $scripturi = [
        'plank-woocommerce', 'trx_addons-woocommerce',
        'tinvwl', 'woosq-frontend', 'perfect-scrollbar', 'slick',
        'wc-cart-fragments', 'wc-order-attribution', 'sourcebuster-js',
        'wc-add-to-cart-variation', 'wc-add-to-cart', 'woocommerce', 'wc-js-cookie', 'wc-jquery-blockui',
    ];
    foreach (apply_filters('podreg_f0_stiluri_scoase', $stiluri) as $h) {
        wp_dequeue_style($h);
    }
    foreach (apply_filters('podreg_f0_scripturi_scoase', $scripturi) as $h) {
        wp_dequeue_script($h);
    }
}, 999);

// Emoji (WordPress încarcă un script pe fiecare pagină doar pentru ele).
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles');

// Google Fonts: textul se vede imediat, cu fontul de sistem, până se încarcă fontul.
add_filter('style_loader_src', function ($src) {
    if (is_string($src) && strpos($src, 'fonts.googleapis.com') !== false && strpos($src, 'display=') === false) {
        $src = add_query_arg('display', 'swap', $src);
    }
    return $src;
}, 20);
