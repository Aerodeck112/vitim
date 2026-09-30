<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Seo;
use App\Core\Settings;
use App\Core\Shop;
use App\Core\Site;

final class HomeController extends SiteController
{
    public function index(): string
    {
        $featured = Shop::publishedProducts('p.featured = 1', [], 'p.sort, p.name', 8);
        if (count($featured) < 4) {
            $featured = Shop::publishedProducts('1=1', [], 'p.featured DESC, p.sort, p.name', 8);
        }
        $seo = new Seo();
        $seo->title = (string)Settings::get('seo_home_title');
        $seo->rawTitle = true;
        $seo->description = (string)Settings::get('seo_home_description');
        $seo->canonical = abs_url('/');
        $seo->modified = (string)\App\Core\DB::val('SELECT MAX(updated_at) FROM products');
        if ($img = (string)Settings::get('home_hero_image')) {
            $seo->image = abs_url(upload_url($img));
        }
        $faq = Settings::json('faq');
        $seo->schema[] = Seo::itemList($featured, abs_url('/'), 'Produse recomandate');
        $seo->schema[] = Seo::faqSchema($faq, abs_url('/'));
        return $this->view('home', [
            'featured' => $featured,
            'categories' => Site::categories(),
            'slides' => Settings::json('home_slides'),
            'stats' => Settings::json('home_stats'),
            'process' => Settings::json('home_process'),
            'faq' => $faq,
            'catIcons' => Settings::json('home_cat_icons'),
        ], $seo);
    }
}
