<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Meta-taguri, Open Graph și date structurate (JSON-LD, schema.org) pentru fiecare pagină.
 */
final class Seo
{
    public string $title = '';
    public bool $rawTitle = false;
    public string $description = '';
    public string $canonical = '';
    public string $image = '';
    public string $type = 'website';
    public bool $noindex = false;
    public array $breadcrumbs = [];
    public array $schema = [];
    public ?string $published = null;
    public ?string $modified = null;
    public string $pageType = 'WebPage';

    public static function orgId(): string
    {
        return abs_url('/') . '#organizatie';
    }

    public function fullTitle(): string
    {
        if ($this->rawTitle || $this->title === '') {
            return $this->title ?: (string)Settings::get('seo_home_title');
        }
        $suffix = (string)Settings::get('seo_title_suffix', ' | VITIM');
        $t = $this->title;
        if (mb_strlen($t . $suffix) <= 65 && !str_contains($t, trim($suffix, ' |'))) {
            $t .= $suffix;
        }
        return $t;
    }

    public function head(): string
    {
        $title = $this->fullTitle();
        $desc = $this->description ?: (string)Settings::get('seo_home_description');
        $desc = mb_substr(trim((string)preg_replace('/\s+/', ' ', $desc)), 0, 300);
        $canonical = $this->canonical ?: abs_url(strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/');
        $image = $this->image ?: ((string)Settings::get('seo_default_og') ? abs_url(upload_url((string)Settings::get('seo_default_og'))) : abs_url('/og/home/index.png'));
        $robots = ($this->noindex || Settings::get('seo_noindex_site') === '1')
            ? 'noindex, follow'
            : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
        $brand = (string)Settings::get('brand_name', 'VITIM');

        $h = [];
        $h[] = '<title>' . e($title) . '</title>';
        $h[] = '<meta name="description" content="' . e($desc) . '">';
        $h[] = '<meta name="robots" content="' . $robots . '">';
        $h[] = '<link rel="canonical" href="' . e($canonical) . '">';
        $h[] = '<link rel="alternate" hreflang="ro-RO" href="' . e($canonical) . '">';
        $h[] = '<meta property="og:locale" content="ro_RO">';
        $h[] = '<meta property="og:type" content="' . e($this->type) . '">';
        $h[] = '<meta property="og:site_name" content="' . e($brand) . '">';
        $h[] = '<meta property="og:title" content="' . e($this->title ?: $title) . '">';
        $h[] = '<meta property="og:description" content="' . e($desc) . '">';
        $h[] = '<meta property="og:url" content="' . e($canonical) . '">';
        $h[] = '<meta property="og:image" content="' . e($image) . '">';
        $h[] = '<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">';
        $h[] = '<meta property="og:image:alt" content="' . e($this->title ?: $brand) . '">';
        if ($this->type === 'article') {
            if ($this->published) {
                $h[] = '<meta property="article:published_time" content="' . e(self::iso($this->published)) . '">';
            }
            if ($this->modified) {
                $h[] = '<meta property="article:modified_time" content="' . e(self::iso($this->modified)) . '">';
            }
        }
        $h[] = '<meta name="twitter:card" content="summary_large_image">';
        $h[] = '<meta name="twitter:title" content="' . e($this->title ?: $title) . '">';
        $h[] = '<meta name="twitter:description" content="' . e($desc) . '">';
        $h[] = '<meta name="twitter:image" content="' . e($image) . '">';
        if ($v = Settings::get('seo_google_verification')) {
            $h[] = '<meta name="google-site-verification" content="' . e($v) . '">';
        }
        if ($v = Settings::get('seo_bing_verification')) {
            $h[] = '<meta name="msvalidate.01" content="' . e($v) . '">';
        }
        $h[] = '<meta name="geo.region" content="RO-MS"><meta name="geo.placename" content="' . e((string)Settings::get('company_city')) . '">';
        if (($lat = Settings::get('company_lat')) && ($lng = Settings::get('company_lng'))) {
            $h[] = '<meta name="geo.position" content="' . e($lat . ';' . $lng) . '"><meta name="ICBM" content="' . e($lat . ', ' . $lng) . '">';
        }
        $h[] = '<script type="application/ld+json">' . $this->jsonLd($canonical, $title, $desc, $image) . '</script>';
        return implode("\n", $h);
    }

    public static function iso(string $dt): string
    {
        $ts = strtotime($dt . ' UTC');
        return $ts ? date('c', $ts) : $dt;
    }

    public static function organization(): array
    {
        $s = fn($k, $d = '') => (string)Settings::get($k, $d);
        $sameAs = array_values(array_filter([
            $s('social_facebook'), $s('social_instagram'), $s('social_linkedin'), $s('social_youtube'), $s('social_tiktok'), $s('google_business_url'),
        ]));
        $areas = [];
        foreach (DB::all("SELECT name, type, county_name FROM locations WHERE published = 1 ORDER BY sort, name") as $l) {
            $areas[] = $l['type'] === 'judet'
                ? ['@type' => 'AdministrativeArea', 'name' => 'Județul ' . $l['name']]
                : ['@type' => 'City', 'name' => $l['name']];
        }
        $areas[] = ['@type' => 'Country', 'name' => 'România'];
        $hours = [];
        foreach (Settings::json('hours_schema') as $hr) {
            $hours[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $hr['days'] ?? [], 'opens' => $hr['opens'] ?? '', 'closes' => $hr['closes'] ?? ''];
        }
        $logo = $s('logo') ? abs_url(upload_url($s('logo'))) : abs_url('/assets/img/logo.png');
        $org = [
            '@type' => ['ProfessionalService', 'LocalBusiness'],
            '@id' => self::orgId(),
            'name' => $s('brand_name', 'VITIM'),
            'legalName' => $s('company_name'),
            'description' => $s('seo_home_description'),
            'url' => abs_url('/'),
            'logo' => ['@type' => 'ImageObject', 'url' => $logo],
            'image' => $logo,
            'telephone' => preg_replace('/[^0-9+]/', '', str_starts_with($s('phone'), '07') ? '+4' . $s('phone') : $s('phone')),
            'email' => $s('email'),
            'priceRange' => '$$',
            'currenciesAccepted' => 'RON, EUR',
            'paymentAccepted' => 'Transfer bancar, Card',
            'areaServed' => $areas,
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $s('company_address'),
                'addressLocality' => $s('company_city'),
                'addressRegion' => $s('company_county'),
                'postalCode' => $s('company_postal'),
                'addressCountry' => 'RO',
            ]),
            'knowsLanguage' => ['ro', 'en'],
        ];
        if ($s('company_lat') && $s('company_lng')) {
            $org['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float)$s('company_lat'), 'longitude' => (float)$s('company_lng')];
        }
        if ($hours) {
            $org['openingHoursSpecification'] = $hours;
        }
        if ($sameAs) {
            $org['sameAs'] = $sameAs;
        }
        if ($s('company_cui')) {
            $org['taxID'] = $s('company_cui');
            $org['vatID'] = $s('company_cui');
        }
        if ($s('founded_year')) {
            $org['foundingDate'] = $s('founded_year');
        }
        if ($s('google_maps_url')) {
            $org['hasMap'] = $s('google_maps_url');
        }
        $catalog = [];
        foreach (DB::all('SELECT title, slug, excerpt FROM services WHERE published = 1 ORDER BY sort') as $sv) {
            $catalog[] = ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => $sv['title'], 'url' => abs_url('/servicii/' . $sv['slug'])]];
        }
        if ($catalog) {
            $org['hasOfferCatalog'] = ['@type' => 'OfferCatalog', 'name' => 'Servicii ' . $s('brand_name', 'VITIM'), 'itemListElement' => $catalog];
        }
        $rating = DB::row('SELECT COUNT(*) AS n, AVG(rating) AS avg FROM testimonials WHERE published = 1 AND rating > 0');
        if ($rating && (int)$rating['n'] >= 3) {
            $org['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => round((float)$rating['avg'], 1), 'reviewCount' => (int)$rating['n'], 'bestRating' => 5];
        }
        return $org;
    }

    private function jsonLd(string $canonical, string $title, string $desc, string $image): string
    {
        $graph = [];
        $graph[] = self::organization();
        $graph[] = [
            '@type' => 'WebSite',
            '@id' => abs_url('/') . '#website',
            'url' => abs_url('/'),
            'name' => (string)Settings::get('brand_name', 'VITIM'),
            'inLanguage' => 'ro-RO',
            'publisher' => ['@id' => self::orgId()],
        ];
        $page = [
            '@type' => $this->pageType,
            '@id' => $canonical . '#pagina',
            'url' => $canonical,
            'name' => $title,
            'description' => $desc,
            'inLanguage' => 'ro-RO',
            'isPartOf' => ['@id' => abs_url('/') . '#website'],
            'about' => ['@id' => self::orgId()],
            'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $image],
        ];
        if ($this->modified) {
            $page['dateModified'] = self::iso($this->modified);
        }
        if ($this->breadcrumbs) {
            $items = [];
            $pos = 1;
            $all = array_merge([['Acasă', '/']], $this->breadcrumbs);
            foreach ($all as [$name, $path]) {
                $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $name, 'item' => abs_url($path)];
            }
            $graph[] = ['@type' => 'BreadcrumbList', '@id' => $canonical . '#breadcrumb', 'itemListElement' => $items];
            $page['breadcrumb'] = ['@id' => $canonical . '#breadcrumb'];
        }
        $graph[] = $page;
        foreach ($this->schema as $s) {
            $graph[] = $s;
        }
        return json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    }

    public static function faqSchema(array $faq, string $url): ?array
    {
        $items = [];
        foreach ($faq as $f) {
            if (!empty($f['q']) && !empty($f['a'])) {
                $items[] = ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags((string)$f['a'])]];
            }
        }
        return $items ? ['@type' => 'FAQPage', '@id' => $url . '#faq', 'mainEntity' => $items] : null;
    }

    /** Anunță Bing/Yandex (IndexNow) că o pagină s-a schimbat. */
    public static function indexNow(array $urls): void
    {
        $key = (string)Settings::get('seo_indexnow_key');
        if ($key === '' || !$urls || config('debug')) {
            return;
        }
        $host = parse_url(abs_url('/'), PHP_URL_HOST);
        $payload = json_encode(['host' => $host, 'key' => $key, 'keyLocation' => abs_url('/' . $key . '.txt'), 'urlList' => array_values($urls)]);
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json; charset=utf-8\r\n", 'content' => $payload, 'timeout' => 4, 'ignore_errors' => true]]);
        @file_get_contents('https://api.indexnow.org/indexnow', false, $ctx);
    }
}
