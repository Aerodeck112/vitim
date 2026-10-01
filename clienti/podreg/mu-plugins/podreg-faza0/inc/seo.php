<?php
/**
 * SEO tehnic: titluri, meta description, Open Graph, schema JSON-LD,
 * robots, sitemap curat și 410 pentru paginile demo.
 *
 * Se oprește automat dacă e activ un plugin SEO dedicat.
 */

defined('ABSPATH') || exit;

add_action('plugins_loaded', function () {
    if (defined('WPSEO_VERSION') || class_exists('RankMath') || defined('SEOPRESS_VERSION') || defined('AIOSEO_VERSION')) {
        return;
    }
    podreg_seo_init();
});

/**
 * Titluri și descrieri scrise manual pentru paginile principale (cheie = calea paginii).
 */
function podreg_seo_harta(): array
{
    return apply_filters('podreg_f0_seo_harta', [
        '' => [
            'Case și cabane din lemn masiv la comandă | PODREG',
            'Case, cabane, tiny house, foișoare și scări din lemn masiv, produse în atelierul propriu din Mureș. Peste 30 de ani de experiență. Cere o estimare.',
        ],
        'cabane-case-lemn' => [
            'Case și cabane din lemn masiv – proiecte realizate | PODREG',
            'Case și cabane din lemn masiv construite de PODREG în România și Franța. Vezi proiectele, soluțiile constructive și cere o ofertă pentru casa ta.',
        ],
        'casute-de-lemn' => [
            'Cabane sub 40 m² și tiny house din lemn | PODREG',
            'Cabane mici, tiny house și căsuțe de grădină din lemn masiv, pe fundație sau pe piloni. Vezi modelele PODREG și cere o ofertă.',
        ],
        'scari-interioare-lemn' => [
            'Scări interioare din lemn masiv la comandă | PODREG',
            'Scări interioare din stejar, frasin, cireș, larice sau rășinoase, produse integral în atelierul PODREG. Vezi modelele și cere o ofertă.',
        ],
        'terase-foisoare' => [
            'Terase și foișoare din lemn masiv | PODREG',
            'Terase, foișoare și carporturi din lemn masiv, cu stâlpi, corni și lambriu produse în atelierul propriu. Vezi lucrările PODREG.',
        ],
        'configurator-cabane-lemn' => [
            'Configurator cabane din lemn – estimare de cost | PODREG',
            'Alege suprafața cabanei și primește pe email o estimare preliminară de cost. Un consultant PODREG analizează apoi proiectul și terenul.',
        ],
        'contact' => [
            'Contact – cere o ofertă | PODREG',
            'Sună la 0752 675 675 sau scrie-ne pentru o ofertă la case, cabane, foișoare sau scări din lemn. Sediul și atelierul: Răstolița, jud. Mureș.',
        ],
        'servicii' => [
            'Servicii: consultanță, proiectare, execuție | PODREG',
            'De la documentare și ofertare la proiectare, producție, montaj și întreținere: serviciile complete PODREG pentru construcții din lemn.',
        ],
        'proces' => [
            'Cum lucrăm – procesul de ofertare | PODREG',
            'Cum estimăm corect costul unei construcții din lemn: estimare preliminară gratuită, apoi studiu tehnico-financiar detaliat. Pașii PODREG.',
        ],
        'despre-noi' => [
            'Despre noi – peste 30 de ani în construcții din lemn | PODREG',
            'PODREG construiește de peste 30 de ani case, cabane, foișoare și scări din lemn masiv, în atelierul propriu din Răstolița, județul Mureș.',
        ],
    ]);
}

/**
 * [titlu, descriere] pentru cererea curentă (sau null).
 */
function podreg_seo_curent(): ?array
{
    static $rez = false;
    if ($rez !== false) {
        return $rez;
    }
    $rez = null;
    $harta = podreg_seo_harta();

    if (is_front_page()) {
        return $rez = $harta[''] ?? null;
    }
    if (!is_singular()) {
        return $rez;
    }
    $post = get_queried_object();
    if (!$post instanceof WP_Post) {
        return $rez;
    }
    $cale = $post->post_type === 'page' ? get_page_uri($post) : '';
    if ($cale !== '' && isset($harta[$cale])) {
        return $rez = $harta[$cale];
    }

    $titlu = wp_strip_all_tags(get_the_title($post));
    $proiect = podreg_proiect_curent();
    $titlu_seo = $titlu . ' | PODREG';
    if ($proiect) {
        $categorie = podreg_cfg('categorii_proiecte.' . $proiect['categorie'], '');
        $lung = $titlu . ' | ' . $categorie . ' | PODREG';
        if ($categorie !== '' && mb_strlen($lung) <= 65) {
            $titlu_seo = $lung;
        }
    }
    return $rez = [$titlu_seo, podreg_seo_descriere_auto($post, $titlu)];
}

/**
 * Descriere generată din conținutul paginii (~155 de caractere, tăiată la cuvânt).
 */
function podreg_seo_descriere_auto(WP_Post $post, string $titlu): string
{
    $text = $post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content;
    $text = wp_strip_all_tags(strip_shortcodes($text));
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    // Primul titlu din pagină repetă de obicei numele proiectului.
    $text = preg_replace('/^(Informații utile\s+)?' . preg_quote($titlu, '/') . '\s*/iu', '', $text);

    if (mb_strlen($text) < 60) {
        return $titlu . ': proiect PODREG din lemn masiv. Vezi fotografiile și cere o ofertă pentru o construcție similară.';
    }
    if (mb_strlen($text) > 155) {
        $text = mb_substr($text, 0, 155);
        $text = preg_replace('/\s+\S*$/u', '', $text) . '…';
    }
    return $text;
}

/**
 * Imaginea reprezentativă: imaginea evidențiată sau prima imagine din conținut.
 */
function podreg_seo_imagine(): string
{
    if (is_singular()) {
        $id = get_queried_object_id();
        if (has_post_thumbnail($id)) {
            $src = wp_get_attachment_image_src(get_post_thumbnail_id($id), 'large');
            if ($src) {
                return $src[0];
            }
        }
        $continut = (string) get_post_field('post_content', $id);
        if (preg_match('#<img[^>]+src=["\']([^"\']+/wp-content/uploads/[^"\']+)["\']#i', $continut, $m)) {
            return $m[1];
        }
    }
    $logo = get_site_icon_url(512);
    return $logo ?: '';
}

function podreg_seo_init(): void
{
    // ---------- Titlu ----------
    add_filter('pre_get_document_title', function ($titlu) {
        $date = podreg_seo_curent();
        return $date ? $date[0] : $titlu;
    }, 99);
    add_filter('document_title_parts', function ($parti) {
        $parti['site'] = 'PODREG';
        unset($parti['tagline']);
        return $parti;
    }, 99);

    // ---------- Meta description, Open Graph, schema ----------
    add_action('wp_head', function () {
        if (!podreg_e_frontend()) {
            return;
        }
        $date = podreg_seo_curent();
        echo "<!--podreg-og-->\n";
        $url = is_singular() ? (string) wp_get_canonical_url() : home_url(add_query_arg([]));
        if (is_front_page()) {
            $url = home_url('/');
        }

        if ($date) {
            printf("<meta name=\"description\" content=\"%s\" />\n", esc_attr($date[1]));
            printf("<meta property=\"og:title\" content=\"%s\" />\n", esc_attr($date[0]));
            printf("<meta property=\"og:description\" content=\"%s\" />\n", esc_attr($date[1]));
        }
        printf("<meta property=\"og:type\" content=\"%s\" />\n", is_front_page() ? 'website' : 'article');
        printf("<meta property=\"og:site_name\" content=\"PODREG\" />\n");
        printf("<meta property=\"og:locale\" content=\"%s\" />\n", podreg_e_engleza() ? 'en_GB' : 'ro_RO');
        if ($url) {
            printf("<meta property=\"og:url\" content=\"%s\" />\n", esc_url($url));
        }
        $img = podreg_seo_imagine();
        if ($img) {
            printf("<meta property=\"og:image\" content=\"%s\" />\n", esc_url($img));
            echo "<meta name=\"twitter:card\" content=\"summary_large_image\" />\n";
        }

        foreach (podreg_seo_schema() as $bloc) {
            echo '<script type="application/ld+json">'
                . wp_json_encode($bloc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                . "</script>\n";
        }
        echo "<!--/podreg-og-->\n";
    }, 1);

    // Tema (trx_addons) scrie propriile og:* – le scoatem ca să nu existe duplicate.
    add_filter('podreg_f0_html', function ($html) {
        $start = strpos($html, '<!--podreg-og-->');
        $stop = strpos($html, '<!--/podreg-og-->');
        if ($start === false || $stop === false || $stop < $start) {
            return $html;
        }
        $curata = function ($bucata) {
            return preg_replace('#<meta\s+(property|name)=["\'](og:[a-z_:]+|twitter:card)["\'][^>]*>\s*#i', '', $bucata);
        };
        return $curata(substr($html, 0, $start))
            . substr($html, $start, $stop - $start)
            . $curata(substr($html, $stop));
    }, 20);

    // ---------- Robots ----------
    add_filter('wp_robots', function ($robots) {
        $privat = is_search()
            || is_attachment()
            || is_singular('cpt_layouts')
            || is_page(array_merge(podreg_cfg('pagini_demo', []), ['privacy-policy']));
        if ($privat) {
            $robots['noindex'] = true;
            $robots['follow'] = true;
            unset($robots['max-image-preview']);
        }
        return $robots;
    }, 99);

    // ---------- Sitemap ----------
    add_filter('wp_sitemaps_post_types', function ($tipuri) {
        unset($tipuri['cpt_layouts'], $tipuri['attachment']);
        return $tipuri;
    });
    add_filter('wp_sitemaps_posts_query_args', function ($args, $tip) {
        if ($tip === 'page') {
            $exclus = podreg_iduri_pagini(array_merge(podreg_cfg('pagini_demo', []), ['privacy-policy']));
            $args['post__not_in'] = array_merge($args['post__not_in'] ?? [], $exclus);
        }
        return $args;
    }, 10, 2);
    add_filter('wp_sitemaps_taxonomies', function ($tax) {
        // Taxonomiile WooCommerce/temă nu au conținut public util.
        foreach (array_keys($tax) as $nume) {
            if ($nume !== 'category') {
                unset($tax[$nume]);
            }
        }
        return $tax;
    });

    // ---------- Pagini demo: 410 Gone ----------
    add_action('template_redirect', function () {
        $demo = podreg_cfg('pagini_demo', []);
        $e_demo = $demo && is_page($demo);
        if (!$e_demo && in_array('shop', $demo, true) && function_exists('is_shop') && is_shop()) {
            $e_demo = true;
        }
        if (!$e_demo && is_singular('cpt_layouts') && !current_user_can('edit_posts')) {
            $e_demo = true;
        }
        if (!$e_demo || current_user_can('edit_pages')) {
            return;
        }
        global $wp_query;
        $wp_query->set_404();
        status_header(410);
        nocache_headers();
    }, 2);
}

/**
 * Blocuri JSON-LD: firma (pe toate paginile) și breadcrumb (pe proiecte și categorii).
 */
function podreg_seo_schema(): array
{
    $firma = [
        '@context'  => 'https://schema.org',
        '@type'     => 'HomeAndConstructionBusiness',
        '@id'       => home_url('/#firma'),
        'name'      => 'PODREG',
        'url'       => home_url('/'),
        'telephone' => array_values(array_filter([podreg_cfg('telefon'), podreg_cfg('telefon_fix')])),
        'email'     => podreg_cfg('email'),
        'address'   => array_filter([
            '@type'           => 'PostalAddress',
            'streetAddress'   => 'Nr. 345/B',
            'addressLocality' => podreg_cfg('firma.localitate'),
            'addressRegion'   => podreg_cfg('firma.judet'),
            'postalCode'      => podreg_cfg('firma.cod_postal'),
            'addressCountry'  => 'RO',
        ]),
        'areaServed' => [
            ['@type' => 'Country', 'name' => 'România'],
            ['@type' => 'Country', 'name' => 'Franța'],
        ],
        'knowsAbout' => ['case din lemn masiv', 'cabane din lemn', 'tiny house', 'foișoare din lemn', 'terase din lemn', 'scări interioare din lemn'],
    ];
    $logo = get_site_icon_url(512);
    if ($logo) {
        $firma['logo'] = $logo;
        $firma['image'] = $logo;
    }
    if (podreg_cfg('firma.denumire')) {
        $firma['legalName'] = podreg_cfg('firma.denumire');
    }
    if (podreg_cfg('firma.cui')) {
        $firma['vatID'] = podreg_cfg('firma.cui');
    }
    $social = array_values(array_filter((array) podreg_cfg('social', [])));
    if ($social) {
        $firma['sameAs'] = $social;
    }

    $blocuri = [$firma];

    // Breadcrumb
    $pasi = [['Acasă', home_url('/')]];
    $proiect = podreg_proiect_curent();
    $categorii = podreg_cfg('categorii_proiecte', []);
    if ($proiect) {
        $cat = get_page_by_path($proiect['categorie']);
        if ($cat) {
            $pasi[] = [$categorii[$proiect['categorie']] ?? get_the_title($cat), get_permalink($cat)];
        }
        $pasi[] = [$proiect['titlu'], get_permalink($proiect['id'])];
    } elseif (is_page(array_keys($categorii))) {
        $pasi[] = [wp_strip_all_tags(get_the_title()), get_permalink()];
    }
    if (count($pasi) > 1) {
        $elemente = [];
        foreach ($pasi as $i => [$nume, $link]) {
            $elemente[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => wp_strip_all_tags($nume), 'item' => $link];
        }
        $blocuri[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $elemente];
    }

    return $blocuri;
}
