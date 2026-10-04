<?php
declare(strict_types=1);

use App\Core\DB;
use App\Core\ProjectSync;
use App\Core\Settings;

/**
 * v1.9: VITIM prezentat ca companie, VITIM AI ca produs.
 * - proiectele: lista completă cu denumirile exacte și secțiunea „Rezultatul”;
 * - serviciile de reparații (calculatoare, plăci de bază, telefoane) trec în categoria secundară „Service & intervenții”
 *   (adresele paginilor rămân aceleași);
 * - bugetele din formular: lunar (abonament) separat de proiect;
 * - pagina „Despre noi” refăcută (textul vechi se păstrează în setarea `backup_texte_v18`);
 * - denumirea „Local Mureș” corectată peste tot.
 */
return function (): void {
    DB::addColumn('projects', 'result', 'text?');
    ProjectSync::run();
    // rândurile vechi din 1.7 cu denumiri greșite (adresele lor trimit deja spre proiectele corecte)
    DB::delete('projects', 'slug IN (?, ?) AND published = 0', ['autohouse-westcar-receptie-service-si-notificari', 'optofarm-marketing-infrastructura-afisaj']);

    foreach (['reparatii-it', 'reparatii-placi-de-baza', 'reparatii-telefoane-tablete'] as $slug) {
        DB::update('services', ['category' => 'service'], 'slug = :s', ['s' => $slug]);
    }

    $defaults = require APP_PATH . '/Data/settings_defaults.php';
    foreach (['form_budgets_monthly', 'form_budgets_project'] as $k) {
        Settings::set($k, $defaults[$k]);
    }
    // „De ce VITIM”: doar textul vechi implicit se înlocuiește (nu ce ai schimbat tu din panou)
    $why = (string)Settings::get('home_why', '');
    if (str_contains($why, 'Ai un om care îți cunoaște firma')) {
        Settings::set('home_why', str_replace('Ai un om care îți cunoaște firma', 'Echipa VITIM îți cunoaște firma', $why));
    }

    $about = DB::row("SELECT id, title, subtitle, body, meta_title, meta_description FROM pages WHERE slug = 'despre-noi'");
    if ($about) {
        if ((string)Settings::get('backup_texte_v18', '') === '') {
            Settings::set('backup_texte_v18', json_encode(['page_despre_noi' => $about], JSON_UNESCAPED_UNICODE));
        }
        foreach (require APP_PATH . '/Data/seed_pages.php' as $pg) {
            if ($pg['slug'] === 'despre-noi') {
                DB::update('pages', ['title' => $pg['title'], 'subtitle' => $pg['subtitle'], 'body' => trim($pg['body']),
                    'meta_title' => $pg['meta_title'], 'meta_description' => $pg['meta_description'], 'updated_at' => DB::now()], 'id = :id', ['id' => $about['id']]);
            }
        }
    }

    // denumirea corectă: „Local Mureș” (nu „LocalMureș”)
    // + fără promisiuni juridice: „conform GDPR” devine descrierea mecanismului
    $fix = fn(string $v): string => str_replace(['LocalMureș', 'LocalMures.ro', 'banner de cookies conform GDPR'], ['Local Mureș', 'localmures.ro', 'banner de cookies pentru gestionarea consimțământului (GDPR)'], $v);
    foreach (['pages' => ['title', 'subtitle', 'body', 'meta_title', 'meta_description'], 'locations' => ['body'], 'posts' => ['title', 'excerpt', 'body'], 'services' => ['excerpt', 'body']] as $table => $cols) {
        if (!DB::tableExists($table)) {
            continue;
        }
        $cols = array_values(array_intersect($cols, DB::columns($table)));
        foreach (DB::all('SELECT id, ' . implode(', ', $cols) . " FROM $table") as $r) {
            $upd = [];
            foreach ($cols as $c) {
                if ($r[$c] !== null && $fix((string)$r[$c]) !== (string)$r[$c]) {
                    $upd[$c] = $fix((string)$r[$c]);
                }
            }
            if ($upd) {
                DB::update($table, $upd, 'id = :id', ['id' => $r['id']]);
            }
        }
    }
    foreach (DB::all("SELECT skey, svalue FROM settings WHERE svalue LIKE '%LocalMure%'") as $r) {
        Settings::set($r['skey'], $fix((string)$r['svalue']));
    }

    \App\Core\Cache::clear();
};
