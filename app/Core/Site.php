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
            self::$services = DB::all('SELECT id, slug, category, title, tagline, icon, excerpt, onsite, featured, image FROM services WHERE published = 1 ORDER BY sort, title');
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
        return self::wordmarkSvg() . '<span class="sr-only">' . $brand . '</span>' . ($withTag ? '<small class="logo-tag">IT &amp; AI</small>' : '');
    }

    /**
     * Logotipul VITIM: „vitim” desenat din linii, cu punctele lui „i” ca două noduri albastre
     * (VITIM leagă tehnologia firmei într-un singur sistem). Culoarea literelor urmează textul (temă luminoasă / întunecată).
     */
    public static function wordmarkSvg(string $class = 'wordmark'): string
    {
        return '<svg class="' . $class . '" viewBox="0 0 86 36" fill="none" aria-hidden="true" focusable="false">'
            . '<g stroke="currentColor" stroke-width="4.6" stroke-miterlimit="10">'
            . '<path d="M2.2 13 10.8 32.2 19.4 13"/><path d="M27.4 13V34"/><path d="M36.8 5v22q0 4.7 4.7 4.7H44"/><path d="M31.5 13h12"/>'
            . '<path d="M51.6 13V34"/><path d="M60.4 34V13m0 5.6q0-5.6 5.9-5.6t5.9 5.8V34m0-15.2q0-5.8 5.9-5.8T84 18.8V34"/></g>'
            . '<circle class="node" cx="27.4" cy="5" r="3.1"/><circle class="node" cx="51.6" cy="5" r="3.1"/></svg>';
    }

    /** Simbolul VITIM: un „V” ale cărui brațe converg într-un singur nod (favicon, avatarul chatului, panou). */
    public static function markSvg(string $class = 'mark'): string
    {
        return '<svg class="' . $class . '" viewBox="0 0 64 64" aria-hidden="true" focusable="false"><rect width="64" height="64" rx="14" fill="#0b1020"/>'
            . '<path d="M17.5 17.5 32 44 46.5 17.5" fill="none" stroke="#eef1f8" stroke-width="6"/>'
            . '<circle cx="17.5" cy="17.5" r="5" fill="#0b1020" stroke="#eef1f8" stroke-width="3"/><circle cx="46.5" cy="17.5" r="5" fill="#0b1020" stroke="#eef1f8" stroke-width="3"/>'
            . '<circle cx="32" cy="45" r="6.5" fill="#4d82ec"/></svg>';
    }
}
