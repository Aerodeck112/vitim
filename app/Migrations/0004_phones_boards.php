<?php
declare(strict_types=1);

use App\Core\DB;
use App\Core\Settings;
use App\Core\Uploader;

/**
 * v1.2: servicii noi „Reparații plăci de bază” și „Reparații telefoane și tablete”.
 * Pe instalările existente se adaugă doar dacă lipsesc; textele modificate de tine nu se ating.
 */
return function (): void {
    $seed = [];
    foreach (require APP_PATH . '/Data/seed_services.php' as $s) {
        $seed[$s['slug']] = $s;
    }

    $now = DB::now();
    $sort = (int)DB::val("SELECT sort FROM services WHERE slug = 'reparatii-it'") ?: 30;
    foreach (['reparatii-placi-de-baza' => 1, 'reparatii-telefoane-tablete' => 2] as $slug => $offset) {
        if (!isset($seed[$slug]) || DB::val('SELECT id FROM services WHERE slug = ?', [$slug])) {
            continue;
        }
        $s = $seed[$slug];
        DB::insert('services', [
            'slug' => $slug,
            'category' => $s['category'],
            'title' => $s['title'],
            'h1' => $s['h1'],
            'tagline' => $s['tagline'],
            'icon' => $s['icon'],
            'excerpt' => $s['excerpt'],
            'body' => trim($s['body']),
            'features' => json_encode($s['features'], JSON_UNESCAPED_UNICODE),
            'process' => '[]',
            'faq' => json_encode($s['faq'], JSON_UNESCAPED_UNICODE),
            'keywords' => $s['keywords'],
            'onsite' => (int)$s['onsite'],
            'featured' => (int)$s['featured'],
            'sort' => $sort + $offset,
            'published' => 1,
            'meta_title' => $s['meta_title'],
            'meta_description' => $s['meta_description'],
            'noindex' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    // fotografii (doar unde serviciul nu are deja una)
    foreach ([
        'reparatii-placi-de-baza' => ['reparatii-placi-de-baza', 'Tehnician lucrând cu șurubelnițe pe o placă de bază deschisă'],
        'reparatii-telefoane-tablete' => ['reparatii-telefoane-tablete', 'Componentele unui telefon mobil dezasamblat: placă de bază, cameră, conectori'],
    ] as $slug => [$file, $alt]) {
        $row = DB::row('SELECT id, image FROM services WHERE slug = ?', [$slug]);
        $src = APP_PATH . '/Data/images/' . $file . '.webp';
        if (!$row || !empty($row['image']) || !is_file($src)) {
            continue;
        }
        $path = DB::val('SELECT path FROM media WHERE original_name = ?', [$file . '.webp']);
        if (!$path) {
            $tmp = STORAGE_PATH . '/tmp/' . $file . '.webp';
            copy($src, $tmp);
            [$m] = Uploader::image(['name' => $file . '.webp', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)], $alt);
            @unlink($tmp);
            $path = $m['path'] ?? null;
        }
        if ($path) {
            DB::update('services', ['image' => $path], 'id = :id', ['id' => $row['id']]);
        }
    }

    // „Reparații IT” trimite spre serviciile noi (doar dacă textul e cel original)
    $it = DB::row("SELECT id, excerpt, body FROM services WHERE slug = 'reparatii-it'");
    if ($it) {
        $upd = [];
        $oldEx = 'Diagnoză și reparații pentru calculatoare, laptopuri, servere, imprimante și echipamente de rețea. Upgrade SSD și RAM, reinstalări, înlocuiri de componente – la sediu sau în atelier.';
        if ($it['excerpt'] === $oldEx && isset($seed['reparatii-it'])) {
            $upd['excerpt'] = $seed['reparatii-it']['excerpt'];
        }
        $li = '<li><strong>Echipamente de rețea</strong>: routere, switch-uri, Wi-Fi, cablare structurată.</li>' . "\n" . '</ul>';
        if (str_contains((string)$it['body'], $li) && !str_contains((string)$it['body'], 'reparatii-placi-de-baza')) {
            $add = '<li><strong>Echipamente de rețea</strong>: routere, switch-uri, Wi-Fi, cablare structurată.</li>' . "\n"
                . '<li><strong>Telefoane și tablete</strong>: vezi <a href="/servicii/reparatii-telefoane-tablete">reparații telefoane și tablete</a>.</li>' . "\n</ul>\n\n"
                . '<h2>Reparații pe placa de bază</h2>' . "\n"
                . '<p>Când defectul e pe placă (nu pornește, scurtcircuit, lichid vărsat), nu schimbăm placa întreagă: o <a href="/servicii/reparatii-placi-de-baza">reparăm la nivel de componentă</a>, cu microscop și stație de lipit profesională. Costă mai puțin și îți păstrezi aparatul.</p>';
            $upd['body'] = str_replace($li, $add, (string)$it['body']);
        }
        if ($upd) {
            $upd['updated_at'] = $now;
            DB::update('services', $upd, 'id = :id', ['id' => $it['id']]);
        }
    }

    // texte generale (doar dacă sunt cele implicite)
    $replace = [
        'seo_llms_intro' => [
            'oferă servicii IT (mentenanță, suport remote, reparații, recuperări de date, securitate cibernetică)',
            'oferă servicii IT (mentenanță, suport remote, reparații de calculatoare, laptopuri, telefoane și tablete, reparații de plăci de bază la nivel de componentă, recuperări de date, securitate cibernetică)',
        ],
        'seo_home_description' => [
            'Recuperări de date, securitate cibernetică,',
            'Reparații telefoane, tablete și plăci de bază, recuperări de date, securitate cibernetică,',
        ],
    ];
    foreach ($replace as $key => [$from, $to]) {
        $v = (string)Settings::get($key, '');
        if (str_contains($v, $from)) {
            Settings::set($key, str_replace($from, $to, $v));
        }
    }
    $sug = Settings::json('ai_suggestions');
    if ($sug === ['Am nevoie de mentenanță IT pentru firmă', 'Mi s-a stricat hard diskul', 'Cât costă un site nou?', 'Ce poate face un agent AI pentru noi?']) {
        Settings::set('ai_suggestions', ['Am nevoie de mentenanță IT pentru firmă', 'Telefonul nu mai pornește', 'Mi s-a stricat hard diskul', 'Ce poate face un agent AI pentru noi?']);
    }

    \App\Core\Cache::clear();
};
