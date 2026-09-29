<?php
declare(strict_types=1);

use App\Core\DB;
use App\Core\Settings;

/**
 * Conținutul inițial: servicii, zone, pagini, articole, redirecționări de pe vechiul site.
 * Rulează o singură dată (la instalare); după aceea totul se editează din panou.
 */
return function (): void {
    $now = DB::now();

    if (!DB::val('SELECT COUNT(*) FROM services')) {
        foreach (require APP_PATH . '/Data/seed_services.php' as $i => $s) {
            DB::insert('services', [
                'slug' => $s['slug'],
                'category' => $s['category'],
                'title' => $s['title'],
                'h1' => $s['h1'] ?? null,
                'tagline' => $s['tagline'] ?? null,
                'icon' => $s['icon'] ?? null,
                'excerpt' => $s['excerpt'],
                'body' => trim($s['body']),
                'features' => json_encode($s['features'] ?? [], JSON_UNESCAPED_UNICODE),
                'process' => '[]',
                'faq' => json_encode($s['faq'] ?? [], JSON_UNESCAPED_UNICODE),
                'keywords' => $s['keywords'] ?? '',
                'onsite' => (int)($s['onsite'] ?? 0),
                'featured' => (int)($s['featured'] ?? 0),
                'sort' => ($i + 1) * 10,
                'published' => 1,
                'meta_title' => $s['meta_title'] ?? null,
                'meta_description' => $s['meta_description'] ?? '',
                'noindex' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    if (!DB::val('SELECT COUNT(*) FROM locations')) {
        $sort = 0;
        foreach (require APP_PATH . '/Data/seed_locations.php' as $c) {
            $cid = DB::insert('locations', [
                'slug' => $c['slug'], 'name' => $c['name'], 'type' => 'judet', 'parent_id' => null, 'county_name' => $c['name'],
                'intro' => $c['intro'], 'body' => trim($c['body']), 'faq' => '[]', 'lat' => $c['lat'], 'lng' => $c['lng'],
                'sort' => $sort += 10, 'published' => 1, 'meta_description' => '', 'noindex' => 0, 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($c['cities'] as $j => $city) {
                DB::insert('locations', [
                    'slug' => $city['slug'], 'name' => $city['name'], 'type' => 'oras', 'parent_id' => $cid, 'county_name' => $c['name'],
                    'intro' => $city['intro'], 'body' => '', 'faq' => '[]', 'lat' => $city['lat'], 'lng' => $city['lng'],
                    'sort' => ($j + 1) * 10, 'published' => 1, 'meta_description' => '', 'noindex' => 0, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    if (!DB::val('SELECT COUNT(*) FROM pages')) {
        foreach (require APP_PATH . '/Data/seed_pages.php' as $i => $p) {
            DB::insert('pages', [
                'slug' => $p['slug'], 'title' => $p['title'], 'subtitle' => $p['subtitle'] ?: null, 'body' => trim($p['body']),
                'template' => $p['template'], 'in_footer' => $p['in_footer'], 'sort' => ($i + 1) * 10, 'published' => 1,
                'meta_title' => $p['meta_title'] ?? null, 'meta_description' => $p['meta_description'] ?? '', 'noindex' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    if (!DB::val('SELECT COUNT(*) FROM posts')) {
        $author = DB::val('SELECT id FROM users ORDER BY id LIMIT 1');
        foreach (require APP_PATH . '/Data/seed_posts.php' as $p) {
            DB::insert('posts', [
                'slug' => $p['slug'], 'title' => $p['title'], 'excerpt' => $p['excerpt'], 'body' => trim($p['body']),
                'category' => $p['category'], 'tags' => $p['tags'], 'author_id' => $author ?: null, 'status' => 'published',
                'published_at' => $p['published_at'], 'faq' => json_encode($p['faq'], JSON_UNESCAPED_UNICODE),
                'meta_description' => '', 'noindex' => 0, 'created_at' => $now, 'updated_at' => $p['published_at'],
            ]);
        }
    }

    // Adresele vechiului site WordPress → pagini noi (301) sau „dispărut definitiv” (410) pentru conținutul demo
    if (!DB::val('SELECT COUNT(*) FROM redirects')) {
        $map = [
            '/suport-it-mures-telefonic' => '/servicii/mentenanta-it',
            '/seo-optimizare-google' => '/servicii/seo',
            '/local-mures-afaceri-locale' => '/localmures',
            '/vitim-despre-noi' => '/despre-noi',
            '/blog-list' => '/blog',
            '/solutii-web' => '/servicii/site-uri-web',
            '/seo-mures-agentie-marketing-promovare' => '/blog/seo-mures-agentie-marketing-promovare',
            '/feed' => '/feed.xml',
            '/sitemap_index.xml' => '/sitemap.xml',
            '/post-sitemap.xml' => '/sitemap.xml',
            '/page-sitemap.xml' => '/sitemap.xml',
            '/sitemap.rss' => '/feed.xml',
            '/category/it' => '/blog',
            '/category/seo' => '/blog',
            '/tag/seo' => '/blog',
        ];
        foreach ($map as $from => $to) {
            DB::insert('redirects', ['from_path' => $from, 'to_path' => $to, 'code' => 301, 'hits' => 0, 'created_at' => $now]);
        }
        $gone = ['/sample-page', '/team', '/personal-cv', '/personal-portfolio', '/home-portfolio', '/grid-masonry', '/grid-standard',
            '/grid-style-1', '/grid-style-2', '/list-style', '/shop', '/shop-2', '/cart', '/cart-2', '/checkout', '/checkout-2',
            '/my-account', '/my-account-2', '/product/summit-walking', '/product/react-falcon-3-0', '/product/flex-armour',
            '/product/men-vision-trainers', '/product/kl-17-bs', '/product/workout-revolution-2', '/product/run-max-88',
            '/product-sitemap.xml', '/portfolios-sitemap.xml', '/product_cat-sitemap.xml', '/portfolios_categories-sitemap.xml', '/post-archive-sitemap.xml'];
        foreach ($gone as $from) {
            DB::insert('redirects', ['from_path' => $from, 'to_path' => '', 'code' => 410, 'hits' => 0, 'created_at' => $now]);
        }
    }

    if (Settings::get('cron_key', '') === '') {
        Settings::set('cron_key', bin2hex(random_bytes(16)));
    }
    if (Settings::get('seo_indexnow_key', '') === '') {
        Settings::set('seo_indexnow_key', bin2hex(random_bytes(16)));
    }
};
