<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\App;
use App\Core\DB;
use App\Core\Sanitizer;

/**
 * Editor generic pentru categorii, pagini, coduri de reducere, articole și recenzii.
 */
final class ContentController extends AdminController
{
    protected string $area = 'content';

    public function __construct()
    {
        $res = explode('/', trim(\App\Core\App::$path, '/'))[2] ?? '';
        $this->area = match ($res) {
            'cupoane' => 'orders',
            'categorii', 'recenzii' => 'products',
            default => 'content',
        };
        parent::__construct();
    }

    private function res(string $key): array
    {
        $all = require APP_PATH . '/Data/admin_resources.php';
        if (!isset($all[$key])) {
            App::notFound(App::$path, true);
            exit;
        }
        $r = $all[$key];
        $r['key'] = $key;
        foreach ($r['fields'] as &$f) {
            if (isset($f['options']) && $f['options'] instanceof \Closure) {
                $f['options'] = ($f['options'])();
            }
        }
        return $r;
    }

    public function index(string $res): string
    {
        $r = $this->res($res);
        $where = ['1=1'];
        $params = [];
        $q = str_input('q');
        if ($q !== '') {
            $or = [];
            foreach ($r['search'] as $i => $col) {
                $or[] = "$col LIKE :q$i";
                $params["q$i"] = '%' . $q . '%';
            }
            $where[] = '(' . implode(' OR ', $or) . ')';
        }
        foreach ($r['filters'] ?? [] as $col => $opts) {
            $v = str_input('f_' . $col);
            if ($v !== '' && isset($opts[$v])) {
                $where[] = "$col = :f_$col";
                $params["f_$col"] = $v;
            }
        }
        $w = implode(' AND ', $where);
        $total = (int)DB::val("SELECT COUNT(*) FROM {$r['table']} WHERE $w", $params);
        $pg = $this->paginate($total, 50, (int)($_GET['p'] ?? 1));
        $rows = DB::all("SELECT * FROM {$r['table']} WHERE $w ORDER BY {$r['order']} LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
        return $this->render('content/index', ['r' => $r, 'rows' => $rows, 'pg' => $pg, 'q' => $q, 'title' => $r['label']]);
    }

    public function edit(string $res, ?string $id = null): string
    {
        $r = $this->res($res);
        $row = $id ? DB::row("SELECT * FROM {$r['table']} WHERE id = ?", [(int)$id]) : null;
        if ($id && !$row) {
            flash('err', 'Elementul nu există.');
            redirect('/admin/c/' . $res);
        }
        $errors = [];
        if ($this->isPost()) {
            [$data, $errors] = $this->collect($r, $row);
            if (!$errors) {
                $now = DB::now();
                $data['updated_at'] = $now;
                if (!in_array('updated_at', DB::columns($r['table']), true)) {
                    unset($data['updated_at']);
                }
                if ($row) {
                    DB::update($r['table'], $data, 'id = :id', ['id' => $row['id']]);
                    $newId = (int)$row['id'];
                } else {
                    $data['created_at'] = $now;
                    $newId = DB::insert($r['table'], $data);
                }
                // slug schimbat → redirecționare automată de la adresa veche
                if ($row && isset($data['slug'], $row['slug']) && $data['slug'] !== $row['slug'] && $r['url']) {
                    $from = str_replace('{slug}', $row['slug'], $r['url']);
                    $to = str_replace('{slug}', $data['slug'], $r['url']);
                    DB::delete('redirects', 'from_path = ?', [$to]);
                    if (!DB::val('SELECT id FROM redirects WHERE from_path = ?', [$from])) {
                        DB::insert('redirects', ['from_path' => $from, 'to_path' => $to, 'code' => 301, 'hits' => 0, 'created_at' => $now]);
                    }
                    flash('info', "Adresa s-a schimbat – am creat automat redirecționarea 301 $from → $to.");
                }
                $saved = DB::row("SELECT * FROM {$r['table']} WHERE id = ?", [$newId]);
                $this->contentChanged($r['url'] && !empty($saved['slug']) ? [str_replace('{slug}', $saved['slug'], $r['url'])] : []);
                flash('ok', ucfirst($r['singular']) . ' salvat' . (in_array($r['singular'], ['pagină', 'zonă'], true) ? 'ă' : '') . '.');
                redirect('/admin/c/' . $res . '/' . $newId);
            }
            $row = array_merge($row ?? [], $_POST);
        }
        return $this->render('content/edit', ['r' => $r, 'row' => $row, 'errors' => $errors, 'title' => ($row && !empty($row['id']) ? 'Editează ' : 'Adaugă ') . $r['singular']]);
    }

    private function collect(array $r, ?array $row): array
    {
        $data = [];
        $errors = [];
        $cols = DB::columns($r['table']);
        foreach ($r['fields'] as $f) {
            $n = $f['name'];
            $raw = $_POST[$n] ?? null;
            switch ($f['type']) {
                case 'checkbox':
                    $v = !empty($raw) ? 1 : 0;
                    break;
                case 'number':
                    $v = (int)($raw ?? 0);
                    break;
                case 'decimal':
                    $v = round((float)str_replace(',', '.', (string)($raw ?? '0')), 2);
                    break;
                case 'richtext':
                    $v = Sanitizer::html(is_string($raw) ? $raw : '');
                    break;
                case 'repeater':
                    $list = json_decode(is_string($raw) ? $raw : '[]', true);
                    $clean = [];
                    foreach (is_array($list) ? $list : [] as $it) {
                        if (!is_array($it)) {
                            continue;
                        }
                        $o = [];
                        foreach ($f['fields'] as $sf) {
                            $o[$sf['key']] = Sanitizer::text((string)($it[$sf['key']] ?? ''), 3000);
                        }
                        if (implode('', $o) !== '') {
                            $clean[] = $o;
                        }
                    }
                    $v = json_encode($clean, JSON_UNESCAPED_UNICODE);
                    break;
                case 'datetime':
                    $s = is_string($raw) ? trim($raw) : '';
                    $v = $s !== '' && ($ts = strtotime($s)) ? gmdate('Y-m-d H:i:s', $ts) : null;
                    break;
                case 'select':
                    $v = is_string($raw) ? $raw : '';
                    if ($v !== '' && !array_key_exists($v, $f['options'])) {
                        $v = '';
                    }
                    if ($v === '' && str_ends_with($n, '_id')) {
                        $v = null;
                    }
                    break;
                case 'slug':
                    $v = slugify(is_string($raw) && trim($raw) !== '' ? $raw : (string)($_POST[$f['from']] ?? ''));
                    break;
                case 'image':
                    $v = is_string($raw) ? trim($raw) : '';
                    if ($v !== '' && !DB::val('SELECT id FROM media WHERE path = ?', [$v])) {
                        $v = '';
                    }
                    $v = $v ?: null;
                    break;
                default:
                    $v = Sanitizer::text(is_string($raw) ? $raw : '', $f['type'] === 'textarea' ? 5000 : 255);
            }
            if (!empty($f['required']) && ($v === '' || $v === null)) {
                $errors[] = 'Câmpul „' . $f['label'] . '” este obligatoriu.';
            }
            if (in_array($n, $cols, true)) {
                $data[$n] = $v;
            }
        }
        if (isset($data['slug'])) {
            $exists = DB::val("SELECT id FROM {$r['table']} WHERE slug = ? AND id <> ?", [$data['slug'], (int)($row['id'] ?? 0)]);
            if ($exists) {
                $errors[] = 'Adresa (slug) „' . $data['slug'] . '” este deja folosită.';
            }
            $reserved = ['admin', 'produse', 'produs', 'categorie', 'cos', 'finalizare', 'comanda', 'plata', 'urmarire-comanda', 'blog', 'contact', 'despre-noi', 'api', 'sitemap', 'cron', 'install', 'assets', 'uploads', 'feed', 'og'];
            if ($r['key'] === 'pagini' && in_array($data['slug'], $reserved, true) && ($row['slug'] ?? '') !== $data['slug']) {
                $errors[] = 'Adresa „' . $data['slug'] . '” este rezervată.';
            }
        }
        if (!empty($r['seo'])) {
            $data['meta_title'] = Sanitizer::text(str_input('meta_title'), 255) ?: null;
            $data['meta_description'] = Sanitizer::text(str_input('meta_description'), 400);
            $data['canonical'] = Sanitizer::text(str_input('canonical'), 255) ?: null;
            $data['noindex'] = !empty($_POST['noindex']) ? 1 : 0;
            $og = str_input('og_image');
            $data['og_image'] = $og !== '' && DB::val('SELECT id FROM media WHERE path = ?', [$og]) ? $og : null;
        }
        if ($r['key'] === 'articole') {
            if (($data['status'] ?? '') === 'published' && empty($data['published_at'])) {
                $data['published_at'] = DB::now();
            }
            if (empty($data['author_id'])) {
                $data['author_id'] = (int)(\App\Core\Auth::user()['id'] ?? 0) ?: null;
            }
        }
        if ($r['key'] === 'cupoane') {
            $data['code'] = mb_strtoupper((string)preg_replace('/[^A-Za-z0-9_-]/', '', (string)$data['code']));
            if ($data['code'] === '') {
                $errors[] = 'Codul poate conține doar litere, cifre, - și _.';
            } elseif (DB::val('SELECT id FROM coupons WHERE UPPER(code) = ? AND id <> ?', [$data['code'], (int)($row['id'] ?? 0)])) {
                $errors[] = 'Există deja un cod de reducere „' . $data['code'] . '”.';
            }
            if ($data['type'] === 'percent' && ($data['value'] <= 0 || $data['value'] > 100)) {
                $errors[] = 'Reducerea procentuală trebuie să fie între 1 și 100.';
            }
            if ($data['type'] === 'fixed' && $data['value'] <= 0 && !$data['free_shipping']) {
                $errors[] = 'Completează valoarea reducerii.';
            }
        }
        if ($r['key'] === 'recenzii') {
            $data['rating'] = max(1, min(5, (int)($data['rating'] ?? 5)));
        }
        return [$data, $errors];
    }

    public function delete(string $res, string $id): never
    {
        $r = $this->res($res);
        $row = DB::row("SELECT * FROM {$r['table']} WHERE id = ?", [(int)$id]);
        if ($row && isset($row['slug']) && in_array($row['slug'], $r['protected'] ?? [], true)) {
            flash('err', 'Această pagină este necesară site-ului și nu poate fi ștearsă. O poți edita.');
            redirect('/admin/c/' . $res);
        }
        if ($row) {
            DB::delete($r['table'], 'id = ?', [(int)$id]);
            $isCat = $r['table'] === 'categories';
            if ($isCat) {
                DB::q('UPDATE products SET category_id = NULL WHERE category_id = ?', [(int)$id]);
            }
            if ($r['url'] && !empty($row['slug'])) {
                $from = str_replace('{slug}', $row['slug'], $r['url']);
                if (!DB::val('SELECT id FROM redirects WHERE from_path = ?', [$from])) {
                    // o categorie ștearsă trimite vizitatorii la toate produsele; restul conținutului răspunde cu 410
                    DB::insert('redirects', ['from_path' => $from, 'to_path' => $isCat ? '/produse' : '', 'code' => $isCat ? 301 : 410, 'hits' => 0, 'created_at' => DB::now()]);
                }
            }
            $this->contentChanged();
            flash('ok', $r['url'] ? ($isCat ? 'Categoria a fost ștearsă; adresa ei trimite acum la toate produsele.' : 'Șters. Adresa veche răspunde acum cu 410 (Google o va scoate din index). Poți schimba asta în Redirecționări.') : 'Șters.');
        }
        redirect('/admin/c/' . $res);
    }

    public function reorder(string $res): never
    {
        $r = $this->res($res);
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        foreach ($ids as $i => $rid) {
            DB::update($r['table'], ['sort' => ($i + 1) * 10], 'id = :id', ['id' => $rid]);
        }
        $this->contentChanged();
        json_out(['ok' => true]);
    }
}
