<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\DB;
use App\Core\Seo;
use App\Core\Settings;

final class HomeController extends SiteController
{
    public function index(): string
    {
        $seo = new Seo();
        $seo->title = (string)Settings::get('seo_home_title');
        $seo->rawTitle = true;
        $seo->description = (string)Settings::get('seo_home_description');
        $seo->canonical = abs_url('/');
        $faq = Settings::json('home_faq');
        $seo->schema[] = [
            '@type' => 'OfferCatalog', '@id' => abs_url('/') . '#abonamente', 'name' => 'Abonamente VITIM pentru companii',
            'itemListElement' => [[
                '@type' => 'Offer', 'name' => 'Abonament VITIM – departament extern de IT & AI',
                'description' => 'Mentenanță și suport IT, securitate, backup, automatizări și AI, website și marketing, într-un singur abonament lunar.',
                'seller' => ['@id' => Seo::orgId()],
                'priceSpecification' => ['@type' => 'UnitPriceSpecification', 'price' => (int)preg_replace('/\D/', '', (string)Settings::get('pricing_from', '1000')), 'priceCurrency' => 'RON', 'unitText' => 'lună', 'description' => 'de la'],
            ]],
        ];
        if ($f = Seo::faqSchema($faq, abs_url('/'))) {
            $seo->schema[] = $f;
        }
        return $this->view('home', [
            'testimonials' => DB::all('SELECT * FROM testimonials WHERE published = 1 ORDER BY sort, id DESC LIMIT 6'),
            'posts' => DB::all("SELECT slug, title, excerpt, cover, published_at, body FROM posts WHERE status = 'published' AND published_at <= ? ORDER BY published_at DESC LIMIT 3", [DB::now()]),
            'faq' => $faq,
            'projects' => DB::all('SELECT * FROM projects WHERE published = 1 AND featured = 1 ORDER BY sort, id DESC LIMIT 4'),
            'clients' => DB::all("SELECT slug, client, logo FROM projects WHERE published = 1 AND own = 0 AND client IS NOT NULL AND client <> '' ORDER BY sort, id DESC"),
        ], $seo);
    }
}
