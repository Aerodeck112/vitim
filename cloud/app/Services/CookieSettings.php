<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\Site;

/**
 * Bannerul de cookie-uri al unui site (în sites.cookie_config) și lista cookie-urilor afișată vizitatorilor.
 * Regulile urmate (Legea 506/2004 art. 4 alin. 5, GDPR, ghidurile EDPB / ANSPDCP):
 * - doar cookie-urile strict necesare pornesc fără acord; restul așteaptă alegerea vizitatorului;
 * - „Accept toate” și „Refuz toate” sunt la fel de vizibile, nimic nu e pre-bifat;
 * - acordul se poate retrage oricând (butonul flotant sau un link #vitim-cookies), iar alegerea se păstrează ca dovadă;
 * - la o schimbare importantă a listei, versiunea crește și vizitatorii sunt întrebați din nou.
 */
final class CookieSettings
{
    public const CATEGORIES = [
        'necessary' => ['Strict necesare', 'Fac site-ul să funcționeze: coșul de cumpărături, autentificarea, securitatea, memorarea alegerii tale despre cookie-uri și a conversației începute în chat. Nu pot fi dezactivate.'],
        'preferences' => ['Preferințe', 'Rețin setări pe care le alegi (de exemplu limba sau regiunea) și permit funcții precum hărțile încorporate.'],
        'statistics' => ['Statistici', 'Ne arată, anonim sau agregat, cum este folosit site-ul (pagini vizitate, timp petrecut), ca să-l putem îmbunătăți.'],
        'marketing' => ['Marketing', 'Permit reclame relevante pe alte site-uri și rețele sociale și măsurarea campaniilor; ne ajută să te recunoaștem când revii dintr-un email trimis de noi, ca să-ți arătăm produsele potrivite.'],
    ];

    /** Servicii externe frecvente: [nume, furnizor, categorie, [[cookie, durată, scop]]]. */
    public const SERVICES = [
        'ga4' => ['Google Analytics', 'Google Ireland Ltd.', 'statistics', [['_ga', '2 ani', 'deosebește vizitatorii'], ['_ga_*', '2 ani', 'păstrează starea sesiunii']]],
        'gads' => ['Google Ads', 'Google Ireland Ltd.', 'marketing', [['_gcl_au', '90 de zile', 'măsoară conversiile din reclame'], ['IDE (doubleclick.net)', '13 luni', 'reclame personalizate']]],
        'gtm' => ['Google Tag Manager', 'Google Ireland Ltd.', 'necessary', [['—', '—', 'încarcă celelalte scripturi; nu setează cookie-uri proprii']]],
        'meta' => ['Meta Pixel (Facebook, Instagram)', 'Meta Platforms Ireland Ltd.', 'marketing', [['_fbp', '90 de zile', 'măsoară reclamele și creează audiențe'], ['fr (facebook.com)', '90 de zile', 'reclame personalizate']]],
        'tiktok' => ['TikTok Pixel', 'TikTok Technology Ltd.', 'marketing', [['_ttp', '13 luni', 'măsoară reclamele TikTok'], ['_tt_enable_cookie', '13 luni', 'verifică suportul pentru cookie-uri']]],
        'linkedin' => ['LinkedIn Insight Tag', 'LinkedIn Ireland', 'marketing', [['li_sugr, bcookie', '90 de zile – 1 an', 'măsoară reclamele LinkedIn']]],
        'hotjar' => ['Hotjar', 'Hotjar Ltd.', 'statistics', [['_hjSessionUser_*', '1 an', 'identifică vizitatorul anonim'], ['_hjSession_*', '30 de minute', 'sesiunea curentă']]],
        'clarity' => ['Microsoft Clarity', 'Microsoft Ireland', 'statistics', [['_clck', '1 an', 'identifică vizitatorul anonim'], ['_clsk', '1 zi', 'sesiunea curentă'], ['MUID', '1 an', 'identificator Microsoft']]],
        'youtube' => ['Videoclipuri YouTube încorporate', 'Google Ireland Ltd.', 'marketing', [['YSC', 'sesiune', 'statistici de vizionare'], ['VISITOR_INFO1_LIVE', '6 luni', 'recomandări și reclame']]],
        'maps' => ['Google Maps încorporat', 'Google Ireland Ltd.', 'preferences', [['NID', '6 luni', 'preferințele hărții']]],
        'recaptcha' => ['Google reCAPTCHA', 'Google Ireland Ltd.', 'necessary', [['_GRECAPTCHA', '6 luni', 'protecție împotriva roboților în formulare']]],
    ];

    public const DEFAULTS = [
        'enabled' => false,
        'layout' => 'bar',
        'position' => 'left',
        'color' => '#2f6bff',
        'title' => 'Folosim cookie-uri',
        'text' => 'Folosim cookie-uri strict necesare ca site-ul să funcționeze și, doar cu acordul tău, cookie-uri de preferințe, statistici și marketing. Poți alege ce accepți și îți poți schimba oricând decizia.',
        'privacy_url' => null,
        'policy_url' => null,
        'services' => [],
        'custom' => [],
        'consent_days' => 180,
        'version' => 1,
        'reopen' => true,
        'gcm' => true,
    ];

    /** @return array<string, mixed> */
    public static function for(Site $site): array
    {
        return array_replace(self::DEFAULTS, array_intersect_key((array) ($site->cookie_config ?? []), self::DEFAULTS));
    }

    /** @param array<string, mixed> $input @param array<string, mixed> $current @return array<string, mixed> */
    public static function normalize(array $input, array $current): array
    {
        $text = fn (string $key, int $max, ?string $default = null) => mb_substr(trim((string) ($input[$key] ?? '')), 0, $max) ?: $default;
        $custom = [];
        foreach (array_slice((array) ($input['custom'] ?? []), 0, 20) as $row) {
            $row = (array) $row;
            $name = mb_substr(trim(strip_tags((string) ($row['name'] ?? ''))), 0, 80);
            if ($name === '') {
                continue;
            }
            $custom[] = [
                'name' => $name,
                'provider' => mb_substr(trim(strip_tags((string) ($row['provider'] ?? ''))), 0, 80),
                'category' => array_key_exists($row['category'] ?? '', self::CATEGORIES) ? $row['category'] : 'marketing',
                'cookies' => mb_substr(trim(strip_tags((string) ($row['cookies'] ?? ''))), 0, 160),
                'duration' => mb_substr(trim(strip_tags((string) ($row['duration'] ?? ''))), 0, 40),
            ];
        }
        $color = (string) ($input['color'] ?? '');

        return [
            'enabled' => ! empty($input['enabled']),
            'layout' => in_array($input['layout'] ?? '', ['bar', 'box'], true) ? $input['layout'] : 'bar',
            'position' => ($input['position'] ?? '') === 'right' ? 'right' : 'left',
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : self::DEFAULTS['color'],
            'title' => $text('title', 80, self::DEFAULTS['title']),
            'text' => $text('text', 600, self::DEFAULTS['text']),
            'privacy_url' => self::url($input['privacy_url'] ?? null),
            'policy_url' => self::url($input['policy_url'] ?? null),
            'services' => array_values(array_intersect(array_keys(self::SERVICES), (array) ($input['services'] ?? []))),
            'custom' => $custom,
            'consent_days' => in_array($days = (int) ($input['consent_days'] ?? 180), [90, 180, 365], true) ? $days : 180,
            'version' => max(1, (int) ($current['version'] ?? 1)) + (! empty($input['reconsent']) ? 1 : 0),
            'reopen' => ! empty($input['reopen']),
            'gcm' => ! empty($input['gcm']),
        ];
    }

    /**
     * Toate cookie-urile site-ului, pe categorii: ale platformei VITIM (după ce e activ pe site), ale WordPress / WooCommerce
     * și serviciile declarate de firmă. @return array<string, list<array{name: string, provider: string, cookies: string, duration: string, purpose: string}>>
     */
    public static function table(Site $site): array
    {
        $s = self::for($site);
        $company = CampaignRenderer::company($site->organization);
        $rows = array_fill_keys(array_keys(self::CATEGORIES), []);
        $rows['necessary'][] = ['name' => 'Alegerea ta despre cookie-uri', 'provider' => $company, 'cookies' => 'vitim_consent', 'duration' => $s['consent_days'].' de zile', 'purpose' => 'reține ce ai acceptat, ca să nu te întrebăm la fiecare pagină'];
        if (WidgetSettings::for($site)['enabled'] && WidgetSettings::agentFor($site)) {
            $rows['necessary'][] = ['name' => 'Chat pe site', 'provider' => $company, 'cookies' => 'vitim_chat_* (stocare locală)', 'duration' => 'până la ștergere', 'purpose' => 'păstrează conversația pe care ai început-o în chat'];
        }
        $rows['necessary'][] = ['name' => 'Formulare de abonare', 'provider' => $company, 'cookies' => 'vitim_form_* (stocare locală)', 'duration' => 'până la ștergere', 'purpose' => 'nu-ți mai arată formularul după ce l-ai închis sau te-ai abonat'];
        $rows['marketing'][] = ['name' => 'Recunoașterea abonaților', 'provider' => $company, 'cookies' => 'vitim_ct', 'duration' => '1 an', 'purpose' => 'te recunoaște când vii dintr-un email trimis de noi sau după abonare, pentru recomandări și reamintirea coșului'];
        if ($site->platform === 'wordpress') {
            $rows['necessary'][] = ['name' => 'WordPress', 'provider' => $company, 'cookies' => 'wordpress_test_cookie, wordpress_logged_in_*', 'duration' => 'sesiune / 14 zile', 'purpose' => 'autentificarea în cont'];
            if (Product::query()->where('site_id', $site->id)->exists()) {
                $rows['necessary'][] = ['name' => 'Magazin (WooCommerce)', 'provider' => $company, 'cookies' => 'woocommerce_cart_hash, woocommerce_items_in_cart, wp_woocommerce_session_*', 'duration' => 'sesiune / 2 zile', 'purpose' => 'coșul de cumpărături și comanda'];
            }
        }
        foreach ($s['services'] as $key) {
            [$name, $provider, $category, $cookies] = self::SERVICES[$key];
            foreach ($cookies as [$cookie, $duration, $purpose]) {
                $rows[$category][] = ['name' => $name, 'provider' => $provider, 'cookies' => $cookie, 'duration' => $duration, 'purpose' => $purpose];
            }
        }
        foreach ($s['custom'] as $c) {
            $rows[$c['category']][] = ['name' => $c['name'], 'provider' => $c['provider'], 'cookies' => $c['cookies'], 'duration' => $c['duration'], 'purpose' => ''];
        }

        return $rows;
    }

    /** Ce primește scriptul de pe site. @return array<string, mixed> */
    public static function publicPayload(Site $site): array
    {
        $s = self::for($site);
        $table = self::table($site);

        return [
            'version' => $s['version'], 'days' => $s['consent_days'], 'layout' => $s['layout'], 'position' => $s['position'], 'color' => $s['color'],
            'title' => $s['title'], 'text' => $s['text'], 'privacy_url' => $s['privacy_url'], 'policy_url' => $s['policy_url'],
            'reopen' => $s['reopen'], 'gcm' => $s['gcm'], 'company' => CampaignRenderer::company($site->organization),
            'categories' => array_map(fn ($key) => ['key' => $key, 'label' => self::CATEGORIES[$key][0], 'description' => self::CATEGORIES[$key][1], 'cookies' => $table[$key]],
                array_values(array_filter(array_keys(self::CATEGORIES), fn ($k) => $k === 'necessary' || $table[$k] !== []))),
        ];
    }

    /**
     * Fragmentul pus cât mai sus în <head> (pluginul WordPress îl pune singur): Google Consent Mode v2 cu totul refuzat
     * până la alegere, citind alegerea deja salvată, ca Google Analytics / Ads să respecte acordul din prima secundă.
     */
    public static function headSnippet(): string
    {
        return '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}'
            .'(function(){var m=document.cookie.match(/(?:^|; )vitim_consent=v\\d+\\.([01])([01])([01])\\./),g=function(i){return m&&m[i]==="1"?"granted":"denied"};'
            .'gtag("consent","default",{ad_storage:g(3),ad_user_data:g(3),ad_personalization:g(3),analytics_storage:g(2),functionality_storage:g(1),personalization_storage:g(1),security_storage:"granted",wait_for_update:500});'
            .'gtag("set","ads_data_redaction",true);gtag("set","url_passthrough",true);})();</script>';
    }

    private static function url(mixed $value): ?string
    {
        $v = trim((string) $value);

        return $v !== '' && mb_strlen($v) <= 300 && preg_match('#^https?://#i', $v) ? $v : null;
    }
}
