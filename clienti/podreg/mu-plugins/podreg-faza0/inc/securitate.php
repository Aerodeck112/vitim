<?php
/**
 * Securitate: header-e HTTP, enumerarea utilizatorilor, versiuni expuse,
 * limitare de rată pe formularele PODREG.
 */

defined('ABSPATH') || exit;

// ---------- Header-e de securitate ----------
add_action('send_headers', function () {
    if (headers_sent()) {
        return;
    }
    if (is_ssl()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
});

// ---------- Utilizatori: REST, arhive de autor, sitemap, oEmbed ----------
add_filter('rest_endpoints', function ($rute) {
    if (is_user_logged_in()) {
        return $rute;
    }
    foreach (array_keys($rute) as $ruta) {
        if (preg_match('#^/wp/v2/users(/|$)#', $ruta)) {
            unset($rute[$ruta]);
        }
    }
    return $rute;
});

add_action('template_redirect', function () {
    if (is_author() || (isset($_GET['author']) && !is_admin())) {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
}, 1);

add_filter('wp_sitemaps_add_provider', function ($provider, $nume) {
    return $nume === 'users' ? false : $provider;
}, 10, 2);

add_filter('oembed_response_data', function ($date) {
    unset($date['author_name'], $date['author_url']);
    return $date;
});

// ---------- Versiuni expuse ----------
remove_action('wp_head', 'wp_generator');
add_filter('the_generator', '__return_empty_string');

add_filter('podreg_f0_html', function ($html) {
    return preg_replace('#<meta\s+name=["\']generator["\'][^>]*>\s*#i', '', $html);
});

// ?ver=<versiunea WordPress> devine un hash (cache-ul în browser rămâne corect).
$podreg_fara_versiune = function ($src) {
    $wp = get_bloginfo('version');
    if (is_string($src) && $wp && strpos($src, 'ver=' . $wp) !== false) {
        $src = str_replace('ver=' . $wp, 'ver=' . substr(md5($wp . wp_salt('nonce')), 0, 8), $src);
    }
    return $src;
};
add_filter('style_loader_src', $podreg_fara_versiune, 99);
add_filter('script_loader_src', $podreg_fara_versiune, 99);

// ---------- Limitare de rată pe formularele PODREG ----------
// Maximum 5 trimiteri la 10 minute de pe același IP, pe rutele /podreg/*.
add_filter('rest_pre_dispatch', function ($rezultat, $server, $cerere) {
    if ($rezultat !== null || !$cerere instanceof WP_REST_Request) {
        return $rezultat;
    }
    if ($cerere->get_method() !== 'POST' || strpos($cerere->get_route(), '/podreg/') !== 0) {
        return $rezultat;
    }
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    if ($ip === '') {
        return $rezultat;
    }
    $cheie = 'podreg_rl_' . md5($ip . wp_salt('nonce'));
    $numar = (int) get_transient($cheie);
    if ($numar >= (int) apply_filters('podreg_f0_limita_formulare', 5)) {
        return new WP_Error(
            'podreg_prea_multe',
            'Ați trimis deja mai multe cereri. Vă rugăm să ne sunați la ' . podreg_cfg('telefon_afisat') . '.',
            ['status' => 429]
        );
    }
    set_transient($cheie, $numar + 1, 10 * MINUTE_IN_SECONDS);
    return $rezultat;
}, 10, 3);
