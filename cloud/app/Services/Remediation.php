<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Lista închisă de acțiuni pe care panoul le poate cere pluginului. Aceeași listă e codată și în plugin:
 * orice altceva e refuzat de ambele părți. Nu există execuție de cod arbitrar.
 */
final class Remediation
{
    /** acțiune => [etichetă, are țintă (plugin / temă)] */
    public const ACTIONS = [
        'scan' => ['Scanează acum', false],
        'backup' => ['Backup acum', false],
        'update_plugin' => ['Actualizează pluginul', true],
        'update_all_plugins' => ['Actualizează toate pluginurile', false],
        'update_theme' => ['Actualizează tema', true],
        'update_core' => ['Actualizează WordPress', false],
        'reinstall_core' => ['Reinstalează fișierele WordPress (aceeași versiune)', false],
        'delete_debug_log' => ['Șterge debug.log', false],
        'delete_readme' => ['Șterge readme.html', false],
        'disable_xmlrpc' => ['Dezactivează XML-RPC', false],
        'disable_file_edit' => ['Dezactivează editorul de fișiere din WordPress', false],
        'block_php_uploads' => ['Blochează PHP în uploads', false],
        'allow_indexing' => ['Permite indexarea în Google', false],
        'seo_fix' => ['Repară automat', true],
    ];

    /** Remedierile SEO făcute de plugin (1.4.0+); ținta e una sau mai multe, unite prin punct: „meta.robots”. */
    public const SEO_FIXES = [
        'meta' => 'Adaugă descrieri, canonical și Open Graph',
        'title' => 'Activează titlurile generate de WordPress',
        'robots' => 'Repară robots.txt',
        'sitemap' => 'Pornește sitemap-ul',
        'https' => 'Redirecționează spre HTTPS',
        'www' => 'Unifică www / fără www',
        'lang' => 'Adaugă limba paginii',
        'viewport' => 'Adaugă viewport pentru telefon',
        'alt' => 'Completează textul alternativ al imaginilor',
        'schema' => 'Adaugă datele structurate ale firmei',
        'headers' => 'Adaugă antetele de securitate',
        'gzip' => 'Pornește compresia',
    ];

    /** Problemele din audit care se rezolvă cu un buton pe site-urile WordPress cu pluginul 1.4.0+. */
    public const AUDIT_FIXES = [
        'seo.noindex_home' => 'allow_indexing',
        'seo.robots_block_all' => 'seo_fix:robots',
        'seo.robots_missing' => 'seo_fix:robots',
        'seo.sitemap_missing' => 'seo_fix:sitemap',
        'seo.https_redirect' => 'seo_fix:https',
        'seo.www_duplicate' => 'seo_fix:www',
        'seo.title_missing' => 'seo_fix:title',
        'seo.description_missing' => 'seo_fix:meta',
        'seo.canonical_missing' => 'seo_fix:meta',
        'seo.og_missing' => 'seo_fix:meta',
        'seo.lang_missing' => 'seo_fix:lang',
        'seo.viewport_missing' => 'seo_fix:viewport',
        'seo.images_alt' => 'seo_fix:alt',
        'seo.schema_missing' => 'seo_fix:schema',
        'sec.headers_missing' => 'seo_fix:headers',
        'perf.no_compression' => 'seo_fix:gzip',
    ];

    public const SEO_PLUGIN_VERSION = '1.4.0';

    /** Remedierea automată pentru o problemă din audit, dacă site-ul o poate primi. */
    public static function forAudit(string $code, ?string $connectorVersion): ?string
    {
        return $connectorVersion !== null && version_compare($connectorVersion, self::SEO_PLUGIN_VERSION, '>=') ? (self::AUDIT_FIXES[$code] ?? null) : null;
    }

    /**
     * Toate remedierile SEO dintr-o listă de probleme, ca o singură comandă („seo_fix:meta.robots”).
     *
     * @param  iterable<string|null>  $fixes
     */
    public static function combine(iterable $fixes): ?string
    {
        $targets = [];
        foreach ($fixes as $fix) {
            if ($fix !== null && str_starts_with($fix, 'seo_fix:')) {
                array_push($targets, ...explode('.', substr($fix, 8)));
            }
        }
        $targets = array_values(array_unique($targets));

        return $targets ? 'seo_fix:'.implode('.', $targets) : null;
    }

    /** @return array{0: string, 1: ?string}|null [acțiune, țintă] dintr-un șir „acțiune” sau „acțiune:țintă” */
    public static function parse(string $fix): ?array
    {
        [$action, $target] = array_pad(explode(':', $fix, 2), 2, null);
        if (! isset(self::ACTIONS[$action])) {
            return null;
        }
        $needsTarget = self::ACTIONS[$action][1];
        if ($needsTarget !== ($target !== null && $target !== '')) {
            return null;
        }
        if ($target !== null && (! preg_match('#^[A-Za-z0-9._/-]{1,190}$#', $target) || str_contains($target, '..') || str_starts_with($target, '/'))) {
            return null;
        }
        if ($action === 'seo_fix' && array_diff(explode('.', (string) $target), array_keys(self::SEO_FIXES)) !== []) {
            return null;
        }

        return [$action, $target];
    }

    public static function label(string $fix): string
    {
        $parsed = self::parse($fix);
        if ($parsed && $parsed[0] === 'seo_fix') {
            $targets = explode('.', (string) $parsed[1]);

            return count($targets) === 1 ? self::SEO_FIXES[$targets[0]] : 'Repară automat '.count($targets).' probleme';
        }

        return $parsed ? self::ACTIONS[$parsed[0]][0] : $fix;
    }
}
