<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\DB;
use App\Core\Sanitizer;
use App\Core\Seo;
use App\Core\Settings;
use App\Core\Site;

final class LocationController extends SiteController
{
    public function index(): string
    {
        $seo = $this->seo(
            'Zone deservite: servicii IT în Mureș, Bistrița-Năsăud și Alba',
            'Intervenții IT la sediu în județele Mureș, Bistrița-Năsăud și Alba: Târgu Mureș, Reghin, Sighișoara, Bistrița, Alba Iulia, Aiud, Blaj, Sebeș și nu numai. Suport remote în toată România.',
            [['Zone', '/zone']]
        );
        $seo->pageType = 'CollectionPage';
        return $this->view('locations', [], $seo);
    }

    public function show(string $slug): string
    {
        $l = DB::row('SELECT * FROM locations WHERE slug = ? AND published = 1', [$slug]);
        if (!$l) {
            return $this->notFound();
        }
        $isCounty = $l['type'] === 'judet';
        $parent = !$isCounty && $l['parent_id'] ? DB::row('SELECT * FROM locations WHERE id = ?', [$l['parent_id']]) : null;
        $countyName = $isCounty ? $l['name'] : ($parent['name'] ?? (string)$l['county_name']);
        $display = $isCounty ? 'județul ' . $l['name'] : $l['name'];

        $crumbs = [['Zone', '/zone']];
        if ($parent) {
            $crumbs[] = ['Județul ' . $parent['name'], '/zone/' . $parent['slug']];
        }
        $crumbs[] = [$isCounty ? 'Județul ' . $l['name'] : $l['name'], '/zone/' . $l['slug']];

        $title = $isCounty ? 'Servicii IT în județul ' . $l['name'] : 'Service și suport IT în ' . $l['name'];
        $desc = $isCounty
            ? "Mentenanță IT, reparații, recuperări de date și securitate cibernetică la sediul firmei tale în județul {$l['name']}. Plus SEO, reclame și agenți AI. Suna la " . Settings::get('phone') . '.'
            : "Firmă IT pentru afaceri din {$l['name']} (jud. {$countyName}): mentenanță, reparații la sediu, recuperare date, securitate, SEO și automatizări AI. Ofertă gratuită.";
        $seo = $this->seo($title, $desc, $crumbs);
        $seo->image = abs_url('/og/zona/' . $l['slug'] . '.png');
        $this->applyMeta($seo, $l);
        $url = abs_url('/zone/' . $l['slug']);

        $onsite = DB::all('SELECT slug, title, icon, excerpt FROM services WHERE published = 1 AND onsite = 1 ORDER BY sort');
        $remote = DB::all('SELECT slug, title, icon, excerpt FROM services WHERE published = 1 AND onsite = 0 ORDER BY sort');
        $cities = $isCounty ? DB::all('SELECT slug, name FROM locations WHERE parent_id = ? AND published = 1 ORDER BY sort, name', [$l['id']]) : [];
        $siblings = $parent ? DB::all('SELECT slug, name FROM locations WHERE parent_id = ? AND id <> ? AND published = 1 ORDER BY sort, name', [$parent['id'], $l['id']]) : [];

        $faq = json_list($l['faq']);
        if (!$faq) {
            $faq = $this->defaultFaq($display, $countyName);
        }
        $place = $isCounty
            ? ['@type' => 'AdministrativeArea', 'name' => 'Județul ' . $l['name'], 'containedInPlace' => ['@type' => 'Country', 'name' => 'România']]
            : ['@type' => 'City', 'name' => $l['name'], 'containedInPlace' => ['@type' => 'AdministrativeArea', 'name' => 'Județul ' . $countyName]];
        if ($l['lat'] && $l['lng']) {
            $place['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float)$l['lat'], 'longitude' => (float)$l['lng']];
        }
        $seo->schema[] = [
            '@type' => 'Service',
            '@id' => $url . '#serviciu',
            'name' => $title,
            'serviceType' => 'Servicii IT și suport tehnic',
            'provider' => ['@id' => Seo::orgId()],
            'areaServed' => $place,
            'url' => $url,
            'hasOfferCatalog' => ['@type' => 'OfferCatalog', 'name' => 'Servicii la sediu în ' . $display, 'itemListElement' => array_map(fn($s) => ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => $s['title'], 'url' => abs_url('/servicii/' . $s['slug'])]], $onsite)],
        ];
        if ($f = Seo::faqSchema($faq, $url)) {
            $seo->schema[] = $f;
        }
        [$body] = Sanitizer::withToc((string)$l['body']);

        return $this->view('location', [
            'l' => $l,
            'isCounty' => $isCounty,
            'parent' => $parent,
            'countyName' => $countyName,
            'display' => $display,
            'title' => $title,
            'crumbs' => $crumbs,
            'body' => $body,
            'onsite' => $onsite,
            'remote' => $remote,
            'cities' => $cities,
            'siblings' => $siblings,
            'faq' => $faq,
        ], $seo);
    }

    private function defaultFaq(string $display, string $county): array
    {
        $phone = (string)Settings::get('phone');
        return [
            ['q' => "Veniți la sediul firmei în $display?", 'a' => "Da. Pentru clienții din $display facem intervenții la sediu: instalări, reparații, rețelistică, servere și mentenanță periodică. Tot ce se poate rezolva remote rezolvăm imediat, fără deplasare."],
            ['q' => 'Cât de repede puteți interveni?', 'a' => "Problemele urgente le preluăm remote de regulă în aceeași zi, adesea în câteva minute. Pentru deplasări în județul $county programăm intervenția cât mai repede; clienții cu abonament au prioritate și timp de răspuns garantat prin contract."],
            ['q' => 'Aveți abonamente de mentenanță IT pentru firme mici?', 'a' => 'Da, abonamentele sunt gândite pentru IMM-uri: plătești lunar un cost fix și ai inclus suportul remote, monitorizarea, backup-ul verificat și un număr de ore de intervenție la sediu.'],
            ['q' => 'Cum cer o ofertă?', 'a' => "Completează formularul de pe această pagină sau sună-ne la $phone. Discuția inițială și oferta sunt gratuite."],
        ];
    }
}
