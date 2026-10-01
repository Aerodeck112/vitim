<?php
/**
 * Curățare: elimină ce a rămas din demo-ul ThemeREX și textele interne.
 *
 * Lucrează pe HTML-ul final, pentru că textele vin din widget-uri, layout-uri
 * trx_addons și opțiunile temei. Soluția definitivă e corectarea lor din admin
 * (lista e în README); până atunci, vizitatorii nu le mai văd.
 */

defined('ABSPATH') || exit;

add_filter('podreg_f0_html', 'podreg_curatenie_html', 10);

function podreg_curatenie_html(string $html): string
{
    // 1. Link-uri sociale ThemeREX → profilurile reale sau eliminate.
    $social = (array) podreg_cfg('social', []);
    $harta = [
        'facebook.com/ThemeRexStudio'   => $social['facebook'] ?? '',
        'instagram.com/themerex_net'    => $social['instagram'] ?? '',
        'x.com/ThemerexThemes'          => $social['x'] ?? '',
        'twitter.com/ThemerexThemes'    => $social['x'] ?? '',
        'dribbble.com/ThemeREX'         => $social['dribbble'] ?? '',
    ];
    $html = preg_replace_callback(
        '#<a\b[^>]*href=["\']https?://(?:www\.)?([^"\']+)["\'][^>]*>.*?</a>#is',
        function ($m) use ($harta) {
            foreach ($harta as $demo => $real) {
                if (stripos($m[1], $demo) === 0) {
                    if ($real === '') {
                        return '';
                    }
                    return preg_replace('#href=["\'][^"\']+["\']#i', 'href="' . esc_url($real) . '"', $m[0], 1);
                }
            }
            return $m[0];
        },
        $html
    );

    // 2. Widget-ul demo din meniul mobil („Have a Project? info@website.com …”).
    $html = preg_replace(
        '#<aside class="widget_text widget widget_custom_html">\s*<div class="textwidget custom-html-widget">\s*<div class="extra_item">\s*<h6>Have a Project\?</h6>.*?</aside>#is',
        '',
        $html
    );

    // 3. Linkuri moarte din template.
    $html = str_replace(
        [home_url('/contact-us/'), 'href="' . home_url('/shop/') . '"'],
        [home_url(podreg_cfg('url_contact', '/contact/')), 'href="' . home_url(podreg_cfg('url_oferta', '/')) . '"'],
        $html
    );

    // 4. Rutarea internă a formularului de contact nu e treaba vizitatorului.
    $html = preg_replace(
        '#(<p class="pcf-note">)[^<]*office@vitim\.ro[^<]*(</p>)#i',
        '$1Vă răspundem în cel mai scurt timp și primiți o confirmare pe email.$2',
        $html
    );

    // 5. Link-ul GDPR din formulare trimite la www.podreg.ro/politica-de-confidentialitate (404).
    $html = str_replace(
        'https://www.podreg.ro/politica-de-confidentialitate"',
        esc_url(home_url('/politica-de-confidentialitate/')) . '"',
        $html
    );

    if (podreg_e_engleza()) {
        return $html;
    }

    // 6. Titluri în engleză rămase în footer și slider.
    $html = preg_replace_callback(
        '#(<h[1-6]\b[^>]*>)\s*(Hello|Get in touch)\s*(</h[1-6]>)#i',
        function ($m) use ($social) {
            // Fără profiluri sociale, în coloana aceea rămân doar insignele ANPC.
            $ro = [
                'hello'        => 'PODREG',
                'get in touch' => array_filter($social) ? 'Urmărește-ne' : 'Protecția consumatorilor',
            ];
            return $m[1] . $ro[strtolower($m[2])] . $m[3];
        },
        $html
    );
    $html = preg_replace('#>\s*Scroll Down\s*</rs-layer>#', '>Derulează</rs-layer>', $html);
    $html = str_replace('All rights reserved.', 'Toate drepturile rezervate.', $html);

    return $html;
}
