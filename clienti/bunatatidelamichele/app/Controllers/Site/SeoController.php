<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\DB;
use App\Core\Orders;
use App\Core\RateLimit;
use App\Core\Settings;
use App\Core\Shop;
use App\Core\Site;

final class SeoController extends SiteController
{
    private function xmlHeaders(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex, follow');
    }

    private static function lastmod(?string $dt): string
    {
        $ts = $dt ? strtotime($dt . ' UTC') : time();
        return date('c', $ts ?: time());
    }

    public function sitemapIndex(): string
    {
        $this->xmlHeaders();
        $parts = [
            'pagini' => DB::val('SELECT MAX(updated_at) FROM pages WHERE published = 1'),
            'produse' => DB::val('SELECT MAX(updated_at) FROM products WHERE published = 1'),
            'categorii' => DB::val('SELECT MAX(updated_at) FROM categories WHERE published = 1'),
        ];
        if (Site::hasPosts()) {
            $parts['blog'] = DB::val("SELECT MAX(COALESCE(updated_at, published_at)) FROM posts WHERE status = 'published'");
        }
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<?xml-stylesheet type="text/xsl" href="' . e(url('/sitemap.xsl')) . '"?>' . "\n";
        $x .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($parts as $k => $lm) {
            $x .= '  <sitemap><loc>' . e(abs_url("/sitemap-$k.xml")) . '</loc><lastmod>' . self::lastmod($lm) . "</lastmod></sitemap>\n";
        }
        return $x . '</sitemapindex>';
    }

    public function sitemap(string $type): string
    {
        $this->xmlHeaders();
        $urls = [];
        $add = function (string $path, ?string $lm, string $freq, string $prio, array $images = []) use (&$urls) {
            $urls[] = compact('path', 'lm', 'freq', 'prio', 'images');
        };
        switch ($type) {
            case 'pagini':
                $add('/', DB::val('SELECT MAX(updated_at) FROM products'), 'weekly', '1.0', array_filter([(string)Settings::get('home_hero_image') ? upload_url((string)Settings::get('home_hero_image')) : '']));
                $add('/produse', DB::val('SELECT MAX(updated_at) FROM products'), 'weekly', '0.9');
                $add('/despre-noi', DB::val("SELECT updated_at FROM pages WHERE slug='despre-noi'"), 'monthly', '0.6', array_filter([(string)Settings::get('about_image') ? upload_url((string)Settings::get('about_image')) : '']));
                $add('/contact', DB::val("SELECT MAX(updated_at) FROM pages"), 'yearly', '0.6');
                if (Site::hasPosts()) {
                    $add('/blog', DB::val("SELECT MAX(published_at) FROM posts WHERE status='published'"), 'weekly', '0.6');
                }
                foreach (DB::all("SELECT slug, updated_at FROM pages WHERE published = 1 AND noindex = 0 AND slug <> 'despre-noi' ORDER BY sort") as $p) {
                    $add('/' . $p['slug'], $p['updated_at'], 'yearly', '0.3');
                }
                break;
            case 'produse':
                foreach (DB::all('SELECT slug, images, updated_at FROM products WHERE published = 1 AND noindex = 0 ORDER BY sort') as $p) {
                    $add('/produs/' . $p['slug'], $p['updated_at'], 'weekly', '0.9', array_map('upload_url', Shop::images($p)));
                }
                break;
            case 'categorii':
                foreach (DB::all('SELECT slug, image, updated_at FROM categories WHERE published = 1 AND noindex = 0 ORDER BY sort') as $c) {
                    $add('/categorie/' . $c['slug'], $c['updated_at'], 'weekly', '0.8', array_filter([$c['image'] ? upload_url($c['image']) : '']));
                }
                break;
            case 'blog':
                foreach (DB::all("SELECT slug, cover, updated_at, published_at FROM posts WHERE status = 'published' AND published_at <= ? AND noindex = 0 ORDER BY published_at DESC", [DB::now()]) as $p) {
                    $add('/blog/' . $p['slug'], $p['updated_at'] ?: $p['published_at'], 'monthly', '0.6', array_filter([$p['cover'] ? upload_url($p['cover']) : '']));
                }
                break;
        }
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<?xml-stylesheet type="text/xsl" href="' . e(url('/sitemap.xsl')) . '"?>' . "\n";
        $x .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        foreach ($urls as $u) {
            $x .= '  <url><loc>' . e(abs_url($u['path'])) . '</loc><lastmod>' . self::lastmod($u['lm']) . '</lastmod><changefreq>' . $u['freq'] . '</changefreq><priority>' . $u['prio'] . '</priority>';
            foreach ($u['images'] as $img) {
                $x .= '<image:image><image:loc>' . e(abs_url($img)) . '</image:loc></image:image>';
            }
            $x .= "</url>\n";
        }
        return $x . '</urlset>';
    }

    public function xsl(): string
    {
        header('Content-Type: text/xsl; charset=utf-8');
        return <<<'XSL'
<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
<xsl:output method="html" encoding="UTF-8"/>
<xsl:template match="/">
<html><head><meta charset="utf-8"/><meta name="robots" content="noindex"/><title>Sitemap</title>
<style>body{font-family:system-ui,sans-serif;background:#f6efe6;color:#2b1a12;margin:0;padding:40px}h1{font-size:28px}p{color:#6b5a4a}table{border-collapse:collapse;width:100%;max-width:1100px;font-size:14px}th,td{text-align:left;padding:10px 12px;border-bottom:1px solid rgba(43,26,18,.1)}th{color:#8a7663;font-weight:500}a{color:#b5602c;text-decoration:none}</style></head>
<body><h1>Sitemap XML</h1><p>Acest fișier ajută Google și alte motoare de căutare să descopere paginile site-ului.</p>
<xsl:if test="count(s:sitemapindex/s:sitemap) &gt; 0"><table><tr><th>Sitemap</th><th>Ultima modificare</th></tr>
<xsl:for-each select="s:sitemapindex/s:sitemap"><tr><td><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td><td><xsl:value-of select="substring(s:lastmod,0,11)"/></td></tr></xsl:for-each></table></xsl:if>
<xsl:if test="count(s:urlset/s:url) &gt; 0"><p><xsl:value-of select="count(s:urlset/s:url)"/> adrese</p><table><tr><th>URL</th><th>Prioritate</th><th>Imagini</th><th>Ultima modificare</th></tr>
<xsl:for-each select="s:urlset/s:url"><tr><td><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td><td><xsl:value-of select="s:priority"/></td><td><xsl:value-of select="count(image:image)"/></td><td><xsl:value-of select="substring(s:lastmod,0,11)"/></td></tr></xsl:for-each></table></xsl:if>
</body></html>
</xsl:template>
</xsl:stylesheet>
XSL;
    }

    public function robots(): string
    {
        header('Content-Type: text/plain; charset=utf-8');
        if (Settings::get('seo_noindex_site') === '1') {
            return "User-agent: *\nDisallow: /\n";
        }
        $bp = base_path();
        $lines = [
            '# ' . Settings::get('brand_name') . ' – robots.txt',
            'User-agent: *',
            "Allow: $bp/",
            "Disallow: $bp/admin",
            "Disallow: $bp/api/",
            "Disallow: $bp/cos",
            "Disallow: $bp/finalizare",
            "Disallow: $bp/comanda/",
            "Disallow: $bp/plata/",
            "Disallow: $bp/urmarire-comanda",
            "Disallow: $bp/cron",
            "Disallow: $bp/*?*ordonare=",
            "Disallow: $bp/*?*q=",
            "Disallow: $bp/*?*utm_",
            "Disallow: $bp/*?*gclid=",
            "Disallow: $bp/*?*fbclid=",
            '',
            '# Motoare de căutare AI – permise, pentru vizibilitate în răspunsurile generate',
            'User-agent: GPTBot',
            'User-agent: OAI-SearchBot',
            'User-agent: ChatGPT-User',
            'User-agent: ClaudeBot',
            'User-agent: Claude-SearchBot',
            'User-agent: PerplexityBot',
            'User-agent: Google-Extended',
            'User-agent: Applebot-Extended',
            "Allow: $bp/",
            "Disallow: $bp/admin",
            "Disallow: $bp/api/",
            "Disallow: $bp/comanda/",
            '',
            'Sitemap: ' . abs_url('/sitemap.xml'),
        ];
        $extra = trim((string)Settings::get('seo_robots_extra'));
        if ($extra !== '') {
            $lines[] = '';
            $lines[] = $extra;
        }
        return implode("\n", $lines) . "\n";
    }

    /** llms.txt – rezumat curat al magazinului pentru asistenți AI (ChatGPT, Claude, Perplexity, Gemini). */
    public function llms(): string
    {
        header('Content-Type: text/plain; charset=utf-8');
        $s = fn($k) => (string)Settings::get($k);
        $out = '# ' . $s('brand_name') . ' (' . $s('company_name') . ")\n\n> " . $s('seo_llms_intro') . "\n\n";
        $out .= "## Contact\n\n- Email: " . $s('email') . "\n" . ($s('phone') ? '- Telefon: ' . $s('phone') . "\n" : '') . '- Program: ' . $s('hours') . "\n- Formular: " . abs_url('/contact') . "\n\n";
        $out .= "## Livrare și plată\n\n- " . $s('shipping_label') . ': ' . Shop::money($s('shipping_cost')) . ', ' . $s('delivery_time') . "\n- Plată online cu cardul (BT iPay, Banca Transilvania) sau ramburs\n- Retur: " . $s('return_days') . ' zile (' . abs_url('/politica-de-retur') . ")\n\n";
        foreach (Site::categories() as $c) {
            $out .= '## ' . $c['name'] . ' (' . abs_url('/categorie/' . $c['slug']) . ")\n\n";
            foreach (Shop::publishedProducts('p.category_id = :c', ['c' => $c['id']]) as $p) {
                $out .= '- [' . $p['name'] . '](' . abs_url(Shop::url($p)) . '): ' . Shop::money(Shop::price($p)) . ($p['price_note'] ? ' (' . $p['price_note'] . ')' : '') . ' – ' . excerpt((string)$p['short_description'], 180) . "\n";
            }
            $out .= "\n";
        }
        $faq = Settings::json('faq');
        if ($faq) {
            $out .= "## Întrebări frecvente\n\n";
            foreach ($faq as $f) {
                $out .= '### ' . $f['q'] . "\n\n" . strip_tags((string)$f['a']) . "\n\n";
            }
        }
        $out .= "## Optional\n\n- [Despre noi](" . abs_url('/despre-noi') . ")\n- [Termeni și condiții](" . abs_url('/termeni-si-conditii') . ")\n- [Sitemap](" . abs_url('/sitemap.xml') . ")\n";
        return $out;
    }

    public function manifest(): string
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        return (string)json_encode([
            'name' => (string)Settings::get('brand_name'),
            'short_name' => 'Michele',
            'description' => (string)Settings::get('brand_tagline'),
            'start_url' => url('/') . '?utm_source=pwa',
            'display' => 'standalone',
            'background_color' => '#f6efe6',
            'theme_color' => '#2b1a12',
            'lang' => 'ro',
            'icons' => [
                ['src' => url('/assets/img/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => url('/assets/img/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => url('/assets/img/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /** Feed de produse pentru Google Merchant Center (Google Shopping și listări gratuite). */
    public function merchant(): string
    {
        if (Settings::get('merchant_feed') !== '1') {
            return $this->notFound();
        }
        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex');
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<rss version=\"2.0\" xmlns:g=\"http://base.google.com/ns/1.0\"><channel>\n";
        $x .= '<title>' . e(Settings::get('brand_name')) . '</title><link>' . e(abs_url('/')) . '</link><description>' . e(Settings::get('brand_tagline')) . "</description>\n";
        $ship = number_format((float)Settings::get('shipping_cost', '0'), 2, '.', '') . ' RON';
        foreach (Shop::publishedProducts() as $p) {
            $imgs = Shop::images($p);
            if (!$imgs) {
                continue;
            }
            $x .= '<item>';
            $x .= '<g:id>' . e($p['sku'] ?: 'BDM-' . $p['id']) . '</g:id>';
            $x .= '<g:title>' . e(mb_substr(html_entity_decode($p['name']), 0, 150)) . '</g:title>';
            $x .= '<g:description>' . e(mb_substr(excerpt($p['short_description'] . ' ' . $p['description'], 4900), 0, 4900)) . '</g:description>';
            $x .= '<g:link>' . e(abs_url(Shop::url($p))) . '</g:link>';
            $x .= '<g:image_link>' . e(abs_url(upload_url($imgs[0]))) . '</g:image_link>';
            foreach (array_slice($imgs, 1, 9) as $im) {
                $x .= '<g:additional_image_link>' . e(abs_url(upload_url($im))) . '</g:additional_image_link>';
            }
            $x .= '<g:availability>' . (Shop::inStock($p) ? 'in_stock' : 'out_of_stock') . '</g:availability>';
            $x .= '<g:price>' . number_format((float)$p['price'], 2, '.', '') . ' RON</g:price>';
            if (Shop::onSale($p)) {
                $x .= '<g:sale_price>' . number_format((float)$p['sale_price'], 2, '.', '') . ' RON</g:sale_price>';
            }
            $x .= '<g:condition>new</g:condition>';
            if ($p['brand']) {
                $x .= '<g:brand>' . e($p['brand']) . '</g:brand>';
            }
            if ($p['gtin']) {
                $x .= '<g:gtin>' . e($p['gtin']) . '</g:gtin>';
            } else {
                $x .= '<g:identifier_exists>no</g:identifier_exists>';
            }
            if ($p['category_name']) {
                $x .= '<g:product_type>' . e($p['category_name']) . '</g:product_type>';
            }
            if ((int)$p['weight_g'] > 0) {
                $x .= '<g:shipping_weight>' . (int)$p['weight_g'] . ' g</g:shipping_weight>';
            }
            $x .= '<g:shipping><g:country>RO</g:country><g:service>' . e(Settings::get('shipping_label')) . '</g:service><g:price>' . $ship . '</g:price></g:shipping>';
            $x .= "</item>\n";
        }
        return $x . '</channel></rss>';
    }

    public function indexNowKey(string $key): string
    {
        if ($key !== (string)Settings::get('seo_indexnow_key')) {
            return $this->notFound();
        }
        header('Content-Type: text/plain; charset=utf-8');
        return $key;
    }

    /** Sarcini automate: anulează plățile cu card abandonate, verifică plățile în așteptare, curățenie. */
    public function cron(): never
    {
        \App\Core\App::$noCache = true;
        if (!hash_equals((string)Settings::get('cron_key'), str_input('key')) || !RateLimit::hit('cron', 30, 300)) {
            http_response_code(403);
            exit('forbidden');
        }
        @set_time_limit(120);
        $cancelled = Orders::cancelStale();
        Shop::gc();
        RateLimit::gc();
        DB::q('DELETE FROM messages WHERE created_at < ?', [gmdate('Y-m-d H:i:s', strtotime('-2 years'))]);
        DB::q('DELETE FROM email_log WHERE created_at < ?', [gmdate('Y-m-d H:i:s', strtotime('-1 year'))]);
        Settings::set('cron_last_run', DB::now());
        header('Content-Type: text/plain; charset=utf-8');
        echo "OK – comenzi anulate: $cancelled\n";
        exit;
    }

    /** Imagine Open Graph generată automat (1200×630) pentru distribuiri pe Facebook, WhatsApp. */
    public function og(string $type, string $slug): string
    {
        $row = match ($type) {
            'produs' => DB::row('SELECT name AS t, images FROM products WHERE slug = ?', [$slug]),
            'categorie' => DB::row('SELECT name AS t, image AS images FROM categories WHERE slug = ?', [$slug]),
            'home' => ['t' => (string)Settings::get('brand_tagline'), 'images' => json_encode([(string)Settings::get('home_hero_image')])],
            default => null,
        };
        if (!$row || !function_exists('imagettftext')) {
            return $this->notFound();
        }
        $imgs = str_starts_with((string)$row['images'], '[') ? json_list($row['images']) : [(string)$row['images']];
        $dir = STORAGE_PATH . '/cache/og';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/' . md5($type . $slug . $row['t'] . ($imgs[0] ?? '') . APP_VERSION) . '.png';
        if (!is_file($file)) {
            $this->drawOg($file, html_entity_decode((string)$row['t']), (string)($imgs[0] ?? ''));
        }
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=604800');
        return (string)file_get_contents($file);
    }

    private function drawOg(string $file, string $title, string $image): void
    {
        $w = 1200;
        $h = 630;
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 246, 239, 230));
        $dark = imagecolorallocate($im, 43, 26, 18);
        $accent = imagecolorallocate($im, 181, 96, 44);
        $muted = imagecolorallocate($im, 120, 98, 80);
        imagefilledrectangle($im, 0, $h - 14, $w, $h, $accent);
        $src = $image !== '' && is_file(UPLOADS_PATH . '/' . $image) ? @imagecreatefromstring((string)file_get_contents(UPLOADS_PATH . '/' . $image)) : false;
        $textW = 1040;
        if ($src) {
            $sw = imagesx($src);
            $sh = imagesy($src);
            $box = 520;
            $r = min($box / $sw, $box / $sh);
            $dw = (int)($sw * $r);
            $dh = (int)($sh * $r);
            imagealphablending($im, true);
            imagecopyresampled($im, $src, 1200 - 40 - $dw, (int)(($h - $dh) / 2), 0, 0, $dw, $dh, $sw, $sh);
            $textW = 560;
        }
        $logoFile = ROOT_PATH . '/assets/img/logo.png';
        if (is_file($logoFile) && ($logo = @imagecreatefrompng($logoFile))) {
            $lw = imagesx($logo);
            $lh = imagesy($logo);
            imagecopyresampled($im, $logo, 70, 50, 0, 0, (int)($lw * 110 / $lh), 110, $lw, $lh);
        }
        $bold = APP_PATH . '/Data/fonts/Geist-Bold.ttf';
        $reg = APP_PATH . '/Data/fonts/Geist-Regular.ttf';
        $size = mb_strlen($title) > 55 ? 38 : 48;
        $lines = [];
        $line = '';
        foreach (explode(' ', $title) as $word) {
            $test = trim($line . ' ' . $word);
            $bb = imagettfbbox($size, 0, $bold, $test);
            if ($bb[2] - $bb[0] > $textW && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $test;
            }
        }
        $lines[] = $line;
        $y = 260;
        foreach (array_slice($lines, 0, 5) as $l) {
            imagettftext($im, $size, 0, 70, $y, $dark, $bold, $l);
            $y += (int)($size * 1.3);
        }
        imagettftext($im, 22, 0, 70, 570, $muted, $reg, (string)parse_url(abs_url('/'), PHP_URL_HOST) . '  ·  ' . Settings::get('brand_tagline'));
        imagepng($im, $file, 7);
        imagedestroy($im);
    }
}
