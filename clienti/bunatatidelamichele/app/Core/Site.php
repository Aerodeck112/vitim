<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Date comune pentru șabloanele site-ului public.
 */
final class Site
{
    public static function categories(): array
    {
        static $c = null;
        return $c ??= DB::all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.published = 1) AS product_count FROM categories c WHERE c.published = 1 ORDER BY c.sort, c.name');
    }

    public static function footerPages(string $group): array
    {
        return DB::all('SELECT slug, title FROM pages WHERE published = 1 AND in_footer = 1 AND footer_group = ? ORDER BY sort, title', [$group]);
    }

    public static function hasPosts(): bool
    {
        static $h = null;
        return $h ??= (int)DB::val("SELECT COUNT(*) FROM posts WHERE status = 'published' AND published_at <= ?", [DB::now()]) > 0;
    }

    public static function socials(): array
    {
        $out = [];
        foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube'] as $k => $label) {
            $u = (string)Settings::get('social_' . $k);
            if ($u !== '') {
                $out[] = ['icon' => $k, 'label' => $label, 'url' => $u];
            }
        }
        return $out;
    }

    public static function companyAddress(): string
    {
        $county = (string)Settings::get('company_county');
        return trim(implode(', ', array_filter([(string)Settings::get('company_address'), (string)Settings::get('company_city'), $county !== '' ? 'jud. ' . $county : ''])), ', ');
    }

    /** Înlocuiește variabilele de firmă și de magazin în textele paginilor (ex: termeni, livrare, retur). */
    public static function companyVars(string $html): string
    {
        $s = fn($k) => e((string)Settings::get($k));
        $missing = fn($what) => '<em>[' . $what . ' – completează în Panou → Setări → Firmă]</em>';
        $phone = (string)Settings::get('phone');
        $ship = (float)Settings::get('shipping_cost', '0');
        return strtr($html, [
            '{{firma}}' => $s('company_name'),
            '{{brand}}' => $s('brand_name'),
            '{{cui}}' => $s('company_cui') ?: $missing('CUI'),
            '{{reg_com}}' => $s('company_reg') ?: $missing('Nr. Reg. Com.'),
            '{{adresa}}' => self::companyAddress() !== '' ? e(self::companyAddress()) : $missing('adresa sediului'),
            '{{email}}' => $s('email'),
            '{{telefon}}' => e($phone),
            '{{telefon_text}}' => $phone !== '' ? ' sau la telefon ' . e($phone) : '',
            '{{site}}' => e(preg_replace('#^https?://#', '', rtrim(abs_url('/'), '/'))),
            '{{data}}' => date('d.m.Y'),
            '{{livrare_cost}}' => $ship > 0 ? e(Shop::money($ship)) : 'gratuit',
            '{{livrare_timp}}' => $s('delivery_time'),
            '{{retur_zile}}' => $s('return_days'),
        ]);
    }

    public static function logoHtml(bool $light = false): string
    {
        $brand = e((string)Settings::get('brand_name', 'Bunătăți de la Michele'));
        $logo = (string)Settings::get($light ? 'logo_light' : 'logo');
        $src = $logo !== '' ? upload_url($logo) : url('/assets/img/' . ($light ? 'logo-gold.png' : 'logo.png'));
        return '<img src="' . e($src) . '" alt="' . $brand . '" width="120" height="106" decoding="async">';
    }

    /** Imagine responsivă dintr-o cale din uploads (srcset + dimensiuni, pentru CLS zero). */
    public static function img(?string $path, string $alt, string $sizes = '100vw', string $class = '', bool $lazy = true, array $attrs = []): string
    {
        if (!$path) {
            return '';
        }
        static $meta = [];
        if (!array_key_exists($path, $meta)) {
            $meta[$path] = DB::row('SELECT width, height, variants, alt FROM media WHERE path = ?', [$path]);
        }
        $m = $meta[$path];
        $srcset = Uploader::srcset($path);
        $a = ['src' => upload_url($path), 'alt' => $alt !== '' ? $alt : (string)($m['alt'] ?? '')];
        if ($srcset !== '' && str_contains($srcset, ',')) {
            $a['srcset'] = $srcset;
            $a['sizes'] = $sizes;
        }
        if ($m) {
            $a['width'] = (string)(int)$m['width'];
            $a['height'] = (string)(int)$m['height'];
        }
        if ($class !== '') {
            $a['class'] = $class;
        }
        $a['decoding'] = 'async';
        if ($lazy) {
            $a['loading'] = 'lazy';
        } else {
            $a['fetchpriority'] = 'high';
        }
        $a = array_merge($a, $attrs);
        $out = '<img';
        foreach ($a as $k => $v) {
            $out .= ' ' . $k . '="' . e($v) . '"';
        }
        return $out . '>';
    }
}
