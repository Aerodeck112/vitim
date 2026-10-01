<?php
/**
 * PODREG Faza 0 – configurare.
 *
 * Singurul fișier care trebuie editat după instalare.
 * Câmpurile goale ('') dezactivează automat funcția care depinde de ele.
 */

defined('ABSPATH') || exit;

return [

    // ---------------------------------------------------------------
    // Date de contact (folosite în bara de CTA, schema și footer)
    // ---------------------------------------------------------------
    'telefon'          => '+40752675675',
    'telefon_afisat'   => '0752 675 675',
    'telefon_fix'      => '+40265532335',
    'whatsapp'         => '40752675675',          // fără + și fără spații
    'email'            => 'office@podreg.ro',
    'url_oferta'       => '/configurator-cabane-lemn/',
    'url_contact'      => '/contact/',

    // ---------------------------------------------------------------
    // Date legale ale firmei (Legea 365/2002). DE COMPLETAT.
    // Cât timp 'denumire' și 'cui' sunt goale, linia legală din footer
    // și pagina de politică de confidențialitate NU se afișează.
    // ---------------------------------------------------------------
    'firma' => [
        'denumire'  => '',   // ex: 'PODREG SRL'
        'cui'       => '',   // ex: 'RO12345678'
        'reg_com'   => '',   // ex: 'J26/123/1995'
        'adresa'    => 'Răstolița nr. 345/B, jud. Mureș',
        'localitate'=> 'Răstolița',
        'judet'     => 'Mureș',
        'cod_postal'=> '',
    ],

    // ---------------------------------------------------------------
    // Profiluri sociale REALE. Link-urile ThemeREX din temă sunt
    // înlocuite cu acestea; rețelele lăsate goale dispar din pagină.
    // ---------------------------------------------------------------
    'social' => [
        'facebook'  => '',
        'instagram' => '',
        'x'         => '',
        'dribbble'  => '',   // nu se folosește; rămâne gol ca iconița să dispară
        'youtube'   => '',
        'google'    => '',   // link Google Business Profile (recenzii)
    ],

    // ---------------------------------------------------------------
    // Google Tag Manager (ex: 'GTM-ABC1234'). Gol = fără tracking,
    // fără banner de cookies.
    // ---------------------------------------------------------------
    'gtm_id' => '',

    // ---------------------------------------------------------------
    // Pagini demo ThemeREX: răspund 410 (Gone) și ies din sitemap.
    // ---------------------------------------------------------------
    'pagini_demo' => ['service-plus', 'our-clients', '404-page', 'shop'],

    // ---------------------------------------------------------------
    // Pagini de proiect: copiii acestor pagini + slug-urile din listă.
    // Pe ele apare blocul „Vreau o construcție ca …” și bara sticky
    // cu numele modelului.
    // ---------------------------------------------------------------
    'categorii_proiecte' => [
        'cabane-case-lemn'      => 'Cabane și case din lemn',
        'casute-de-lemn'        => 'Cabane sub 40 m²',
        'scari-interioare-lemn' => 'Scări interioare',
        'terase-foisoare'       => 'Terase și foișoare',
    ],
    'proiecte_fara_parinte' => [
        'scari-interioare-de-lemn-model-rastolita'      => 'scari-interioare-lemn',
        'scari-interioare-de-lemn-model-reghin'         => 'scari-interioare-lemn',
        'scari-interioare-de-lemn-model-reghin-barnutiu'=> 'scari-interioare-lemn',
        'scara-gornesti'                                => 'scari-interioare-lemn',
        'scara-si-riflaje-cluj'                         => 'scari-interioare-lemn',
        'foisor-gratar-poiana-marului'                  => 'terase-foisoare',
        'foisor-gradina-medias'                         => 'terase-foisoare',
        'foisor-podreg-model-salard'                    => 'terase-foisoare',
        'foisor-podreg-solovastru'                      => 'terase-foisoare',
        'terasa-budiu'                                  => 'terase-foisoare',
        'terasa-zefirului'                              => 'terase-foisoare',
        'carport-sambata-de-sus'                        => 'terase-foisoare',
        'tiny-house-23'                                 => 'casute-de-lemn',
        'tiny-house-caransebes'                         => 'casute-de-lemn',
        'cabana-comandau'                               => 'cabane-case-lemn',
    ],

    // ---------------------------------------------------------------
    // Module (true = activ). Dacă ceva arată ciudat după instalare,
    // dezactivează modulul respectiv și anunță echipa VITIM.
    // ---------------------------------------------------------------
    'module' => [
        'securitate'  => true,
        'seo'         => true,   // se oprește singur dacă instalați Yoast / Rank Math
        'curatenie'   => true,   // elimină textele și linkurile rămase din template
        'conversie'   => true,   // bară sticky, CTA pe proiecte
        'performanta' => true,   // nu mai încarcă WooCommerce/wishlist pe paginile fără magazin
        'legal'       => true,
    ],
];
