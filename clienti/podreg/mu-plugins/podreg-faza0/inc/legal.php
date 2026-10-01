<?php
/**
 * Legal: politica de confidențialitate PODREG la /politica-de-confidentialitate/
 * (adresa la care trimit deja formularele), redirect de la politica ThemeREX
 * și linia cu datele firmei în footer.
 */

defined('ABSPATH') || exit;

const PODREG_SLUG_POLITICA = 'politica-de-confidentialitate';

// /privacy-policy/ (textul ThemeREX) → politica PODREG.
add_action('template_redirect', function () {
    if (is_page('privacy-policy') && !current_user_can('edit_pages')) {
        wp_safe_redirect(home_url('/' . PODREG_SLUG_POLITICA . '/'), 301);
        exit;
    }
}, 1);

// Pagina virtuală. Dacă cineva creează în admin o pagină reală cu același slug, aceea are prioritate.
add_action('template_redirect', function () {
    if (!is_404() || podreg_e_engleza()) {
        return;
    }
    $cale = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $baza = trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');
    if ($baza !== '' && strpos($cale, $baza . '/') === 0) {
        $cale = substr($cale, strlen($baza) + 1);
    }
    if ($cale !== PODREG_SLUG_POLITICA) {
        return;
    }

    global $wp_query;
    $wp_query->is_404 = false;
    status_header(200);

    add_filter('pre_get_document_title', function () {
        return 'Politica de confidențialitate | PODREG';
    }, 100);
    add_filter('wp_robots', function ($r) {
        unset($r['noindex']);
        return $r;
    }, 100);
    add_filter('body_class', function ($c) {
        return array_diff($c, ['error404']);
    });

    get_header();
    echo '<div class="content_wrap podreg-politica" style="max-width:820px;margin:48px auto;padding:0 16px;line-height:1.65">';
    require PODREG_F0_DIR . '/inc/politica-text.php';
    echo '</div>';
    get_footer();
    exit;
}, 9);

// Linia legală din footer.
add_filter('podreg_f0_html', function ($html) {
    $poz = strpos($html, '<footer class="footer_wrap');
    $sf = $poz === false ? false : strpos($html, '</footer>', $poz);
    if ($sf === false) {
        return $html;
    }
    $en = podreg_e_engleza();
    $parti = array_filter([
        esc_html((string) podreg_cfg('firma.denumire')),
        podreg_cfg('firma.cui') ? 'CUI ' . esc_html((string) podreg_cfg('firma.cui')) : '',
        esc_html((string) podreg_cfg('firma.reg_com')),
        esc_html((string) podreg_cfg('firma.adresa')),
        '<a href="' . esc_url(home_url('/' . PODREG_SLUG_POLITICA . '/')) . '">'
            . ($en ? 'Privacy policy' : 'Politica de confidențialitate') . '</a>',
        '<a href="https://anpc.ro/" target="_blank" rel="noopener">ANPC</a>',
    ]);
    $linie = '<div class="podreg-legal" style="max-width:1170px;margin:0 auto;padding:16px;font-size:13px;opacity:.75;text-align:center">'
        . implode(' · ', $parti) . '</div>';
    return substr($html, 0, $sf) . $linie . substr($html, $sf);
}, 30);
