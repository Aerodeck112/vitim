<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\DB;
use App\Core\Sanitizer;
use App\Core\Shop;

final class ProductController extends AdminController
{
    protected string $area = 'products';

    public function index(): string
    {
        $where = ['1=1'];
        $p = [];
        if (($q = str_input('q')) !== '') {
            $where[] = '(p.name LIKE :q1 OR p.sku LIKE :q2 OR p.brand LIKE :q3)';
            $p += ['q1' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%"];
        }
        if (($c = (int)str_input('categorie')) > 0) {
            $where[] = 'p.category_id = :c';
            $p['c'] = $c;
        }
        $f = str_input('filtru');
        if ($f === 'ascunse') {
            $where[] = 'p.published = 0';
        } elseif ($f === 'stoc') {
            $where[] = "(p.stock_status = 'outofstock' OR (p.manage_stock = 1 AND p.stock <= " . (int)setting('low_stock_threshold', '5') . '))';
        } elseif ($f === 'reducere') {
            $where[] = 'p.sale_price IS NOT NULL AND p.sale_price > 0';
        }
        $rows = DB::all('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE ' . implode(' AND ', $where) . ' ORDER BY p.sort, p.name', $p);
        return $this->render('products/index', ['rows' => $rows, 'q' => $q, 'cats' => DB::all('SELECT id, name FROM categories ORDER BY sort, name'), 'title' => 'Produse']);
    }

    public function edit(?string $id = null): string
    {
        $row = $id ? DB::row('SELECT * FROM products WHERE id = ?', [(int)$id]) : null;
        if ($id && !$row) {
            flash('err', 'Produsul nu există.');
            redirect('/admin/produse');
        }
        $errors = [];
        if ($this->isPost()) {
            [$data, $errors] = $this->collect($row);
            if (!$errors) {
                $now = DB::now();
                $data['updated_at'] = $now;
                if ($row) {
                    DB::update('products', $data, 'id = :id', ['id' => $row['id']]);
                    $newId = (int)$row['id'];
                    if ($data['slug'] !== $row['slug']) {
                        $from = '/produs/' . $row['slug'];
                        $to = '/produs/' . $data['slug'];
                        DB::delete('redirects', 'from_path = ?', [$to]);
                        DB::delete('redirects', 'from_path = ?', [$from]);
                        DB::q('UPDATE redirects SET to_path = ? WHERE to_path = ?', [$to, $from]);
                        DB::insert('redirects', ['from_path' => $from, 'to_path' => $to, 'code' => 301, 'hits' => 0, 'created_at' => $now]);
                        flash('info', "Adresa s-a schimbat – am creat automat redirecționarea 301 $from → $to (pozițiile din Google se păstrează).");
                    }
                } else {
                    $data['created_at'] = $now;
                    $newId = DB::insert('products', $data);
                    DB::delete('redirects', 'from_path = ?', ['/produs/' . $data['slug']]);
                }
                $this->contentChanged(['/produs/' . $data['slug'], '/produse']);
                flash('ok', 'Produsul a fost salvat.');
                redirect('/admin/produse/' . $newId);
            }
            $row = array_merge($row ?? [], $_POST);
        }
        return $this->render('products/edit', [
            'row' => $row,
            'errors' => $errors,
            'cats' => DB::all('SELECT id, name FROM categories ORDER BY sort, name'),
            'title' => $row && !empty($row['id']) ? 'Editează produs' : 'Produs nou',
        ]);
    }

    private function money(string $key): ?float
    {
        $v = trim(str_replace([' ', ','], ['', '.'], str_input($key)));
        return $v === '' ? null : round((float)$v, 2);
    }

    private function collect(?array $row): array
    {
        $errors = [];
        $name = Sanitizer::text(str_input('name'), 250);
        $slug = slugify(str_input('slug') !== '' ? str_input('slug') : $name);
        $price = $this->money('price');
        $sale = $this->money('sale_price');
        $images = [];
        foreach (json_decode(str_input('images', '[]'), true) ?: [] as $img) {
            if (is_string($img) && DB::val('SELECT id FROM media WHERE path = ?', [$img])) {
                $images[] = $img;
            }
        }
        $attrs = [];
        foreach (json_decode(str_input('attributes', '[]'), true) ?: [] as $a) {
            if (is_array($a) && trim((string)($a['name'] ?? '')) !== '') {
                $attrs[] = ['name' => Sanitizer::text((string)$a['name'], 100), 'value' => Sanitizer::text((string)($a['value'] ?? ''), 300)];
            }
        }
        $faq = [];
        foreach (json_decode(str_input('faq', '[]'), true) ?: [] as $f) {
            if (is_array($f) && trim((string)($f['q'] ?? '')) !== '') {
                $faq[] = ['q' => Sanitizer::text((string)$f['q'], 300), 'a' => Sanitizer::text((string)($f['a'] ?? ''), 2000)];
            }
        }
        $cat = (int)str_input('category_id');
        $saleUntil = str_input('sale_until');
        $data = [
            'name' => $name,
            'slug' => $slug,
            'category_id' => $cat > 0 && DB::val('SELECT id FROM categories WHERE id = ?', [$cat]) ? $cat : null,
            'brand' => Sanitizer::text(str_input('brand'), 100) ?: null,
            'sku' => Sanitizer::text(str_input('sku'), 60) ?: null,
            'gtin' => preg_replace('/\D/', '', str_input('gtin')) ?: null,
            'short_description' => Sanitizer::html(str_input('short_description')),
            'description' => Sanitizer::html(str_input('description')),
            'price' => $price ?? 0,
            'sale_price' => $sale !== null && $sale > 0 ? $sale : null,
            'sale_until' => $saleUntil !== '' && ($ts = strtotime($saleUntil)) ? gmdate('Y-m-d H:i:s', $ts) : null,
            'unit' => Sanitizer::text(str_input('unit', 'buc'), 20) ?: 'buc',
            'price_note' => Sanitizer::text(str_input('price_note'), 200) ?: null,
            'min_qty' => max(1, (int)str_input('min_qty', '1')),
            'qty_step' => max(1, (int)str_input('qty_step', '1')),
            'weight_g' => max(0, (int)str_input('weight_g', '0')),
            'manage_stock' => !empty($_POST['manage_stock']) ? 1 : 0,
            'stock' => (int)str_input('stock', '0'),
            'stock_status' => in_array(str_input('stock_status'), ['instock', 'outofstock', 'onbackorder'], true) ? str_input('stock_status') : 'instock',
            'images' => json_encode($images),
            'attributes' => json_encode($attrs, JSON_UNESCAPED_UNICODE),
            'faq' => json_encode($faq, JSON_UNESCAPED_UNICODE),
            'badge' => Sanitizer::text(str_input('badge'), 30) ?: null,
            'featured' => !empty($_POST['featured']) ? 1 : 0,
            'published' => !empty($_POST['published']) ? 1 : 0,
            'sort' => (int)str_input('sort', '0'),
            'meta_title' => Sanitizer::text(str_input('meta_title'), 255) ?: null,
            'meta_description' => Sanitizer::text(str_input('meta_description'), 400),
            'canonical' => Sanitizer::text(str_input('canonical'), 255) ?: null,
            'noindex' => !empty($_POST['noindex']) ? 1 : 0,
            'og_image' => ($og = str_input('og_image')) !== '' && DB::val('SELECT id FROM media WHERE path = ?', [$og]) ? $og : null,
        ];
        if ($name === '') {
            $errors[] = 'Completează numele produsului.';
        }
        if ($price === null || $price <= 0) {
            $errors[] = 'Prețul trebuie să fie mai mare decât 0.';
        }
        if ($data['sale_price'] !== null && $data['sale_price'] >= $data['price']) {
            $errors[] = 'Prețul redus trebuie să fie mai mic decât prețul normal.';
        }
        if (DB::val('SELECT id FROM products WHERE slug = ? AND id <> ?', [$slug, (int)($row['id'] ?? 0)])) {
            $errors[] = "Adresa /produs/$slug este deja folosită de alt produs.";
        }
        if ($data['published'] && !$images) {
            $errors[] = 'Adaugă cel puțin o imagine – un produs fără poză nu se vinde (și Google Shopping îl respinge).';
        }
        return [$data, $errors];
    }

    public function quick(): never
    {
        $id = (int)input('id', 0);
        $p = DB::row('SELECT * FROM products WHERE id = ?', [$id]);
        if (!$p) {
            json_out(['ok' => false, 'message' => 'Produs inexistent.'], 404);
        }
        $upd = ['updated_at' => DB::now()];
        if (($v = $this->money('price')) !== null && $v > 0) {
            $upd['price'] = $v;
        }
        if (isset($_POST['stock']) && $_POST['stock'] !== '') {
            $upd['stock'] = (int)$_POST['stock'];
        }
        if (isset($_POST['published'])) {
            $upd['published'] = (int)!empty($_POST['published']);
        }
        DB::update('products', $upd, 'id = :id', ['id' => $id]);
        $this->contentChanged();
        json_out(['ok' => true, 'message' => 'Salvat.']);
    }

    public function duplicate(string $id): never
    {
        $p = DB::row('SELECT * FROM products WHERE id = ?', [(int)$id]);
        if ($p) {
            unset($p['id']);
            $base = $p['slug'] . '-copie';
            $slug = $base;
            $i = 2;
            while (DB::val('SELECT id FROM products WHERE slug = ?', [$slug])) {
                $slug = $base . '-' . $i++;
            }
            $p['slug'] = $slug;
            $p['name'] .= ' (copie)';
            $p['published'] = 0;
            $p['sales_count'] = 0;
            $p['created_at'] = $p['updated_at'] = DB::now();
            $new = DB::insert('products', $p);
            flash('ok', 'Produsul a fost duplicat (ascuns până îl publici).');
            redirect('/admin/produse/' . $new);
        }
        redirect('/admin/produse');
    }

    public function delete(string $id): never
    {
        $p = DB::row('SELECT * FROM products WHERE id = ?', [(int)$id]);
        if ($p) {
            if ((int)DB::val('SELECT COUNT(*) FROM order_items WHERE product_id = ?', [$p['id']]) > 0) {
                DB::update('products', ['published' => 0, 'updated_at' => DB::now()], 'id = :id', ['id' => $p['id']]);
                flash('info', 'Produsul apare în comenzi, așa că l-am ascuns în loc să-l șterg. Istoricul comenzilor rămâne intact.');
            } else {
                DB::delete('products', 'id = ?', [$p['id']]);
                DB::delete('reviews', 'product_id = ?', [$p['id']]);
                flash('ok', 'Produsul a fost șters.');
            }
            $from = '/produs/' . $p['slug'];
            if (!DB::val('SELECT id FROM redirects WHERE from_path = ?', [$from])) {
                $to = $p['category_id'] ? '/categorie/' . DB::val('SELECT slug FROM categories WHERE id = ?', [$p['category_id']]) : '/produse';
                DB::insert('redirects', ['from_path' => $from, 'to_path' => $to, 'code' => 301, 'hits' => 0, 'created_at' => DB::now()]);
            }
            $this->contentChanged();
        }
        redirect('/admin/produse');
    }
}
