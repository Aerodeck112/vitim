<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Cache;
use App\Core\DB;
use App\Core\Sanitizer;
use App\Core\Seo;
use App\Core\Settings;

final class SeoController extends AdminController
{
    protected string $area = 'seo';

    private const FIELDS = [
        'seo_home_title' => 'Titlu SEO prima pagină',
        'seo_home_description' => 'Descriere SEO prima pagină',
        'seo_title_suffix' => 'Sufix titlu (adăugat automat)',
        'seo_default_og' => 'Imagine implicită pentru distribuire',
        'seo_google_verification' => 'Google Search Console – cod verificare (meta)',
        'seo_bing_verification' => 'Bing Webmaster – cod verificare',
        'seo_indexnow_key' => 'Cheie IndexNow',
        'seo_llms_intro' => 'Descriere firmă pentru asistenți AI (llms.txt)',
        'seo_robots_extra' => 'Reguli suplimentare robots.txt',
        'seo_noindex_site' => 'Ascunde tot site-ul din motoarele de căutare',
    ];

    public function index(): string
    {
        return $this->render('seo/index', ['fields' => self::FIELDS, 'title' => 'Setări SEO']);
    }

    public function save(): never
    {
        foreach (self::FIELDS as $k => $label) {
            $raw = $_POST[$k] ?? '';
            if ($k === 'seo_noindex_site') {
                Settings::set($k, !empty($raw) ? '1' : '0');
            } elseif ($k === 'seo_google_verification' && preg_match('/content="([^"]+)"/', (string)$raw, $m)) {
                Settings::set($k, $m[1]); // acceptăm și eticheta meta lipită întreagă
            } elseif ($k === 'seo_indexnow_key') {
                Settings::set($k, preg_replace('/[^a-f0-9]/', '', strtolower((string)$raw)) ?: bin2hex(random_bytes(16)));
            } else {
                Settings::set($k, Sanitizer::text((string)$raw, 2000));
            }
        }
        Cache::clear();
        flash('ok', 'Setările SEO au fost salvate.');
        redirect('/admin/seo');
    }

    public function redirects(): string
    {
        $q = str_input('q');
        $params = [];
        $w = '1=1';
        if ($q !== '') {
            $w = '(from_path LIKE :q OR to_path LIKE :q2)';
            $params = ['q' => "%$q%", 'q2' => "%$q%"];
        }
        return $this->render('seo/redirects', [
            'rows' => DB::all("SELECT * FROM redirects WHERE $w ORDER BY id DESC LIMIT 500", $params),
            'nf' => DB::all('SELECT * FROM not_found_log ORDER BY hits DESC, last_seen_at DESC LIMIT 100'),
            'q' => $q,
            'title' => 'Redirecționări',
        ]);
    }

    public function redirectSave(): never
    {
        $from = '/' . ltrim(trim(str_input('from_path')), '/');
        $from = (string)(parse_url($from, PHP_URL_PATH) ?: $from);
        $from = $from !== '/' ? rtrim($from, '/') : $from;
        $to = trim(str_input('to_path'));
        $code = (int)str_input('code', '301');
        if (!in_array($code, [301, 302, 410], true)) {
            $code = 301;
        }
        if ($from === '/' || $from === '') {
            flash('err', 'Adresa sursă nu poate fi prima pagină.');
        } elseif ($code !== 410 && $to === '') {
            flash('err', 'Completează adresa destinație.');
        } elseif ($from === $to) {
            flash('err', 'Sursa și destinația sunt identice.');
        } else {
            DB::delete('redirects', 'from_path = ?', [$from]);
            DB::insert('redirects', ['from_path' => $from, 'to_path' => $code === 410 ? '' : $to, 'code' => $code, 'hits' => 0, 'created_at' => DB::now()]);
            DB::delete('not_found_log', 'path = ?', [$from]);
            Cache::clear();
            flash('ok', "Redirecționare salvată: $from → " . ($code === 410 ? '410 (dispărut)' : $to));
        }
        redirect('/admin/seo/redirectionari');
    }

    public function redirectDelete(string $id): never
    {
        DB::delete('redirects', 'id = ?', [(int)$id]);
        Cache::clear();
        flash('ok', 'Redirecționare ștearsă.');
        redirect('/admin/seo/redirectionari');
    }

    public function notFoundDelete(string $id): never
    {
        if ((int)$id === 0) {
            DB::q('DELETE FROM not_found_log');
        } else {
            DB::delete('not_found_log', 'id = ?', [(int)$id]);
        }
        redirect('/admin/seo/redirectionari');
    }

    /** Audit SEO automat al conținutului. */
    public function audit(): string
    {
        $items = [];
        $titles = [];
        $sets = [
            ['Serviciu', 'services', '/servicii/', "published = 1", 'body', 'excerpt'],
            ['Zonă', 'locations', '/zone/', "published = 1", 'body', 'intro'],
            ['Articol', 'posts', '/blog/', "status = 'published'", 'body', 'excerpt'],
            ['Pagină', 'pages', '/', "published = 1", 'body', 'subtitle'],
        ];
        foreach ($sets as [$type, $table, $prefix, $where, $bodyCol, $descCol]) {
            foreach (DB::all("SELECT * FROM $table WHERE $where") as $r) {
                $name = $r['title'] ?? $r['name'];
                $metaTitle = $r['meta_title'] ?: $name;
                $desc = $r['meta_description'] ?: (string)($r[$descCol] ?? '');
                $body = (string)($r[$bodyCol] ?? '');
                $words = str_word_count(strip_tags($body . ' ' . ($r[$descCol] ?? '')), 0, 'ăâîșțĂÂÎȘȚ');
                $issues = [];
                $score = 100;
                $tl = mb_strlen($metaTitle);
                if ($tl < 25) {
                    $issues[] = ['warn', "Titlu SEO scurt ($tl caractere) – ideal 45–60."];
                    $score -= 10;
                } elseif ($tl > 65) {
                    $issues[] = ['warn', "Titlu SEO lung ($tl caractere) – Google îl va tăia."];
                    $score -= 10;
                }
                $dl = mb_strlen($desc);
                if ($dl < 70) {
                    $issues[] = ['err', "Descriere SEO prea scurtă sau lipsă ($dl caractere)."];
                    $score -= 20;
                } elseif ($dl > 170) {
                    $issues[] = ['warn', "Descriere SEO lungă ($dl caractere) – ideal 140–160."];
                    $score -= 5;
                }
                $minWords = $table === 'locations' && $r['type'] !== 'judet' ? 60 : ($table === 'pages' ? 80 : 300);
                if ($words < $minWords && !in_array($r['slug'] ?? '', ['contact'], true)) {
                    $issues[] = [$table === 'locations' ? 'warn' : 'err', "Conținut puțin ($words cuvinte) – recomandat peste $minWords."];
                    $score -= $table === 'locations' ? 10 : 20;
                }
                if (in_array($table, ['services', 'posts'], true) && !preg_match('/<h2/i', $body)) {
                    $issues[] = ['warn', 'Lipsesc subtitlurile H2 – structurează textul.'];
                    $score -= 10;
                }
                if (preg_match_all('/<img(?![^>]*alt="[^"]+")[^>]*>/i', $body, $m)) {
                    $issues[] = ['warn', count($m[0]) . ' imagine(i) fără text alternativ.'];
                    $score -= 5;
                }
                if ($table === 'services' && count(json_list($r['faq'])) < 3) {
                    $issues[] = ['warn', 'Adaugă cel puțin 3 întrebări frecvente (rezultate bogate în Google și AI).'];
                    $score -= 5;
                }
                if (in_array($table, ['services', 'posts'], true) && !preg_match('#href="/(servicii|blog|zone)/#', $body)) {
                    $issues[] = ['info', 'Niciun link intern către alte servicii/articole.'];
                    $score -= 5;
                }
                if ($table === 'posts' && empty($r['cover'])) {
                    $issues[] = ['info', 'Articolul nu are imagine principală.'];
                    $score -= 3;
                }
                if (!empty($r['noindex'])) {
                    $issues[] = ['info', 'Pagina este marcată noindex (ascunsă din Google).'];
                }
                $titles[mb_strtolower($metaTitle)][] = $type . ': ' . $name;
                $editRes = ['services' => 'servicii', 'locations' => 'zone', 'posts' => 'articole', 'pages' => 'pagini'][$table];
                $items[] = [
                    'type' => $type, 'name' => $name, 'url' => $prefix . $r['slug'], 'edit' => '/admin/c/' . $editRes . '/' . $r['id'],
                    'score' => max(0, $score), 'issues' => $issues, 'words' => $words,
                ];
            }
        }
        $dups = array_filter($titles, fn($v) => count($v) > 1);
        usort($items, fn($a, $b) => $a['score'] <=> $b['score']);
        $global = [];
        $s = fn($k) => (string)Settings::get($k);
        $global[] = [$s('seo_google_verification') !== '', 'Google Search Console verificat', 'Adaugă codul de verificare mai jos, apoi trimite sitemap-ul în Search Console.'];
        $global[] = [$s('company_cui') !== '' && $s('company_address') !== '', 'Date complete despre firmă (NAP) pentru SEO local', 'Completează adresa și CUI în Setări → Firmă.'];
        $global[] = [$s('google_business_url') !== '', 'Profil Google Business legat de site', 'Adaugă linkul profilului în Setări → Firmă.'];
        $global[] = [$s('seo_noindex_site') !== '1', 'Site-ul este vizibil pentru motoarele de căutare', 'Debifează „Ascunde tot site-ul” din Setări SEO.'];
        $global[] = [(int)DB::val('SELECT COUNT(*) FROM testimonials WHERE published = 1') >= 3, 'Cel puțin 3 testimoniale reale (stele în Google)', 'Cere recenzii clienților mulțumiți și adaugă-le la Testimoniale.'];
        $global[] = [(int)DB::val("SELECT COUNT(*) FROM posts WHERE status = 'published' AND published_at >= ?", [gmdate('Y-m-d', strtotime('-45 days'))]) > 0, 'Articol nou în ultimele 45 de zile', 'Un site actualizat constant e tratat mai bine de Google și de AI.'];
        $global[] = [(int)DB::val('SELECT COUNT(*) FROM media WHERE alt IS NULL OR alt = \'\'') === 0, 'Toate imaginile au text alternativ', 'Completează textul alternativ în Media.'];
        $global[] = [str_starts_with(abs_url('/'), 'https://'), 'Site servit prin HTTPS', 'Activează SSL (AutoSSL în cPanel) și redirecționarea din .htaccess.'];
        $avg = $items ? (int)round(array_sum(array_column($items, 'score')) / count($items)) : 0;
        return $this->render('seo/audit', ['items' => $items, 'dups' => $dups, 'global' => $global, 'avg' => $avg, 'title' => 'Audit SEO']);
    }

    public function pingIndexNow(): never
    {
        $urls = [abs_url('/'), abs_url('/servicii'), abs_url('/zone'), abs_url('/blog')];
        foreach (DB::all('SELECT slug FROM services WHERE published = 1') as $r) {
            $urls[] = abs_url('/servicii/' . $r['slug']);
        }
        foreach (DB::all('SELECT slug FROM locations WHERE published = 1') as $r) {
            $urls[] = abs_url('/zone/' . $r['slug']);
        }
        foreach (DB::all("SELECT slug FROM posts WHERE status = 'published'") as $r) {
            $urls[] = abs_url('/blog/' . $r['slug']);
        }
        Seo::indexNow($urls);
        flash('ok', count($urls) . ' adrese trimise către IndexNow (Bing, Yandex, Seznam…). Pentru Google, trimite sitemap-ul din Search Console.');
        redirect('/admin/seo');
    }
}
