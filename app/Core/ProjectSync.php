<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Sincronizează proiectele din app/Data/seed_projects.php în baza de date (după slug) și importă capturile site-urilor.
 * Folosit de migrări când lista de proiecte confirmată de VITIM se schimbă.
 */
final class ProjectSync
{
    public static function run(): void
    {
        $now = DB::now();
        $hasResult = in_array('result', DB::columns('projects'), true);
        foreach (require APP_PATH . '/Data/seed_projects.php' as $p) {
            $body = '<h2>Problema</h2><p>' . e($p['problem']) . '</p>'
                . '<h2>Soluția VITIM</h2><p>' . e($p['solution']) . '</p>'
                . '<h2>Ce am implementat</h2><ul>' . implode('', array_map(fn($f) => '<li>' . e($f) . '</li>', $p['features'])) . '</ul>'
                . (!empty($p['result']) ? '<h2>Rezultatul</h2><p>' . e($p['result']) . '</p>' : '');
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
                'site' => $p['site'] ?: null,
                'sort' => $p['sort'],
                'published' => 1,
                'meta_title' => $p['client'] . ($p['own'] ? ' – proiect VITIM' : ' – studiu de caz VITIM'),
                'meta_description' => mb_substr($p['summary'] . ' ' . $p['solution'], 0, 158),
                'updated_at' => $now,
            ];
            if ($hasResult) {
                $row['result'] = $p['result'] ?? null;
            }
            $alt = 'Site-ul ' . ($p['site'] ?: $p['client']) . ' (' . $p['client'] . ')';
            if ($c = self::image($p['slug'] . '.webp', $alt . ' pe calculator')) {
                $row['cover'] = $c;
            }
            if ($c = self::image($p['slug'] . '-m.webp', $alt . ' pe telefon')) {
                $row['cover_mobile'] = $c;
            }
            $id = DB::val('SELECT id FROM projects WHERE slug = ?', [$p['slug']]);
            if ($id) {
                DB::update('projects', $row, 'id = :id', ['id' => $id]);
            } else {
                DB::insert('projects', $row + ['slug' => $p['slug'], 'results' => '[]', 'noindex' => 0, 'created_at' => $now]);
            }
            // un proiect publicat din nou nu mai are nevoie de redirecționarea către /proiecte
            DB::delete('redirects', 'from_path = ?', ['/proiecte/' . $p['slug']]);
        }
        Cache::clear();
    }

    private static function image(string $file, string $alt): ?string
    {
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
    }
}
