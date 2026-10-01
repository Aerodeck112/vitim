<?php

declare(strict_types=1);

namespace App\Reports;

/**
 * Serviciile VITIM și cifrele raportate lunar pentru fiecare. Până la conectarea cu Google, cifrele SEO / Ads / GBP
 * se completează de echipă (din Search Console, Google Ads, Business Profile); mentenanța se calculează automat.
 */
final class ServiceCatalog
{
    /** serviciu => [etichetă, categoria din jurnal, metrici: cheie => [etichetă, unitate, „up” = mai mult e mai bine]] */
    public const SERVICES = [
        'maintenance' => ['Mentenanță și securitate', ['updates', 'security', 'backup', 'repair'], []],
        'seo' => ['SEO', ['seo', 'content'], [
            'clicks' => ['Click-uri din Google', '', 'up'],
            'impressions' => ['Afișări în Google', '', 'up'],
            'avg_position' => ['Poziție medie', '', 'down'],
            'keywords_top10' => ['Cuvinte cheie în top 10', '', 'up'],
            'pages_optimized' => ['Pagini optimizate', '', 'up'],
        ]],
        'google_ads' => ['Google Ads', ['ads'], [
            'spend' => ['Buget cheltuit', 'lei', 'neutral'],
            'impressions' => ['Afișări', '', 'up'],
            'clicks' => ['Click-uri', '', 'up'],
            'conversions' => ['Conversii (cereri, apeluri, comenzi)', '', 'up'],
            'calls' => ['Apeluri din anunțuri', '', 'up'],
        ]],
        'google_business' => ['Google Business Profile', ['gbp'], [
            'views' => ['Vizualizări profil', '', 'up'],
            'calls' => ['Apeluri', '', 'up'],
            'directions' => ['Cereri de indicații', '', 'up'],
            'website_clicks' => ['Click-uri spre site', '', 'up'],
            'reviews_new' => ['Recenzii noi', '', 'up'],
            'rating' => ['Nota medie', '★', 'up'],
            'posts' => ['Postări publicate', '', 'up'],
        ]],
    ];

    public static function label(string $service): string
    {
        return self::SERVICES[$service][0] ?? $service;
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function metrics(string $service): array
    {
        return self::SERVICES[$service][2] ?? [];
    }

    /** @return list<string> */
    public static function categories(string $service): array
    {
        return self::SERVICES[$service][1] ?? [];
    }
}
