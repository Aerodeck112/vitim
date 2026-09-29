<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Date comune pentru șabloanele site-ului public.
 */
final class Site
{
    private static ?array $services = null;
    private static ?array $counties = null;

    public static function categories(): array
    {
        static $c = null;
        return $c ??= require APP_PATH . '/Data/categories.php';
    }

    public static function services(): array
    {
        if (self::$services === null) {
            self::$services = DB::all('SELECT id, slug, category, title, tagline, icon, excerpt, onsite, featured FROM services WHERE published = 1 ORDER BY sort, title');
        }
        return self::$services;
    }

    public static function servicesByCategory(): array
    {
        $out = [];
        foreach (self::categories() as $k => $c) {
            $out[$k] = ['cat' => $c, 'items' => []];
        }
        foreach (self::services() as $s) {
            $out[$s['category']]['items'][] = $s;
        }
        return array_filter($out, fn($g) => !empty($g['items']) && isset($g['cat']));
    }

    /** Județele cu orașele lor. */
    public static function counties(): array
    {
        if (self::$counties === null) {
            $rows = DB::all('SELECT id, slug, name, type, parent_id FROM locations WHERE published = 1 ORDER BY sort, name');
            $counties = [];
            foreach ($rows as $r) {
                if ($r['type'] === 'judet') {
                    $counties[$r['id']] = $r + ['cities' => []];
                }
            }
            foreach ($rows as $r) {
                if ($r['type'] !== 'judet' && isset($counties[$r['parent_id']])) {
                    $counties[$r['parent_id']]['cities'][] = $r;
                }
            }
            self::$counties = array_values($counties);
        }
        return self::$counties;
    }

    public static function footerPages(): array
    {
        return DB::all("SELECT slug, title FROM pages WHERE published = 1 AND in_footer = 1 ORDER BY sort, title");
    }

    public static function hasProjects(): bool
    {
        return (int)DB::val('SELECT COUNT(*) FROM projects WHERE published = 1') > 0;
    }

    public static function hasPosts(): bool
    {
        return (int)DB::val("SELECT COUNT(*) FROM posts WHERE status = 'published'") > 0;
    }

    public static function socials(): array
    {
        $out = [];
        foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'tiktok' => 'TikTok'] as $k => $label) {
            $u = (string)Settings::get('social_' . $k);
            if ($u !== '') {
                $out[] = ['icon' => $k, 'label' => $label, 'url' => $u];
            }
        }
        return $out;
    }

    /** Înlocuiește variabilele de firmă în textele paginilor (ex: politica de confidențialitate). */
    public static function companyVars(string $html): string
    {
        $s = fn($k) => e((string)Settings::get($k));
        $address = trim(implode(', ', array_filter([(string)Settings::get('company_address'), (string)Settings::get('company_city'), 'jud. ' . Settings::get('company_county')])), ', ');
        return strtr($html, [
            '{{firma}}' => $s('company_name'),
            '{{brand}}' => $s('brand_name'),
            '{{cui}}' => $s('company_cui') ?: '<em>[CUI – completează în Setări → Firmă]</em>',
            '{{reg_com}}' => $s('company_reg') ?: '<em>[Nr. Reg. Com. – completează în Setări → Firmă]</em>',
            '{{adresa}}' => e($address),
            '{{email}}' => $s('email'),
            '{{telefon}}' => $s('phone'),
            '{{site}}' => e(abs_url('/')),
            '{{data}}' => date('d.m.Y'),
        ]);
    }

    public static function logoHtml(bool $withTag = true): string
    {
        $logo = (string)Settings::get('logo');
        $brand = e((string)Settings::get('brand_name', 'VITIM'));
        if ($logo !== '') {
            $dark = (string)Settings::get('logo_dark');
            $img = '<img src="' . e(upload_url($logo)) . '" alt="' . $brand . '" width="140" height="34"' . ($dark ? ' class="logo-light"' : '') . '>';
            if ($dark) {
                $img .= '<img src="' . e(upload_url($dark)) . '" alt="' . $brand . '" width="140" height="34" class="logo-dark">';
            }
            return $img;
        }
        return self::markSvg() . '<span>' . $brand . ($withTag ? '<small>IT · Marketing · AI</small>' : '') . '</span>';
    }

    public static function markSvg(string $class = 'mark'): string
    {
        return '<svg class="' . $class . '" viewBox="0 0 64 64" aria-hidden="true"><defs><linearGradient id="lg-v" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2f8cff"/><stop offset=".6" stop-color="#6a5cff"/><stop offset="1" stop-color="#19d3c5"/></linearGradient></defs><rect width="64" height="64" rx="16" fill="url(#lg-v)"/><path d="M15 17h9l8 20 8-20h9L37 47h-10z" fill="#fff"/><path d="M41 17h8l-4 10h-8z" fill="#fff" opacity=".55"/></svg>';
    }
}
