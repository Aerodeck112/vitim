<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\DB;
use App\Core\Seo;
use App\Core\Settings;
use App\Core\Shop;
use App\Core\Site;

final class ShopController extends SiteController
{
    private const SORTS = [
        'recomandate' => ['Recomandate', 'p.featured DESC, p.sort, p.name'],
        'populare' => ['Cele mai vândute', 'p.sales_count DESC, p.sort'],
        'pret-crescator' => ['Preț crescător', 'COALESCE(p.sale_price, p.price) ASC'],
        'pret-descrescator' => ['Preț descrescător', 'COALESCE(p.sale_price, p.price) DESC'],
        'noi' => ['Cele mai noi', 'p.created_at DESC, p.id DESC'],
        'nume' => ['Nume (A–Z)', 'p.name ASC'],
    ];

    private function listing(?array $cat): string
    {
        $q = mb_substr(trim(str_input('q')), 0, 80);
        $sortKey = str_input('ordonare', 'recomandate');
        if (!isset(self::SORTS[$sortKey])) {
            $sortKey = 'recomandate';
        }
        $where = ['1=1'];
        $params = [];
        if ($cat) {
            $where[] = 'p.category_id = :cat';
            $params['cat'] = (int)$cat['id'];
        }
        if ($q !== '') {
            $where[] = '(p.name LIKE :q1 OR p.short_description LIKE :q2 OR p.brand LIKE :q3 OR c.name LIKE :q4)';
            foreach (['q1', 'q2', 'q3', 'q4'] as $k) {
                $params[$k] = '%' . $q . '%';
            }
        }
        $products = Shop::publishedProducts(implode(' AND ', $where), $params, self::SORTS[$sortKey][1]);
        $path = $cat ? '/categorie/' . $cat['slug'] : '/produse';

        if ($cat) {
            $seo = $this->seo($cat['h1'] ?: $cat['name'], $cat['intro'] ?: excerpt((string)$cat['body']), [['Produse', '/produse'], [$cat['name'], $path]]);
            $this->applyMeta($seo, $cat);
            $seo->pageType = 'CollectionPage';
            $seo->schema[] = Seo::faqSchema(json_list($cat['faq']), abs_url($path));
        } else {
            $seo = $this->seo('Cafea italiană online: boabe, cialde, monodoze și espresoare', 'Cumpără online cafea italiană premium: cafea boabe prăjită la foc de lemn, cialde și monodoze Saka, espresoare. Livrare prin curier în 1–3 zile, plată cu cardul sau ramburs.', [['Produse', '/produse']]);
            $seo->pageType = 'CollectionPage';
        }
        if ($q !== '' || $sortKey !== 'recomandate') {
            $seo->noindex = true; // variantele de căutare/sortare nu se indexează; canonicul rămâne pagina curată
            $seo->canonical = abs_url($path);
        }
        $seo->schema[] = Seo::itemList($products, abs_url($path), $cat['name'] ?? 'Produse');
        return $this->view('shop', [
            'cat' => $cat,
            'products' => $products,
            'categories' => Site::categories(),
            'q' => $q,
            'sort' => $sortKey,
            'sorts' => array_map(fn($s) => $s[0], self::SORTS),
            'path' => $path,
        ], $seo);
    }

    public function index(): string
    {
        return $this->listing(null);
    }

    public function category(string $slug): string
    {
        $cat = DB::row('SELECT * FROM categories WHERE slug = ? AND published = 1', [$slug]);
        if (!$cat) {
            \App\Core\App::tryRedirect('/categorie/' . $slug);
            return $this->notFound();
        }
        return $this->listing($cat);
    }

    public function product(string $slug): string
    {
        $p = DB::row('SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.slug = ? AND p.published = 1', [$slug]);
        if (!$p) {
            \App\Core\App::tryRedirect('/produs/' . $slug); // produs redenumit sau scos din catalog
            return $this->notFound();
        }
        $reviews = Settings::get('reviews_enabled') === '1' ? DB::all('SELECT * FROM reviews WHERE product_id = ? AND published = 1 ORDER BY id DESC', [$p['id']]) : [];
        $related = $p['category_id'] ? Shop::publishedProducts('p.category_id = :c AND p.id <> :id', ['c' => $p['category_id'], 'id' => $p['id']], 'p.sort', 4) : [];
        if (count($related) < 4) {
            $ids = array_merge([(int)$p['id']], array_map(fn($r) => (int)$r['id'], $related));
            $more = Shop::publishedProducts('p.id NOT IN (' . implode(',', $ids) . ')', [], 'p.featured DESC, p.sort', 4 - count($related));
            $related = array_merge($related, $more);
        }
        $crumbs = [['Produse', '/produse']];
        if ($p['category_slug']) {
            $crumbs[] = [$p['category_name'], '/categorie/' . $p['category_slug']];
        }
        $crumbs[] = [$p['name'], Shop::url($p)];
        $seo = $this->seo(html_entity_decode($p['name']), excerpt($p['short_description'] . ' ' . $p['description'], 158), $crumbs);
        $this->applyMeta($seo, $p);
        $seo->type = 'product';
        $seo->pageType = 'ItemPage';
        if (!$p['og_image'] && ($img = Shop::image($p))) {
            $seo->image = abs_url(upload_url($img));
            $seo->imageAlt = $p['name'];
        }
        $seo->extraMeta = [
            'product:price:amount' => number_format(Shop::price($p), 2, '.', ''),
            'product:price:currency' => 'RON',
            'product:availability' => Shop::inStock($p) ? 'in stock' : 'out of stock',
            'product:condition' => 'new',
        ];
        if ($p['brand']) {
            $seo->extraMeta['product:brand'] = $p['brand'];
        }
        $seo->schema[] = Seo::product($p, $reviews);
        $seo->schema[] = Seo::faqSchema(json_list($p['faq']), abs_url(Shop::url($p)));
        return $this->view('product', ['p' => $p, 'reviews' => $reviews, 'related' => $related], $seo);
    }
}
