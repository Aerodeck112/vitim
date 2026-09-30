<?php
declare(strict_types=1);

use App\Core\DB;
use App\Core\Settings;
use App\Core\Uploader;

/**
 * Conținutul inițial: imaginile, categoriile, produsele și paginile preluate de pe site-ul vechi,
 * plus redirecționările de la adresele WordPress (ca să nu se piardă pozițiile din Google).
 */
return function (): void {
    $now = DB::now();
    $img = function (string $name, string $alt) use ($now): string {
        $file = APP_PATH . '/Data/images/' . $name . '.webp';
        $existing = DB::val('SELECT path FROM media WHERE original_name = ?', [$name . '.webp']);
        if ($existing) {
            return (string)$existing;
        }
        $m = is_file($file) ? Uploader::fromFile($file, $alt, $name . '.webp') : null;
        return $m ? (string)$m['path'] : '';
    };

    $data = require APP_PATH . '/Data/seed_catalog.php';

    // ---------- Categorii ----------
    $catIds = [];
    foreach ($data['categories'] as $c) {
        $id = DB::val('SELECT id FROM categories WHERE slug = ?', [$c['slug']]);
        if (!$id) {
            $id = DB::insert('categories', [
                'slug' => $c['slug'], 'name' => $c['name'], 'h1' => $c['h1'], 'intro' => $c['intro'], 'body' => $c['body'],
                'icon' => $img($c['icon'], $c['name']), 'faq' => json_encode($c['faq'], JSON_UNESCAPED_UNICODE),
                'sort' => $c['sort'], 'published' => 1, 'meta_description' => '', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $catIds[$c['slug']] = (int)$id;
    }

    // ---------- Produse ----------
    foreach ($data['products'] as $p) {
        if (DB::val('SELECT id FROM products WHERE slug = ?', [$p['slug']])) {
            continue;
        }
        $path = $img($p['image'], $p['name']);
        DB::insert('products', [
            'slug' => $p['slug'],
            'name' => $p['name'],
            'category_id' => $catIds[$p['category']] ?? null,
            'brand' => $p['brand'] ?? null,
            'short_description' => $p['short'],
            'description' => $p['description'],
            'price' => $p['price'],
            'unit' => $p['unit'] ?? 'buc',
            'price_note' => $p['price_note'] ?? null,
            'min_qty' => 1,
            'qty_step' => 1,
            'weight_g' => $p['weight_g'] ?? 0,
            'manage_stock' => 0,
            'stock' => 0,
            'stock_status' => 'instock',
            'images' => json_encode($path ? [$path] : []),
            'attributes' => json_encode($p['attributes'] ?? [], JSON_UNESCAPED_UNICODE),
            'faq' => '[]',
            'featured' => $p['featured'],
            'sort' => $p['sort'],
            'published' => 1,
            'meta_title' => $p['meta_title'] ?? null,
            'meta_description' => $p['meta_description'] ?? '',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    // ---------- Pagini ----------
    foreach (require APP_PATH . '/Data/seed_pages.php' as $pg) {
        if (DB::val('SELECT id FROM pages WHERE slug = ?', [$pg['slug']])) {
            continue;
        }
        DB::insert('pages', $pg + ['published' => 1, 'created_at' => $now, 'updated_at' => $now]);
    }

    // ---------- Imagini pentru prima pagină și Despre noi ----------
    $slides = json_decode((string)Settings::get('home_slides'), true) ?: [];
    $slideImgs = [$img('ceasca-roz-boabe-cafea', 'Ceașcă de cafea înconjurată de boabe de cafea prăjite'), $img('ceasca-cafea-boabe', 'Ceașcă de cafea și boabe de cafea prăjită la foc de lemn')];
    foreach ($slides as $i => &$s) {
        if (empty($s['image']) && !empty($slideImgs[$i])) {
            $s['image'] = $slideImgs[$i];
        }
    }
    unset($s);
    Settings::set('home_slides', $slides);

    $steps = json_decode((string)Settings::get('home_process'), true) ?: [];
    $stepImgs = ['pas-1-selectia-boabelor', 'pas-2-prajire', 'pas-3-macinare', 'pas-4-ambalare'];
    foreach ($steps as $i => &$s) {
        if (empty($s['image']) && isset($stepImgs[$i])) {
            $s['image'] = $img($stepImgs[$i], $s['title']);
        }
    }
    unset($s);
    Settings::set('home_process', $steps);

    Settings::set('home_hero_image', $img('latte-si-espresso', 'Latte și espresso cu boabe de cafea și scorțișoară'));
    Settings::set('home_intro_image', $img('ceasca-espresso-scortisoara', 'Ceașcă de espresso cu scorțișoară și anason'));
    Settings::set('about_image', $img('espressor-profesional-ceasca', 'Espresso turnat dintr-un espresor profesional'));
    Settings::set('home_cat_icons', [
        'cafea-boabe' => $img('icon-cafea-boabe', 'Cafea boabe'),
        'cialde' => $img('icon-cialde', 'Cialde'),
        'monodoze' => $img('icon-monodoze', 'Monodoze'),
    ]);
    Settings::set('logo', $img('logo-bunatati-de-la-michele', 'Bunătăți de la Michele'));

    if (Settings::get('cron_key') === '') {
        Settings::set('cron_key', bin2hex(random_bytes(12)));
    }
    if (Settings::get('seo_indexnow_key') === '') {
        Settings::set('seo_indexnow_key', bin2hex(random_bytes(16)));
    }
    if (Settings::get('bt_callback_token') === '') {
        Settings::set('bt_callback_token', bin2hex(random_bytes(12)));
    }

    // ---------- Adresele vechi WordPress ----------
    $redirects = [
        '/shop' => '/produse',
        '/produse-2' => '/produse',
        '/categorie-produs/cafea' => '/produse',
        '/categorie-produs/uncategorized' => '/produse',
        '/eticheta-produs/cafea-boabe' => '/categorie/cafea-boabe',
        '/eticheta-produs/pastile-de-cafea' => '/categorie/pastile-de-cafea',
        '/eticheta-produs/espresoare-de-cafea' => '/categorie/espresoare-de-cafea',
        '/about-us-3' => '/despre-noi',
        '/about-us' => '/despre-noi',
        '/contact-us' => '/contact',
        '/home-sweets-bakery' => '/',
        '/contul-meu' => '/urmarire-comanda',
        '/contul-meu/lost-password' => '/urmarire-comanda',
        '/contul-meu/orders' => '/urmarire-comanda',
        '/my-account' => '/urmarire-comanda',
        '/wishlist' => '/produse',
        '/compare' => '/produse',
        '/checkout' => '/finalizare',
        '/cart' => '/cos',
        '/feed' => '/',
        '/termeni-si-conditii-2' => '/termeni-si-conditii',
        '/privacy-policy' => '/politica-de-confidentialitate',
        '/politica-de-confidentialitate-2' => '/politica-de-confidentialitate',
    ];
    foreach ($redirects as $from => $to) {
        if (!DB::val('SELECT id FROM redirects WHERE from_path = ?', [$from])) {
            DB::insert('redirects', ['from_path' => $from, 'to_path' => $to, 'code' => 301, 'hits' => 0, 'created_at' => $now]);
        }
    }
    foreach (['/sample-page', '/comments/feed', '/xmlrpc.php', '/hello-world'] as $gone) {
        if (!DB::val('SELECT id FROM redirects WHERE from_path = ?', [$gone])) {
            DB::insert('redirects', ['from_path' => $gone, 'to_path' => '', 'code' => 410, 'hits' => 0, 'created_at' => $now]);
        }
    }
};
