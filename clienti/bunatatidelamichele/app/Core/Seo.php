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
    public string $imageAlt = '';
    public string $type = 'website';
    public bool $noindex = false;
    public array $breadcrumbs = [];
    public array $schema = [];
    public ?string $published = null;
    public ?string $modified = null;
    public string $pageType = 'WebPage';
    public array $extraMeta = [];
    public ?string $prev = null;
    public ?string $next = null;

    public static function orgId(): string
    {
        return abs_url('/') . '#organizatie';
    }

    public function fullTitle(): string
    {
        if ($this->rawTitle || $this->title === '') {
            return $this->title ?: (string)Settings::get('seo_home_title');
        }
        $suffix = (string)Settings::get('seo_title_suffix', '');
        $t = $this->title;
        if ($suffix !== '' && mb_strlen($t . $suffix) <= 65 && !str_contains($t, trim($suffix, ' |'))) {
            $t .= $suffix;
        }
        return $t;
    }

    public function head(): string
    {
        $title = $this->fullTitle();
        $desc = $this->description ?: (string)Settings::get('seo_home_description');
        $desc = mb_substr(trim((string)preg_replace('/\s+/', ' ', strip_tags($desc))), 0, 300);
        $canonical = $this->canonical ?: abs_url(strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/');
        $image = $this->image ?: ((string)Settings::get('seo_default_og') ? abs_url(upload_url((string)Settings::get('seo_default_og'))) : abs_url('/og/home/index.png'));
        $robots = ($this->noindex || Settings::get('seo_noindex_site') === '1')
            ? 'noindex, follow'
            : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
        $brand = (string)Settings::get('brand_name', 'Bunătăți de la Michele');

        $h = [];
        $h[] = '<title>' . e($title) . '</title>';
        $h[] = '<meta name="description" content="' . e($desc) . '">';
        $h[] = '<meta name="robots" content="' . $robots . '">';
        $h[] = '<link rel="canonical" href="' . e($canonical) . '">';
        if ($this->prev) {
            $h[] = '<link rel="prev" href="' . e($this->prev) . '">';
        }
        if ($this->next) {
            $h[] = '<link rel="next" href="' . e($this->next) . '">';
        }
        $h[] = '<link rel="alternate" hreflang="ro-RO" href="' . e($canonical) . '">';
        $h[] = '<meta property="og:locale" content="ro_RO">';
        $h[] = '<meta property="og:type" content="' . e($this->type) . '">';
        $h[] = '<meta property="og:site_name" content="' . e($brand) . '">';
        $h[] = '<meta property="og:title" content="' . e($this->title ?: $title) . '">';
        $h[] = '<meta property="og:description" content="' . e($desc) . '">';
        $h[] = '<meta property="og:url" content="' . e($canonical) . '">';
        $h[] = '<meta property="og:image" content="' . e($image) . '">';
        $h[] = '<meta property="og:image:alt" content="' . e($this->imageAlt ?: ($this->title ?: $brand)) . '">';
        foreach ($this->extraMeta as $prop => $content) {
            $h[] = '<meta property="' . e($prop) . '" content="' . e($content) . '">';
        }
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
        $sameAs = array_values(array_filter([$s('social_facebook'), $s('social_instagram'), $s('social_tiktok'), $s('social_youtube'), $s('google_business_url')]));
        $logo = $s('logo') ? abs_url(upload_url($s('logo'))) : abs_url('/assets/img/logo.png');
        $org = [
            '@type' => 'OnlineStore',
            '@id' => self::orgId(),
            'name' => $s('brand_name', 'Bunătăți de la Michele'),
            'legalName' => $s('company_name'),
            'description' => $s('seo_home_description'),
            'url' => abs_url('/'),
            'logo' => ['@type' => 'ImageObject', 'url' => $logo],
            'image' => $logo,
            'email' => $s('email'),
            'currenciesAccepted' => 'RON',
            'paymentAccepted' => 'Card, Ramburs',
            'areaServed' => ['@type' => 'Country', 'name' => 'România'],
            'knowsLanguage' => 'ro',
            'contactPoint' => array_filter([
                '@type' => 'ContactPoint',
                'contactType' => 'customer service',
                'email' => $s('email'),
                'telephone' => $s('phone') ? preg_replace('/[^0-9+]/', '', str_starts_with($s('phone'), '07') ? '+4' . $s('phone') : $s('phone')) : null,
                'availableLanguage' => 'Romanian',
            ]),
            'hasMerchantReturnPolicy' => self::returnPolicy(),
        ];
        if ($s('phone')) {
            $org['telephone'] = preg_replace('/[^0-9+]/', '', str_starts_with($s('phone'), '07') ? '+4' . $s('phone') : $s('phone'));
        }
        $addr = array_filter(['@type' => 'PostalAddress', 'streetAddress' => $s('company_address'), 'addressLocality' => $s('company_city'), 'addressRegion' => $s('company_county'), 'postalCode' => $s('company_postal'), 'addressCountry' => 'RO']);
        if (count($addr) > 2) {
            $org['address'] = $addr;
        }
        if ($sameAs) {
            $org['sameAs'] = $sameAs;
        }
        if ($s('company_cui')) {
            $org['taxID'] = $s('company_cui');
            $org['vatID'] = $s('company_cui');
        }
        return $org;
    }

    public static function returnPolicy(): array
    {
        return [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => 'RO',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => (int)Settings::get('return_days', '14'),
            'returnMethod' => 'https://schema.org/ReturnByMail',
            'returnFees' => 'https://schema.org/ReturnFeesCustomerResponsibility',
            'merchantReturnLink' => abs_url('/politica-de-retur'),
        ];
    }

    public static function shippingDetails(): array
    {
        $cost = (float)Settings::get('shipping_cost', '0');
        preg_match_all('/\d+/', (string)Settings::get('delivery_time', '1-3'), $m);
        $min = (int)($m[0][0] ?? 1);
        $max = (int)($m[0][1] ?? $min + 2);
        return [
            '@type' => 'OfferShippingDetails',
            'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => number_format($cost, 2, '.', ''), 'currency' => 'RON'],
            'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'RO'],
            'deliveryTime' => [
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => ['@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY'],
                'transitTime' => ['@type' => 'QuantitativeValue', 'minValue' => $min, 'maxValue' => max($min, $max), 'unitCode' => 'DAY'],
            ],
        ];
    }

    /** Date structurate Product + Offer (rezultate bogate în Google: preț, stoc, livrare, retur). */
    public static function product(array $p, array $reviews = []): array
    {
        $url = abs_url(Shop::url($p));
        $imgs = array_map(fn($i) => abs_url(upload_url($i)), Shop::images($p));
        $offer = [
            '@type' => 'Offer',
            'url' => $url,
            'priceCurrency' => 'RON',
            'price' => number_format(Shop::price($p), 2, '.', ''),
            'availability' => Shop::inStock($p) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
            'seller' => ['@id' => self::orgId()],
            'shippingDetails' => self::shippingDetails(),
            'hasMerchantReturnPolicy' => self::returnPolicy(),
        ];
        if (Shop::onSale($p) && !empty($p['sale_until'])) {
            $offer['priceValidUntil'] = date('Y-m-d', (int)strtotime($p['sale_until'] . ' UTC'));
        }
        $prod = [
            '@type' => 'Product',
            '@id' => $url . '#produs',
            'name' => html_entity_decode((string)$p['name'], ENT_QUOTES, 'UTF-8'),
            'description' => excerpt((string)($p['meta_description'] ?: ($p['short_description'] . ' ' . $p['description'])), 500),
            'url' => $url,
            'image' => $imgs,
            'offers' => $offer,
        ];
        if ($p['brand']) {
            $prod['brand'] = ['@type' => 'Brand', 'name' => $p['brand']];
        }
        if ($p['sku']) {
            $prod['sku'] = $p['sku'];
        }
        if ($p['gtin']) {
            $prod['gtin'] = $p['gtin'];
        }
        if (!empty($p['category_name'])) {
            $prod['category'] = $p['category_name'];
        }
        if ((int)$p['weight_g'] > 0) {
            $prod['weight'] = ['@type' => 'QuantitativeValue', 'value' => (int)$p['weight_g'], 'unitCode' => 'GRM'];
        }
        if (count($reviews) > 0) {
            $prod['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1), 'reviewCount' => count($reviews), 'bestRating' => 5];
            $prod['review'] = array_map(fn($r) => ['@type' => 'Review', 'author' => ['@type' => 'Person', 'name' => $r['name']], 'reviewRating' => ['@type' => 'Rating', 'ratingValue' => (int)$r['rating'], 'bestRating' => 5], 'reviewBody' => $r['text'], 'datePublished' => substr((string)$r['created_at'], 0, 10)], array_slice($reviews, 0, 10));
        }
        return $prod;
    }

    public static function itemList(array $products, string $url, string $name): array
    {
        $items = [];
        foreach (array_values($products) as $i => $p) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'url' => abs_url(Shop::url($p)), 'name' => $p['name']];
        }
        return ['@type' => 'ItemList', '@id' => $url . '#lista', 'name' => $name, 'numberOfItems' => count($items), 'itemListElement' => $items];
    }

    private function jsonLd(string $canonical, string $title, string $desc, string $image): string
    {
        $graph = [];
        $graph[] = self::organization();
        $graph[] = [
            '@type' => 'WebSite',
            '@id' => abs_url('/') . '#website',
            'url' => abs_url('/'),
            'name' => (string)Settings::get('brand_name'),
            'inLanguage' => 'ro-RO',
            'publisher' => ['@id' => self::orgId()],
            'potentialAction' => ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => abs_url('/produse') . '?q={search_term_string}'], 'query-input' => 'required name=search_term_string'],
        ];
        $page = [
            '@type' => $this->pageType,
            '@id' => $canonical . '#pagina',
            'url' => $canonical,
            'name' => $title,
            'description' => $desc,
            'inLanguage' => 'ro-RO',
            'isPartOf' => ['@id' => abs_url('/') . '#website'],
            'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $image],
        ];
        if ($this->modified) {
            $page['dateModified'] = self::iso($this->modified);
        }
        if ($this->breadcrumbs) {
            $items = [];
            $pos = 1;
            foreach (array_merge([['Acasă', '/']], $this->breadcrumbs) as [$name, $path]) {
                $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $name, 'item' => abs_url($path)];
            }
            $graph[] = ['@type' => 'BreadcrumbList', '@id' => $canonical . '#breadcrumb', 'itemListElement' => $items];
            $page['breadcrumb'] = ['@id' => $canonical . '#breadcrumb'];
        }
        $graph[] = $page;
        foreach ($this->schema as $s) {
            if ($s) {
                $graph[] = $s;
            }
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
        if ($key === '' || !$urls || config('debug') || !str_starts_with(abs_url('/'), 'https://')) {
            return;
        }
        $host = parse_url(abs_url('/'), PHP_URL_HOST);
        $payload = json_encode(['host' => $host, 'key' => $key, 'keyLocation' => abs_url('/' . $key . '.txt'), 'urlList' => array_values($urls)]);
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json; charset=utf-8\r\n", 'content' => $payload, 'timeout' => 4, 'ignore_errors' => true]]);
        @file_get_contents('https://api.indexnow.org/indexnow', false, $ctx);
    }
}
