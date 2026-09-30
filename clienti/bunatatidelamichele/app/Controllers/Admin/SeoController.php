<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Cache;
use App\Core\DB;
use App\Core\Sanitizer;
use App\Core\Seo;
use App\Core\Settings;
use App\Core\Shop;

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
            } elseif ($k === 'seo_default_og') {
                Settings::set($k, is_string($raw) && $raw !== '' && DB::val('SELECT id FROM media WHERE path = ?', [$raw]) ? $raw : '');
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

    /** Audit SEO automat al produselor, categoriilor, paginilor și articolelor. */
    public function audit(): string
    {
        $items = [];
        $titles = [];
        $sets = [
            ['Produs', 'products', '/produs/', 'published = 1', 'description', 'short_description', 'name', '/admin/produse/', 150],
            ['Categorie', 'categories', '/categorie/', 'published = 1', 'body', 'intro', 'name', '/admin/c/categorii/', 120],
            ['Pagină', 'pages', '/', 'published = 1', 'body', 'subtitle', 'title', '/admin/c/pagini/', 80],
            ['Articol', 'posts', '/blog/', "status = 'published'", 'body', 'excerpt', 'title', '/admin/c/articole/', 300],
        ];
        foreach ($sets as [$type, $table, $prefix, $where, $bodyCol, $descCol, $nameCol, $edit, $minWords]) {
            foreach (DB::all("SELECT * FROM $table WHERE $where") as $r) {
                $name = (string)$r[$nameCol];
                $metaTitle = $r['meta_title'] ?: $name;
                $desc = $r['meta_description'] ?: excerpt((string)($r[$descCol] ?? ''), 300);
                $body = (string)($r[$bodyCol] ?? '');
                $words = str_word_count(strip_tags($body . ' ' . ($r[$descCol] ?? '')), 0, 'ăâîșțĂÂÎȘȚ');
                $issues = [];
                $score = 100;
                $tl = mb_strlen($metaTitle . ($r['meta_title'] ? '' : (string)Settings::get('seo_title_suffix')));
                if ($tl < 25) {
                    $issues[] = ['warn', "Titlu SEO scurt ($tl caractere) – ideal 45–60."];
                    $score -= 10;
                } elseif ($tl > 70) {
                    $issues[] = ['warn', "Titlu SEO lung ($tl caractere) – Google îl va tăia."];
                    $score -= 10;
                }
                $dl = mb_strlen($desc);
                if ($dl < 70) {
                    $issues[] = ['err', "Descriere SEO prea scurtă sau lipsă ($dl caractere)."];
                    $score -= 20;
                } elseif ($dl > 170 && $r['meta_description']) {
                    $issues[] = ['warn', "Descriere SEO lungă ($dl caractere) – ideal 140–160."];
                    $score -= 5;
                }
                if ($words < $minWords) {
                    $issues[] = [$table === 'pages' ? 'warn' : 'err', "Conținut puțin ($words cuvinte) – recomandat peste $minWords."];
                    $score -= 15;
                }
                if (in_array($table, ['products', 'posts'], true) && !preg_match('/<h2/i', $body)) {
                    $issues[] = ['warn', 'Lipsesc subtitlurile H2 – structurează descrierea.'];
                    $score -= 5;
                }
                if (preg_match_all('/<img(?![^>]*alt="[^"]+")[^>]*>/i', $body, $m)) {
                    $issues[] = ['warn', count($m[0]) . ' imagine(i) fără text alternativ.'];
                    $score -= 5;
                }
                if ($table === 'products') {
                    $imgs = Shop::images($r);
                    if (!$imgs) {
                        $issues[] = ['err', 'Produsul nu are imagine.'];
                        $score -= 25;
                    } elseif (count($imgs) < 2) {
                        $issues[] = ['info', 'O singură imagine – 2–4 fotografii cresc încrederea și conversia.'];
                        $score -= 3;
                    }
                    if (!$r['brand']) {
                        $issues[] = ['info', 'Completează brandul (apare în Google Shopping și în rezultatele bogate).'];
                        $score -= 3;
                    }
                    if (!$r['gtin'] && !$r['sku']) {
                        $issues[] = ['info', 'Fără cod produs / EAN – Google Shopping preferă produsele cu cod de bare.'];
                        $score -= 2;
                    }
                    if (!preg_match('#href="/(produs|categorie)/#', $body)) {
                        $issues[] = ['info', 'Niciun link intern către alte produse sau categorii.'];
                        $score -= 3;
                    }
                }
                if (in_array($table, ['categories', 'products'], true) && count(json_list($r['faq'])) < 2) {
                    $issues[] = ['info', 'Adaugă 2–3 întrebări frecvente (rezultate bogate în Google și AI).'];
                    $score -= 3;
                }
                if (!empty($r['noindex'])) {
                    $issues[] = ['info', 'Pagina este marcată noindex (ascunsă din Google).'];
                }
                $titles[mb_strtolower($metaTitle)][] = $type . ': ' . $name;
                $items[] = ['type' => $type, 'name' => $name, 'url' => $prefix . $r['slug'], 'edit' => $edit . $r['id'], 'score' => max(0, $score), 'issues' => $issues, 'words' => $words];
            }
        }
        $dups = array_filter($titles, fn($v) => count($v) > 1);
        usort($items, fn($a, $b) => $a['score'] <=> $b['score']);
        $s = fn($k) => (string)Settings::get($k);
        $global = [
            [$s('seo_google_verification') !== '', 'Google Search Console verificat', 'Adaugă codul de verificare în Setări SEO, apoi trimite sitemap-ul în Search Console.'],
            [$s('company_cui') !== '' && $s('company_address') !== '', 'Date complete despre firmă (CUI, adresă) – încredere pentru Google și clienți', 'Completează Setări → Firmă.'],
            [$s('seo_noindex_site') !== '1', 'Site-ul este vizibil pentru motoarele de căutare', 'Debifează „Ascunde tot site-ul” din Setări SEO.'],
            [(int)DB::val('SELECT COUNT(*) FROM reviews WHERE published = 1') >= 3, 'Cel puțin 3 recenzii reale publicate (stele în Google)', 'Cere clienților mulțumiți o recenzie și publică-le din Recenzii.'],
            [$s('merchant_feed') === '1', 'Feed Google Merchant Center activ', 'Activează feedul din Setări → Integrări și adaugă-l în Merchant Center: ' . abs_url('/feed/google-merchant.xml')],
            [(int)DB::val('SELECT COUNT(*) FROM media WHERE alt IS NULL OR alt = \'\'') === 0, 'Toate imaginile au text alternativ', 'Completează textul alternativ în Media.'],
            [str_starts_with(abs_url('/'), 'https://'), 'Site servit prin HTTPS', 'Activează SSL (AutoSSL în cPanel) și redirecționarea din .htaccess.'],
            [Settings::get('ga4_id') !== '' || Settings::get('gtm_id') !== '', 'Google Analytics 4 configurat (măsori vânzările din Google)', 'Adaugă ID-ul în Setări → Integrări.'],
        ];
        $avg = $items ? (int)round(array_sum(array_column($items, 'score')) / count($items)) : 0;
        return $this->render('seo/audit', ['items' => $items, 'dups' => $dups, 'global' => $global, 'avg' => $avg, 'title' => 'Audit SEO']);
    }

    public function pingIndexNow(): never
    {
        $urls = [abs_url('/'), abs_url('/produse'), abs_url('/despre-noi'), abs_url('/contact')];
        foreach (DB::all('SELECT slug FROM products WHERE published = 1') as $r) {
            $urls[] = abs_url('/produs/' . $r['slug']);
        }
        foreach (DB::all('SELECT slug FROM categories WHERE published = 1') as $r) {
            $urls[] = abs_url('/categorie/' . $r['slug']);
        }
        foreach (DB::all("SELECT slug FROM posts WHERE status = 'published'") as $r) {
            $urls[] = abs_url('/blog/' . $r['slug']);
        }
        Seo::indexNow($urls);
        flash('ok', count($urls) . ' adrese trimise către IndexNow (Bing, Yandex, Seznam…). Pentru Google, trimite sitemap-ul din Search Console.');
        redirect('/admin/seo');
    }
}
