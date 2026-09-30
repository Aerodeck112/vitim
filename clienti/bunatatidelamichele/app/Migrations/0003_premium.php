<?php
declare(strict_types=1);

use App\Core\DB;
use App\Core\Settings;
use App\Core\Uploader;

/**
 * v1.1.0 – design premium: imaginile produselor decupate (fundal transparent, fără filigran),
 * vitrina Saka & Pareo pe prima pagină și textele noi. Nu atinge ce a fost modificat deja din panou.
 */
return function (): void {
    $img = function (string $name, string $alt): string {
        $existing = DB::val('SELECT path FROM media WHERE original_name = ?', [$name . '.webp']);
        if ($existing) {
            return (string)$existing;
        }
        $file = APP_PATH . '/Data/images/' . $name . '.webp';
        $m = is_file($file) ? Uploader::fromFile($file, $alt, $name . '.webp') : null;
        return $m ? (string)$m['path'] : '';
    };
    $orig = fn(string $name) => (string)DB::val('SELECT path FROM media WHERE original_name = ?', [$name . '.webp']);

    // produse: înlocuim imaginea principală doar dacă este încă cea originală
    $map = [
        'acs-one-coffee' => 'acs-one-coffee',
        'cafea-boabe-miscela-bar-bar-blend-1kg-prajita-la-foc-de-lemn' => 'cafea-boabe-miscela-bar-bar-blend-1kg',
        'cialda-clasic-bar-monodoza' => 'cialda-clasic-bar-monodoza',
        'grana-miscela-cremabar-1kg-prajita-la-foc-de-lemn' => 'grana-miscela-cremabar-1kg',
        'pareo-miscela-bar-bar-blend-1kg-cafea-boabe-prajita-la-foc-de-lemn' => 'pareo-miscela-bar-bar-blend-1kg',
        'saka-decaffeinato-monodoza' => 'saka-decaffeinato-monodoza',
        'saka-premium-monodoza' => 'saka-premium-monodoza',
    ];
    $cut = [];
    foreach ($map as $slug => $base) {
        $p = DB::row('SELECT id, name, images FROM products WHERE slug = ?', [$slug]);
        $cut[$base] = $img($base . '-decupat', $p['name'] ?? $base);
        if (!$p || $cut[$base] === '') {
            continue;
        }
        $imgs = json_list($p['images']);
        if (($imgs[0] ?? '') === $orig($base) || !$imgs) {
            $imgs[0] = $cut[$base];
            DB::update('products', ['images' => json_encode(array_values(array_unique($imgs))), 'updated_at' => DB::now()], 'id = :id', ['id' => $p['id']]);
        }
    }

    // categorii: produse reale în loc de iconițele desenate
    $catImg = ['cafea-boabe' => ['pareo-miscela-bar-bar-blend-1kg', 'icon-cafea-boabe'], 'pastile-de-cafea' => ['saka-premium-monodoza', 'icon-cialde'], 'espresoare-de-cafea' => ['acs-one-coffee', 'icon-monodoze']];
    foreach ($catImg as $slug => [$base, $oldIcon]) {
        $c = DB::row('SELECT id, icon, image FROM categories WHERE slug = ?', [$slug]);
        if ($c && ($c['icon'] === $orig($oldIcon) || !$c['icon']) && $cut[$base] !== '') {
            DB::update('categories', ['icon' => $cut[$base], 'image' => $c['image'] ?: $cut[$base], 'updated_at' => DB::now()], 'id = :id', ['id' => $c['id']]);
        }
    }

    // prima pagină: slide-urile noi, doar dacă sunt încă cele inițiale
    $vitrina = $img('vitrina-saka-pareo', 'Cafea Pareo și monodoze Saka – import exclusiv din Italia');
    $slides = Settings::json('home_slides');
    if (($slides[0]['title'] ?? '') === 'Cafea premium') {
        $new = json_decode((string)(require APP_PATH . '/Data/settings_defaults.php')['home_slides'], true);
        $new[0]['image'] = $vitrina;
        $new[1]['image'] = $orig('latte-si-espresso') ?: ($slides[1]['image'] ?? '');
        Settings::set('home_slides', $new);
    }
    $brands = json_decode((string)(require APP_PATH . '/Data/settings_defaults.php')['home_brands'], true);
    $brands[0]['image'] = $cut['pareo-miscela-bar-bar-blend-1kg'] ?? '';
    $brands[1]['image'] = $cut['saka-premium-monodoza'] ?? '';
    if (!Settings::json('home_brands') || !array_filter(array_column(Settings::json('home_brands'), 'image'))) {
        Settings::set('home_brands', $brands);
    }
    if ($vitrina !== '') {
        Settings::set('home_hero_image', $vitrina);
    }
    if (Settings::get('logo') === $orig('logo-bunatati-de-la-michele')) {
        Settings::set('logo', ''); // se folosesc variantele din assets (închisă / aurie), potrivite fiecărui fundal
    }
};
