<?php
declare(strict_types=1);

use App\Core\DB;
use App\Core\Settings;

/**
 * v1.7: repoziționare — „Departamentul extern de IT & AI al firmei tale”.
 * Textele vechi ale primei pagini și ale paginii „Despre noi” se salvează întâi în setarea `backup_texte_v16`
 * (se pot recupera din baza de date). Proiectele reale se adaugă doar dacă lipsesc.
 */
return function (): void {
    // ---------- proiecte: câmpuri pentru formatul problemă → soluție → ce am implementat ----------
    DB::addColumn('projects', 'tag', 'string?');
    DB::addColumn('projects', 'problem', 'text?');
    DB::addColumn('projects', 'solution', 'text?');
    DB::addColumn('projects', 'features', 'text?'); // un element pe rând
    DB::addColumn('projects', 'featured', 'bool');
    DB::addColumn('projects', 'logo', 'string?');

    $now = DB::now();
    foreach (require APP_PATH . '/Data/seed_projects.php' as $p) {
        if (DB::val('SELECT id FROM projects WHERE slug = ?', [$p['slug']])) {
            continue;
        }
        $body = '<h2>Problema</h2><p>' . e($p['problem']) . '</p>'
            . '<h2>Soluția VITIM</h2><p>' . e($p['solution']) . '</p>'
            . '<h2>Ce am implementat</h2><ul>' . implode('', array_map(fn($f) => '<li>' . e($f) . '</li>', $p['features'])) . '</ul>';
        DB::insert('projects', [
            'slug' => $p['slug'],
            'title' => $p['title'],
            'client' => $p['client'],
            'service_id' => DB::val('SELECT id FROM services WHERE slug = ?', [$p['service']]) ?: null,
            'summary' => $p['summary'],
            'body' => $body,
            'results' => '[]',
            'tag' => $p['tag'],
            'problem' => $p['problem'],
            'solution' => $p['solution'],
            'features' => implode("\n", $p['features']),
            'featured' => $p['featured'],
            'sort' => $p['sort'],
            'published' => 1,
            'meta_title' => $p['client'] . ' – studiu de caz VITIM',
            'meta_description' => mb_substr($p['summary'] . ' ' . $p['solution'], 0, 158),
            'noindex' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    // ---------- copie de siguranță a textelor actuale ----------
    $keys = ['brand_tagline', 'seo_home_title', 'seo_home_description', 'seo_llms_intro', 'home_badge', 'home_title', 'home_subtitle',
        'home_cta_primary', 'home_cta_secondary', 'home_points', 'home_why', 'home_process', 'home_stats', 'home_faq', 'home_cta_title', 'home_cta_text'];
    if ((string)Settings::get('backup_texte_v16', '') === '') {
        $backup = [];
        foreach ($keys as $k) {
            // doar valorile salvate în baza de date; cele nesalvate erau textele implicite din versiunea 1.6
            $v = DB::val('SELECT svalue FROM settings WHERE skey = ?', [$k]);
            if ($v !== null && $v !== false) {
                $backup[$k] = (string)$v;
            }
        }
        $about = DB::row("SELECT title, subtitle, body, meta_title, meta_description FROM pages WHERE slug = 'despre-noi'");
        if ($about) {
            $backup['page_despre_noi'] = $about;
        }
        Settings::set('backup_texte_v16', json_encode($backup, JSON_UNESCAPED_UNICODE));
    }

    // ---------- textele noi (vezi și app/Data/settings_defaults.php) ----------
    $defaults = require APP_PATH . '/Data/settings_defaults.php';
    foreach ([...$keys, 'pricing_from', 'pricing_note'] as $k) {
        if (array_key_exists($k, $defaults)) {
            Settings::set($k, $defaults[$k]);
        }
    }

    $about = DB::row("SELECT id FROM pages WHERE slug = 'despre-noi'");
    if ($about) {
        $seed = null;
        foreach (require APP_PATH . '/Data/seed_pages.php' as $pg) {
            if ($pg['slug'] === 'despre-noi') {
                $seed = $pg;
            }
        }
        if ($seed) {
            DB::update('pages', [
                'title' => $seed['title'],
                'subtitle' => $seed['subtitle'],
                'body' => trim($seed['body']),
                'meta_title' => $seed['meta_title'],
                'meta_description' => $seed['meta_description'],
                'updated_at' => $now,
            ], 'id = :id', ['id' => $about['id']]);
        }
    }

    \App\Core\Cache::clear();
};
