<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\DB;
use App\Core\Sanitizer;
use App\Core\Seo;
use App\Core\Settings;
use App\Core\Shop;
use App\Core\Site;

final class PageController extends SiteController
{
    private function page(string $slug): ?array
    {
        return DB::row('SELECT * FROM pages WHERE slug = ? AND published = 1', [$slug]);
    }

    public function about(): string
    {
        $p = $this->page('despre-noi') ?? ['title' => 'Despre noi', 'subtitle' => '', 'body' => '', 'slug' => 'despre-noi'];
        $seo = $this->seo($p['title'], $p['subtitle'] ?: excerpt((string)$p['body']), [[$p['title'], '/despre-noi']]);
        $this->applyMeta($seo, $p);
        $seo->pageType = 'AboutPage';
        if ($img = (string)Settings::get('about_image')) {
            $seo->image = abs_url(upload_url($img));
        }
        return $this->view('about', [
            'p' => $p,
            'body' => Site::companyVars((string)$p['body']),
            'process' => Settings::json('home_process'),
            'stats' => Settings::json('home_stats'),
            'featured' => Shop::publishedProducts('p.featured = 1', [], 'p.sort', 4),
        ], $seo);
    }

    public function contact(): string
    {
        $faq = Settings::json('faq');
        $seo = $this->seo('Contact și întrebări frecvente', 'Ai o întrebare despre cafea, o comandă sau livrare? Scrie-ne la ' . Settings::get('email') . ' sau completează formularul – îți răspundem rapid.', [['Contact', '/contact']]);
        $seo->pageType = 'ContactPage';
        $seo->schema[] = Seo::faqSchema($faq, abs_url('/contact'));
        return $this->view('contact', ['faq' => $faq], $seo);
    }

    public function show(string $slug): string
    {
        $p = $this->page($slug);
        if (!$p || in_array($slug, ['despre-noi'], true)) {
            if ($slug === 'despre-noi') {
                redirect('/despre-noi', 301);
            }
            \App\Core\App::tryRedirect('/' . $slug); // adrese vechi (ex. /shop) → redirecționare sau 410
            \App\Core\App::notFound('/' . $slug);
            return '';
        }
        $seo = $this->seo($p['title'], $p['subtitle'] ?: excerpt(Site::companyVars((string)$p['body'])), [[$p['title'], '/' . $p['slug']]]);
        $this->applyMeta($seo, $p);
        [$html, $toc] = Sanitizer::withToc(Site::companyVars((string)$p['body']));
        return $this->view('page', ['p' => $p, 'body' => $html, 'toc' => $toc], $seo);
    }

    public function blog(): string
    {
        $posts = DB::all("SELECT * FROM posts WHERE status = 'published' AND published_at <= ? ORDER BY published_at DESC LIMIT 60", [DB::now()]);
        if (!$posts) {
            return $this->notFound();
        }
        $seo = $this->seo('Blog – despre cafea', 'Ghiduri, rețete și povești despre cafeaua italiană: cum alegi, cum prepari și cum păstrezi cafeaua.', [['Blog', '/blog']]);
        $seo->pageType = 'CollectionPage';
        return $this->view('blog', ['posts' => $posts], $seo);
    }

    public function post(string $slug): string
    {
        $p = DB::row("SELECT * FROM posts WHERE slug = ? AND status = 'published' AND published_at <= ?", [$slug, DB::now()]);
        if (!$p) {
            return $this->notFound();
        }
        $seo = $this->seo($p['title'], $p['excerpt'] ?: excerpt((string)$p['body']), [['Blog', '/blog'], [$p['title'], '/blog/' . $p['slug']]]);
        $this->applyMeta($seo, $p);
        $seo->type = 'article';
        $seo->pageType = 'WebPage';
        $seo->published = $p['published_at'];
        $seo->modified = $p['updated_at'] ?: $p['published_at'];
        if ($p['cover'] && !$p['og_image']) {
            $seo->image = abs_url(upload_url($p['cover']));
        }
        $url = abs_url('/blog/' . $p['slug']);
        $seo->schema[] = [
            '@type' => 'BlogPosting', '@id' => $url . '#articol', 'headline' => $p['title'], 'description' => $p['excerpt'],
            'datePublished' => Seo::iso((string)$p['published_at']), 'dateModified' => Seo::iso((string)($p['updated_at'] ?: $p['published_at'])),
            'image' => $p['cover'] ? abs_url(upload_url($p['cover'])) : null, 'mainEntityOfPage' => $url,
            'author' => ['@id' => Seo::orgId()], 'publisher' => ['@id' => Seo::orgId()], 'inLanguage' => 'ro-RO',
        ];
        $seo->schema[] = Seo::faqSchema(json_list($p['faq']), $url);
        [$html, $toc] = Sanitizer::withToc((string)$p['body']);
        $more = DB::all("SELECT slug, title, cover, excerpt, published_at FROM posts WHERE status = 'published' AND published_at <= ? AND id <> ? ORDER BY published_at DESC LIMIT 3", [DB::now(), $p['id']]);
        return $this->view('post', ['p' => $p, 'body' => $html, 'toc' => $toc, 'more' => $more, 'products' => Shop::publishedProducts('p.featured = 1', [], 'p.sort', 4)], $seo);
    }

    public function notFound(bool $gone = false): string
    {
        \App\Core\App::$noCache = false;
        $seo = $this->seo($gone ? 'Pagina nu mai există' : 'Pagina nu a fost găsită');
        $seo->noindex = true;
        return $this->view('404', ['gone' => $gone, 'products' => Shop::publishedProducts('p.featured = 1', [], 'p.sort', 4)], $seo);
    }
}
