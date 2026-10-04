<?php
declare(strict_types=1);

use App\Core\DB;
use App\Core\Uploader;

/**
 * v1.8: lista de proiecte confirmată de VITIM + capturi reale ale site-urilor (desktop și telefon).
 * Proiectele din listă se actualizează după slug; cele care nu mai sunt în listă se scot de pe site (nepublicate)
 * și adresele lor trimit spre /proiecte. Adresele schimbate primesc redirecționare 301.
 */
return function (): void {
    DB::addColumn('projects', 'own', 'bool');
    DB::addColumn('projects', 'site', 'string?');
    DB::addColumn('projects', 'cover_mobile', 'string?');

    $import = function (string $file, string $alt): ?string {
        $src = APP_PATH . '/Data/images/proiecte/' . $file;
        if (!is_file($src)) {
            return null;
        }
        $existing = DB::val('SELECT path FROM media WHERE original_name = ?', [$file]);
        if ($existing) {
            return (string)$existing;
        }
        $tmp = STORAGE_PATH . '/tmp/' . $file;
        copy($src, $tmp);
        [$m] = Uploader::image(['name' => $file, 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)], $alt);
        @unlink($tmp);
        return $m['path'] ?? null;
    };

    $now = DB::now();
    $seed = require APP_PATH . '/Data/seed_projects.php';
    $keep = [];
    foreach ($seed as $p) {
        $keep[] = $p['slug'];
        $body = '<h2>Problema</h2><p>' . e($p['problem']) . '</p>'
            . '<h2>Soluția VITIM</h2><p>' . e($p['solution']) . '</p>'
            . '<h2>Ce am implementat</h2><ul>' . implode('', array_map(fn($f) => '<li>' . e($f) . '</li>', $p['features'])) . '</ul>';
        $row = [
            'title' => $p['title'],
            'client' => $p['client'],
            'service_id' => DB::val('SELECT id FROM services WHERE slug = ?', [$p['service']]) ?: null,
            'summary' => $p['summary'],
            'body' => $body,
            'tag' => $p['tag'],
            'problem' => $p['problem'],
            'solution' => $p['solution'],
            'features' => implode("\n", $p['features']),
            'featured' => $p['featured'],
            'own' => $p['own'],
            'site' => $p['site'],
            'sort' => $p['sort'],
            'published' => 1,
            'meta_title' => $p['client'] . ($p['own'] ? ' – proiect VITIM' : ' – studiu de caz VITIM'),
            'meta_description' => mb_substr($p['summary'] . ' ' . $p['solution'], 0, 158),
            'updated_at' => $now,
        ];
        $alt = 'Site-ul ' . $p['site'] . ' (' . $p['client'] . ')';
        if ($c = $import($p['slug'] . '.webp', $alt . ' pe calculator')) {
            $row['cover'] = $c;
        }
        if ($c = $import($p['slug'] . '-m.webp', $alt . ' pe telefon')) {
            $row['cover_mobile'] = $c;
        }
        $id = DB::val('SELECT id FROM projects WHERE slug = ?', [$p['slug']]);
        if ($id) {
            DB::update('projects', $row, 'id = :id', ['id' => $id]);
        } else {
            DB::insert('projects', $row + ['slug' => $p['slug'], 'results' => '[]', 'noindex' => 0, 'created_at' => $now]);
        }
    }

    // adrese vechi: proiecte redenumite → noua adresă; proiecte scoase → lista de proiecte
    $moved = [
        'autohouse-westcar-receptie-service-si-notificari' => 'autohaus-westcar-mentenanta-it-afisaj-digital',
        'optofarm-marketing-infrastructura-afisaj' => 'optica-optofarm-site-uri-securitate-email-marketing',
    ];
    $old = DB::all('SELECT id, slug FROM projects');
    foreach ($old as $r) {
        if (in_array($r['slug'], $keep, true)) {
            continue;
        }
        DB::update('projects', ['published' => 0, 'featured' => 0, 'updated_at' => $now], 'id = :id', ['id' => $r['id']]);
        $from = '/proiecte/' . $r['slug'];
        $to = isset($moved[$r['slug']]) ? '/proiecte/' . $moved[$r['slug']] : '/proiecte';
        if (!DB::val('SELECT id FROM redirects WHERE from_path = ?', [$from])) {
            DB::insert('redirects', ['from_path' => $from, 'to_path' => $to, 'code' => 301, 'hits' => 0, 'created_at' => $now]);
        }
    }

    // prima pagină: în dreapta titlului, capturile proiectelor reale în locul consolei (doar dacă nu ai ales fotografie)
    if (in_array((string)\App\Core\Settings::get('home_hero_style', 'animatie'), ['', 'animatie'], true)) {
        \App\Core\Settings::set('home_hero_style', 'proiecte');
    }

    \App\Core\Cache::clear();
};
