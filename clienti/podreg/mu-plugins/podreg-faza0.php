<?php
/**
 * Plugin Name: PODREG Faza 0 – reparații urgente
 * Description: Securitate, SEO tehnic, curățare template, CTA-uri și măsurare pentru podreg.ro. Configurare în podreg-faza0/config.php.
 * Version:     0.1.0
 * Author:      VITIM
 * Author URI:  https://vitim.ro
 *
 * Must-use plugin: se copiază în wp-content/mu-plugins/ împreună cu directorul podreg-faza0/.
 */

defined('ABSPATH') || exit;

define('PODREG_F0_VERSION', '0.1.0');
define('PODREG_F0_DIR', __DIR__ . '/podreg-faza0');
define('PODREG_F0_URL', content_url('mu-plugins/podreg-faza0'));

require PODREG_F0_DIR . '/inc/helpers.php';

foreach (podreg_cfg('module', []) as $modul => $activ) {
    $fisier = PODREG_F0_DIR . '/inc/' . $modul . '.php';
    if ($activ && preg_match('/^[a-z]+$/', $modul) && is_file($fisier)) {
        require $fisier;
    }
}
