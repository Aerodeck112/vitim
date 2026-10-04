<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\DB;
use App\Core\Settings;
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
            'servicii' => DB::val('SELECT MAX(updated_at) FROM services WHERE published = 1'),
            'zone' => DB::val('SELECT MAX(updated_at) FROM locations WHERE published = 1'),
        ];
        if (Site::hasPosts()) {
            $parts['blog'] = DB::val("SELECT MAX(COALESCE(updated_at, published_at)) FROM posts WHERE status = 'published'");
        }
        if (Site::hasProjects()) {
            $parts['proiecte'] = DB::val('SELECT MAX(updated_at) FROM projects WHERE published = 1');
        }
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<?xml-stylesheet type="text/xsl" href="' . e(url('/sitemap.xsl')) . '"?>' . "\n";
        $x .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($parts as $k => $lm) {
            $x .= "  <sitemap><loc>" . e(abs_url("/sitemap-$k.xml")) . "</loc><lastmod>" . self::lastmod($lm) . "</lastmod></sitemap>\n";
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
                $add('/', DB::val('SELECT MAX(updated_at) FROM services'), 'weekly', '1.0', array_filter([Settings::get('seo_default_og') ? upload_url((string)Settings::get('seo_default_og')) : '']));
                $add('/servicii', DB::val('SELECT MAX(updated_at) FROM services'), 'weekly', '0.9');
                $add('/zone', DB::val('SELECT MAX(updated_at) FROM locations'), 'monthly', '0.8');
                $add('/contact', DB::val("SELECT updated_at FROM pages WHERE slug='contact'"), 'yearly', '0.8');
                $add('/despre-noi', DB::val("SELECT updated_at FROM pages WHERE slug='despre-noi'"), 'yearly', '0.7');
                $add('/vitim-ai', null, 'monthly', '0.9');
                if (Site::hasPosts()) {
                    $add('/blog', DB::val("SELECT MAX(published_at) FROM posts WHERE status='published'"), 'weekly', '0.7');
                }
                if (Site::hasProjects()) {
                    $add('/proiecte', DB::val('SELECT MAX(updated_at) FROM projects'), 'monthly', '0.7');
                }
                foreach (DB::all("SELECT slug, updated_at FROM pages WHERE published = 1 AND noindex = 0 AND slug NOT IN ('contact','despre-noi') ORDER BY sort") as $p) {
                    $add('/' . $p['slug'], $p['updated_at'], 'yearly', '0.3');
                }
                break;
            case 'servicii':
                foreach (DB::all('SELECT slug, updated_at, image FROM services WHERE published = 1 AND noindex = 0 ORDER BY sort') as $s) {
                    $add('/servicii/' . $s['slug'], $s['updated_at'], 'monthly', '0.9', array_filter([$s['image'] ? upload_url($s['image']) : '', '/og/serviciu/' . $s['slug'] . '.png']));
                }
                break;
            case 'zone':
                foreach (DB::all('SELECT slug, type, updated_at FROM locations WHERE published = 1 AND noindex = 0 ORDER BY sort') as $l) {
                    $add('/zone/' . $l['slug'], $l['updated_at'], 'monthly', $l['type'] === 'judet' ? '0.8' : '0.7');
                }
                break;
            case 'blog':
                foreach (DB::all("SELECT slug, cover, updated_at, published_at FROM posts WHERE status = 'published' AND published_at <= ? AND noindex = 0 ORDER BY published_at DESC", [DB::now()]) as $p) {
                    $add('/blog/' . $p['slug'], $p['updated_at'] ?: $p['published_at'], 'monthly', '0.6', array_filter([$p['cover'] ? upload_url($p['cover']) : '']));
                }
                break;
            case 'proiecte':
                foreach (DB::all('SELECT slug, cover, updated_at FROM projects WHERE published = 1 AND noindex = 0 ORDER BY sort') as $p) {
                    $add('/proiecte/' . $p['slug'], $p['updated_at'], 'yearly', '0.6', array_filter([$p['cover'] ? upload_url($p['cover']) : '']));
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
<style>body{font-family:system-ui,sans-serif;background:#05070d;color:#eef1f8;margin:0;padding:40px}h1{font-size:28px}p{color:#aeb6c8}table{border-collapse:collapse;width:100%;max-width:1100px;font-size:14px}th,td{text-align:left;padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.08)}th{color:#7a8398;font-weight:500}a{color:#6ea8ff;text-decoration:none}</style></head>
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
            "Disallow: $bp/e/",
            "Disallow: $bp/newsletter/",
            "Disallow: $bp/multumim",
            "Disallow: $bp/cron",
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

    /** llms.txt – rezumat curat al site-ului pentru asistenți AI (ChatGPT, Claude, Perplexity, Gemini). */
    public function llms(): string
    {
        header('Content-Type: text/plain; charset=utf-8');
        $s = fn($k) => (string)Settings::get($k);
        $out = '# ' . $s('brand_name') . ' (' . $s('company_name') . ")\n\n";
        $out .= '> ' . $s('seo_llms_intro') . "\n\n";
        $out .= "## Contact\n\n";
        $out .= '- Telefon: ' . $s('phone') . "\n- Email: " . $s('email') . "\n- Program: " . $s('hours') . "\n";
        $out .= '- Sediu: ' . trim($s('company_address') . ' ' . $s('company_city') . ', județul ' . $s('company_county')) . "\n";
        $out .= '- Formular de ofertă: ' . abs_url('/contact') . "\n\n";
        foreach (Site::servicesByCategory() as $g) {
            $out .= '## ' . $g['cat']['name'] . "\n\n";
            foreach ($g['items'] as $sv) {
                $out .= '- [' . $sv['title'] . '](' . abs_url('/servicii/' . $sv['slug']) . '): ' . $sv['excerpt'] . ($sv['onsite'] ? ' (remote + la sediu)' : '') . "\n";
            }
            $out .= "\n";
        }
        $out .= "## Zone deservite on-site\n\n";
        foreach (Site::counties() as $c) {
            $out .= '- [Județul ' . $c['name'] . '](' . abs_url('/zone/' . $c['slug']) . ')' . ($c['cities'] ? ': ' . implode(', ', array_column($c['cities'], 'name')) : '') . "\n";
        }
        $out .= "- Remote: toată România\n\n";
        $faq = Settings::json('home_faq');
        if ($faq) {
            $out .= "## Întrebări frecvente\n\n";
            foreach ($faq as $f) {
                $out .= '### ' . $f['q'] . "\n\n" . strip_tags((string)$f['a']) . "\n\n";
            }
        }
        $posts = DB::all("SELECT slug, title, excerpt FROM posts WHERE status = 'published' AND published_at <= ? ORDER BY published_at DESC LIMIT 20", [DB::now()]);
        if ($posts) {
            $out .= "## Articole\n\n";
            foreach ($posts as $p) {
                $out .= '- [' . $p['title'] . '](' . abs_url('/blog/' . $p['slug']) . ')' . ($p['excerpt'] ? ': ' . $p['excerpt'] : '') . "\n";
            }
            $out .= "\n";
        }
        $out .= "## VITIM AI\n\n- [Platforma VITIM AI](" . abs_url('/vitim-ai') . "): asistent AI pe site 24/7, contacte și cereri într-un singur loc, campanii email / SMS / WhatsApp, automatizări, formulare, integrare WooCommerce, site administrat și raport lunar, configurate și administrate de VITIM.\n\n";
        $out .= "## Optional\n\n- [Despre noi](" . abs_url('/despre-noi') . ")\n- [Sitemap](" . abs_url('/sitemap.xml') . ")\n";
        return $out;
    }

    public function manifest(): string
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        return (string)json_encode([
            'name' => Settings::get('brand_name') . ' – ' . Settings::get('brand_tagline'),
            'short_name' => (string)Settings::get('brand_name'),
            'start_url' => url('/') . '?utm_source=pwa',
            'display' => 'standalone',
            'background_color' => '#05070d',
            'theme_color' => '#05070d',
            'lang' => 'ro',
            'icons' => [
                ['src' => url('/assets/img/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => url('/assets/img/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => url('/assets/img/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public function feed(): string
    {
        header('Content-Type: application/rss+xml; charset=utf-8');
        $posts = DB::all("SELECT * FROM posts WHERE status = 'published' AND published_at <= ? ORDER BY published_at DESC LIMIT 20", [DB::now()]);
        $x = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>';
        $x .= '<title>' . e(Settings::get('brand_name')) . ' – Blog</title><link>' . e(abs_url('/blog')) . '</link><description>' . e(Settings::get('brand_tagline')) . '</description><language>ro-RO</language>';
        $x .= '<atom:link href="' . e(abs_url('/feed.xml')) . '" rel="self" type="application/rss+xml"/>';
        foreach ($posts as $p) {
            $x .= '<item><title>' . e($p['title']) . '</title><link>' . e(abs_url('/blog/' . $p['slug'])) . '</link><guid>' . e(abs_url('/blog/' . $p['slug'])) . '</guid><pubDate>' . date(DATE_RSS, (int)strtotime($p['published_at'] . ' UTC')) . '</pubDate><description>' . e($p['excerpt'] ?: excerpt((string)$p['body'], 300)) . '</description></item>';
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

    /** Imagine Open Graph generată automat (1200×630) pentru distribuiri pe Facebook, LinkedIn, WhatsApp. */
    public function og(string $type, string $slug): string
    {
        [$title, $label] = match ($type) {
            'serviciu' => [(string)DB::val('SELECT title FROM services WHERE slug = ?', [$slug]), 'Servicii'],
            'zona' => (function () use ($slug) {
                $l = DB::row('SELECT name, type FROM locations WHERE slug = ?', [$slug]);
                return [$l ? ($l['type'] === 'judet' ? 'Servicii IT în județul ' : 'Servicii IT în ') . $l['name'] : '', 'Zone deservite'];
            })(),
            'articol' => [(string)DB::val('SELECT title FROM posts WHERE slug = ?', [$slug]), 'Blog'],
            'home' => [(string)Settings::get('brand_tagline'), (string)Settings::get('brand_name')],
            default => ['', ''],
        };
        if ($title === '' || !function_exists('imagettftext')) {
            return $this->notFound();
        }
        $dir = STORAGE_PATH . '/cache/og';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/' . md5($type . $slug . $title . APP_VERSION) . '.png';
        if (!is_file($file)) {
            $this->drawOg($file, $title, $label);
        }
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=604800');
        return (string)file_get_contents($file);
    }

    private function drawOg(string $file, string $title, string $label): void
    {
        $w = 1200;
        $h = 630;
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, true);
        // fundal
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $c = imagecolorallocate($im, (int)(5 + 8 * $t), (int)(7 + 10 * $t), (int)(13 + 22 * $t));
            imageline($im, 0, $y, $w, $y, $c);
        }
        // lumini difuze
        foreach ([[180, -40, 700, [47, 107, 255]], [1080, 80, 640, [123, 92, 255]], [700, 720, 600, [18, 199, 182]]] as [$cx, $cy, $r, $rgb]) {
            for ($i = $r; $i > 0; $i -= 6) {
                $a = (int)(127 - (1 - $i / $r) * 22);
                $col = imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], max(0, min(127, $a)));
                imagefilledellipse($im, $cx, $cy, $i, $i, $col);
            }
        }
        // grilă
        $grid = imagecolorallocatealpha($im, 255, 255, 255, 121);
        for ($x = 0; $x < $w; $x += 60) {
            imageline($im, $x, 0, $x, $h, $grid);
        }
        for ($y = 0; $y < $h; $y += 60) {
            imageline($im, 0, $y, $w, $y, $grid);
        }
        $bold = APP_PATH . '/Data/fonts/Geist-Bold.ttf';
        $reg = APP_PATH . '/Data/fonts/Geist-Regular.ttf';
        $white = imagecolorallocate($im, 238, 241, 248);
        $muted = imagecolorallocate($im, 174, 182, 200);
        $blue = imagecolorallocate($im, 110, 168, 255);
        // marcă
        $markBg = imagecolorallocate($im, 60, 110, 255);
        imagefilledrectangle($im, 80, 72, 136, 128, $markBg);
        imagettftext($im, 30, 0, 91, 113, $white, $bold, 'V');
        imagettftext($im, 30, 0, 156, 112, $white, $bold, (string)Settings::get('brand_name', 'VITIM'));
        imagettftext($im, 20, 0, 80, 205, $blue, $reg, mb_strtoupper($label));
        // titlu pe mai multe rânduri
        $size = mb_strlen($title) > 60 ? 50 : 60;
        $lines = [];
        $line = '';
        foreach (explode(' ', $title) as $word) {
            $test = trim($line . ' ' . $word);
            $box = imagettfbbox($size, 0, $bold, $test);
            if ($box[2] - $box[0] > 1040 && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $test;
            }
        }
        $lines[] = $line;
        $lines = array_slice($lines, 0, 4);
        $y = 290;
        foreach ($lines as $l) {
            imagettftext($im, $size, 0, 80, $y, $white, $bold, $l);
            $y += (int)($size * 1.25);
        }
        $foot = (string)parse_url(abs_url('/'), PHP_URL_HOST) . '   ·   ' . Settings::get('phone');
        imagettftext($im, 22, 0, 80, 575, $muted, $reg, $foot);
        imagepng($im, $file, 7);
        imagedestroy($im);
    }
}
