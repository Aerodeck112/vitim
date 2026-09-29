<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\DB;
use App\Core\Sanitizer;
use App\Core\Seo;
use App\Core\Settings;
use App\Core\Site;

final class ServiceController extends SiteController
{
    public function index(): string
    {
        $seo = $this->seo(
            'Servicii IT, securitate, marketing și AI pentru firme',
            'Mentenanță IT, suport remote, reparații, recuperări de date, securitate cibernetică, SEO, Google & Meta Ads, Google Workspace, agenți AI și automatizări. Mureș, Bistrița-Năsăud, Alba și remote în toată țara.',
            [['Servicii', '/servicii']]
        );
        $seo->pageType = 'CollectionPage';
        $items = [];
        $pos = 1;
        foreach (Site::services() as $s) {
            $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'url' => abs_url('/servicii/' . $s['slug']), 'name' => $s['title']];
        }
        $seo->schema[] = ['@type' => 'ItemList', 'name' => 'Servicii VITIM', 'itemListElement' => $items];
        return $this->view('services', ['groups' => Site::servicesByCategory()], $seo);
    }

    public function show(string $slug): string
    {
        $s = DB::row('SELECT * FROM services WHERE slug = ? AND published = 1', [$slug]);
        if (!$s) {
            return $this->notFound();
        }
        $cats = Site::categories();
        $cat = $cats[$s['category']] ?? ['name' => 'Servicii', 'short' => 'Servicii'];
        $url = abs_url('/servicii/' . $s['slug']);
        $seo = $this->seo($s['title'], $s['excerpt'], [['Servicii', '/servicii'], [$s['title'], '/servicii/' . $s['slug']]]);
        $seo->image = abs_url('/og/serviciu/' . $s['slug'] . '.png');
        $this->applyMeta($seo, $s);

        $faq = json_list($s['faq']);
        $areas = [];
        foreach (Site::counties() as $c) {
            $areas[] = ['@type' => 'AdministrativeArea', 'name' => 'Județul ' . $c['name']];
        }
        if (!$s['onsite']) {
            $areas[] = ['@type' => 'Country', 'name' => 'România'];
        }
        $service = [
            '@type' => 'Service',
            '@id' => $url . '#serviciu',
            'name' => $s['title'],
            'serviceType' => $s['title'],
            'description' => $s['excerpt'],
            'url' => $url,
            'provider' => ['@id' => Seo::orgId()],
            'areaServed' => $areas,
            'category' => $cat['name'],
            'availableChannel' => ['@type' => 'ServiceChannel', 'servicePhone' => (string)Settings::get('phone'), 'serviceUrl' => abs_url('/contact')],
        ];
        $features = json_list($s['features']);
        if ($features) {
            $service['hasOfferCatalog'] = [
                '@type' => 'OfferCatalog',
                'name' => $s['title'],
                'itemListElement' => array_map(fn($f) => ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => $f['title'] ?? '']], $features),
            ];
        }
        if (!empty($s['price_from']) && preg_match('/(\d[\d.]*)/', (string)$s['price_from'], $pm)) {
            $service['offers'] = ['@type' => 'Offer', 'priceCurrency' => 'RON', 'price' => str_replace('.', '', $pm[1]), 'priceSpecification' => ['@type' => 'PriceSpecification', 'priceCurrency' => 'RON', 'minPrice' => str_replace('.', '', $pm[1])]];
        }
        $seo->schema[] = $service;
        if ($f = Seo::faqSchema($faq, $url)) {
            $seo->schema[] = $f;
        }

        [$body, $toc] = Sanitizer::withToc((string)$s['body']);
        $related = DB::all('SELECT slug, title, icon, excerpt, image FROM services WHERE published = 1 AND category = ? AND id <> ? ORDER BY sort LIMIT 3', [$s['category'], $s['id']]);
        if (count($related) < 3) {
            $more = DB::all('SELECT slug, title, icon, excerpt, image FROM services WHERE published = 1 AND category <> ? AND id <> ? ORDER BY featured DESC, sort LIMIT ' . (3 - count($related)), [$s['category'], $s['id']]);
            $related = array_merge($related, $more);
        }
        $testimonials = DB::all('SELECT * FROM testimonials WHERE published = 1 AND (service_id = ? OR service_id IS NULL) ORDER BY service_id DESC, sort LIMIT 3', [$s['id']]);
        $projects = DB::all('SELECT slug, title, summary, client FROM projects WHERE published = 1 AND service_id = ? ORDER BY sort LIMIT 3', [$s['id']]);

        return $this->view('service', [
            's' => $s,
            'cat' => $cat,
            'body' => $body,
            'toc' => $toc,
            'features' => $features,
            'process' => json_list($s['process']) ?: Settings::json('home_process'),
            'faq' => $faq,
            'related' => $related,
            'testimonials' => $testimonials,
            'projects' => $projects,
        ], $seo);
    }
}
