<?php
/**
 * Funcții comune pentru modulele Faza 0.
 */

defined('ABSPATH') || exit;

/**
 * Citește o valoare din config.php. Acceptă chei cu punct: 'firma.cui'.
 */
function podreg_cfg(string $cheie, $implicit = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require PODREG_F0_DIR . '/config.php';
        $cfg = apply_filters('podreg_f0_config', is_array($cfg) ? $cfg : []);
    }
    $val = $cfg;
    foreach (explode('.', $cheie) as $parte) {
        if (!is_array($val) || !array_key_exists($parte, $val)) {
            return $implicit;
        }
        $val = $val[$parte];
    }
    return $val;
}

/**
 * Cerere de pagină publică (nu admin, REST, AJAX, cron, feed sau editorul Elementor).
 */
function podreg_e_frontend(): bool
{
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return false;
    }
    if (is_feed() || is_robots() || is_trackback() || is_embed()) {
        return false;
    }
    if (isset($_GET['elementor-preview']) || isset($_GET['elementor_library'])) {
        return false;
    }
    return true;
}

/**
 * Versiunea în engleză (TranslatePress servește /en/...).
 */
function podreg_e_engleza(): bool
{
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $baza = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
    $rel = '/' . ltrim(substr($uri, strlen(rtrim($baza, '/'))), '/');
    return (bool) preg_match('#^/en(/|$|\?)#', $rel);
}

/**
 * URL absolut pentru o cale din config ('/contact/' sau URL complet).
 */
function podreg_url(string $cale): string
{
    return preg_match('#^https?://#', $cale) ? $cale : home_url($cale);
}

/**
 * Dacă pagina curentă e un proiect, întoarce ['titlu' => ..., 'categorie' => slug].
 */
function podreg_proiect_curent(): ?array
{
    static $rez = false;
    if ($rez !== false) {
        return $rez;
    }
    $rez = null;
    if (!is_singular('page')) {
        return $rez;
    }
    $post = get_queried_object();
    if (!$post instanceof WP_Post) {
        return $rez;
    }
    $categorii = podreg_cfg('categorii_proiecte', []);
    $fara_parinte = podreg_cfg('proiecte_fara_parinte', []);

    $categorie = null;
    if (isset($fara_parinte[$post->post_name])) {
        $categorie = $fara_parinte[$post->post_name];
    } elseif ($post->post_parent) {
        $parinte = get_post($post->post_parent);
        if ($parinte && isset($categorii[$parinte->post_name])) {
            $categorie = $parinte->post_name;
        }
    }
    if ($categorie !== null) {
        $rez = ['titlu' => get_the_title($post), 'categorie' => $categorie, 'id' => $post->ID];
    }
    return $rez;
}

/**
 * ID-urile paginilor după slug (cu cache pe cerere).
 */
function podreg_iduri_pagini(array $sluguri): array
{
    $iduri = [];
    foreach ($sluguri as $slug) {
        $p = get_page_by_path($slug, OBJECT, 'page');
        if ($p) {
            $iduri[] = (int) $p->ID;
        }
    }
    return $iduri;
}

/**
 * Filtre aplicate pe HTML-ul final. Modulele adaugă callback-uri cu
 * add_filter('podreg_f0_html', ...). Bufferul pornește doar dacă există filtre.
 */
add_action('template_redirect', function () {
    if (!podreg_e_frontend() || !has_filter('podreg_f0_html')) {
        return;
    }
    ob_start(function ($html) {
        if (!is_string($html) || stripos($html, '<html') === false) {
            return $html;
        }
        $nou = apply_filters('podreg_f0_html', $html);
        return is_string($nou) && $nou !== '' ? $nou : $html;
    });
}, 0);
