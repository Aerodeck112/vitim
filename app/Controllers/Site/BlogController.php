<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\DB;
use App\Core\Sanitizer;
use App\Core\Seo;
use App\Core\Settings;

final class BlogController extends SiteController
{
    private const PER_PAGE = 9;

    public function index(string $page = '1'): string
    {
        $p = max(1, (int)$page);
        if ($page === '1' && str_contains(\App\Core\App::$path, '/pagina/')) {
            redirect('/blog', 301);
        }
        $now = DB::now();
        $total = (int)DB::val("SELECT COUNT(*) FROM posts WHERE status = 'published' AND published_at <= ?", [$now]);
        $pages = max(1, (int)ceil($total / self::PER_PAGE));
        if ($p > $pages) {
            return $this->notFound();
        }
        $posts = DB::all(
            "SELECT slug, title, excerpt, cover, published_at, body, category FROM posts WHERE status = 'published' AND published_at <= ? ORDER BY published_at DESC LIMIT " . self::PER_PAGE . ' OFFSET ' . (($p - 1) * self::PER_PAGE),
            [$now]
        );
        $seo = $this->seo(
            'Blog: ghiduri IT, securitate, SEO și AI pentru firme' . ($p > 1 ? " – pagina $p" : ''),
            'Articole practice despre mentenanță IT, securitate cibernetică, recuperări de date, SEO, Google Ads, Meta Ads, automatizări și agenți AI pentru afaceri.',
            [['Blog', '/blog']]
        );
        $seo->pageType = 'CollectionPage';
        $seo->canonical = abs_url($p > 1 ? '/blog/pagina/' . $p : '/blog');
        $seo->schema[] = [
            '@type' => 'Blog',
            '@id' => abs_url('/blog') . '#blog',
            'name' => 'Blog ' . Settings::get('brand_name'),
            'url' => abs_url('/blog'),
            'publisher' => ['@id' => Seo::orgId()],
            'inLanguage' => 'ro-RO',
        ];
        return $this->view('blog', ['posts' => $posts, 'page' => $p, 'pages' => $pages], $seo);
    }

    public function show(string $slug): string
    {
        $post = DB::row("SELECT p.*, u.name AS author_name FROM posts p LEFT JOIN users u ON u.id = p.author_id WHERE p.slug = ? AND p.status = 'published' AND p.published_at <= ?", [$slug, DB::now()]);
        if (!$post) {
            return $this->notFound();
        }
        $url = abs_url('/blog/' . $post['slug']);
        $seo = $this->seo($post['title'], $post['excerpt'] ?: excerpt((string)$post['body'], 160), [['Blog', '/blog'], [$post['title'], '/blog/' . $post['slug']]]);
        $seo->type = 'article';
        $seo->published = $post['published_at'];
        $seo->image = $post['cover'] ? abs_url(upload_url($post['cover'])) : abs_url('/og/articol/' . $post['slug'] . '.png');
        $this->applyMeta($seo, $post);
        $seo->modified = $post['updated_at'] ?: $post['published_at'];
        $author = $post['author_name'] ?: (string)Settings::get('brand_name');
        $article = [
            '@type' => 'BlogPosting',
            '@id' => $url . '#articol',
            'headline' => mb_substr($post['title'], 0, 110),
            'description' => $seo->description,
            'image' => [$seo->image],
            'datePublished' => Seo::iso((string)$post['published_at']),
            'dateModified' => Seo::iso((string)($post['updated_at'] ?: $post['published_at'])),
            'author' => ['@type' => 'Person', 'name' => $author, 'worksFor' => ['@id' => Seo::orgId()]],
            'publisher' => ['@id' => Seo::orgId()],
            'mainEntityOfPage' => ['@id' => $url . '#pagina'],
            'inLanguage' => 'ro-RO',
            'wordCount' => str_word_count(strip_tags((string)$post['body'])),
            'articleSection' => $post['category'] ?: 'Blog',
            'keywords' => $post['tags'] ?: null,
        ];
        $seo->schema[] = array_filter($article, fn($v) => $v !== null);
        $faq = json_list($post['faq']);
        if ($f = Seo::faqSchema($faq, $url)) {
            $seo->schema[] = $f;
        }
        [$body, $toc] = Sanitizer::withToc((string)$post['body']);
        $related = DB::all("SELECT slug, title, excerpt, cover, published_at, body FROM posts WHERE status = 'published' AND published_at <= ? AND id <> ? ORDER BY (category = ?) DESC, published_at DESC LIMIT 3", [DB::now(), $post['id'], (string)$post['category']]);
        return $this->view('post', ['post' => $post, 'body' => $body, 'toc' => $toc, 'faq' => $faq, 'related' => $related, 'author' => $author], $seo);
    }
}
