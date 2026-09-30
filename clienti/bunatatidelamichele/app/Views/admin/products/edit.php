<?php
use App\Core\Csrf;
use App\Core\Shop;
use App\Core\View;

$r = $row ?? [];
$v = fn(string $k, $d = '') => $r[$k] ?? $d;
$imgs = is_array($v('images')) ? $v('images') : json_list((string)$v('images', '[]'));
$thumbs = [];
foreach ($imgs as $im) {
    $m = \App\Core\DB::row('SELECT variants FROM media WHERE path = ?', [$im]);
    $vv = json_list($m['variants'] ?? '[]');
    $thumbs[$im] = upload_url($vv[480] ?? $vv['480'] ?? $im);
}
$isNew = empty($r['id']);
$num = fn($x) => $x === null || $x === '' ? '' : number_format((float)$x, 2, '.', '');
?>
<div class="page-head">
  <div><h1><?= e($title) ?></h1><p><a href="<?= e(url('/admin/produse')) ?>">← Produse</a><?php if (!$isNew): ?> · <a href="<?= e(url('/produs/' . $r['slug'])) ?>" target="_blank">Vezi pe site <?= icon('external') ?></a><?php endif; ?></p></div>
</div>
<?php foreach ($errors as $er): ?><div class="alert alert-err"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" class="split">
  <?= Csrf::field() ?>
  <div>
    <div class="card f">
      <div class="fl"><label for="fld_name">Nume produs <span style="color:var(--err)">*</span></label><input class="in" id="fld_name" name="name" value="<?= e($v('name')) ?>" required style="font-size:17px;font-weight:600"></div>
      <div class="fl"><label>Adresă (URL)</label><div style="display:flex;align-items:center;gap:6px"><span class="muted small mono">/produs/</span><input class="in mono" name="slug" value="<?= e($v('slug')) ?>" data-slug-from="name" pattern="[a-z0-9-]*"></div><div class="hint">Dacă o schimbi, adresa veche se redirecționează automat (301).</div></div>
      <div class="fl" data-gallery-field data-thumbs='<?= e(json_encode($thumbs)) ?>'><label>Imagini <span class="small muted">(prima este imaginea principală; trage pentru a reordona)</span></label><input type="hidden" name="images" value="<?= e(json_encode($imgs)) ?>"><div class="gal"></div><div class="hint">Recomandat: fundal alb, pătrat, minim 800×800 px. Se convertesc automat în WebP.</div></div>
      <div class="fl"><label>Descriere scurtă (apare lângă preț)</label><textarea name="short_description" data-rte><?= e($v('short_description')) ?></textarea></div>
      <div class="fl"><label>Descriere completă</label><textarea name="description" data-rte><?= e($v('description')) ?></textarea><div class="hint">Pentru SEO: minimum 150–300 de cuvinte, cu subtitluri (H2) și linkuri către produse similare.</div></div>
    </div>

    <div class="card f">
      <h2>Specificații și întrebări</h2>
      <?= View::partial('admin/partials/field', ['f' => ['name' => 'attributes', 'label' => 'Specificații (apar în tabelul de pe pagina produsului)', 'type' => 'repeater', 'fields' => [['key' => 'name', 'label' => 'Denumire (ex: Cantitate)'], ['key' => 'value', 'label' => 'Valoare (ex: 1 kg)']]], 'val' => is_string($v('attributes', '[]')) ? $v('attributes', '[]') : json_encode($v('attributes'))]) ?>
      <?= View::partial('admin/partials/field', ['f' => ['name' => 'faq', 'label' => 'Întrebări frecvente despre produs (rezultate bogate în Google)', 'type' => 'repeater', 'fields' => [['key' => 'q', 'label' => 'Întrebare'], ['key' => 'a', 'label' => 'Răspuns', 'type' => 'textarea']]], 'val' => is_string($v('faq', '[]')) ? $v('faq', '[]') : json_encode($v('faq'))]) ?>
    </div>

    <div class="card f">
      <div class="card-h"><h2><?= icon('search') ?> SEO</h2><span class="small muted">Lasă gol pentru valorile automate</span></div>
      <div class="serp" data-serp data-title="#seo_t" data-desc="#seo_d" data-fb-title="#fld_name" data-fb-desc="#none">
        <div class="u"><?= e(parse_url(abs_url('/'), PHP_URL_HOST)) ?> › produs › <?= e($v('slug')) ?></div><div class="t"></div><div class="d"></div>
      </div>
      <div class="fl"><label for="seo_t">Titlu SEO</label><input class="in" id="seo_t" name="meta_title" value="<?= e($v('meta_title')) ?>" data-count="60"><div class="hint">Ideal 50–60 caractere: produs + beneficiu (ex: „Cafea boabe Bar Blend 1kg, prăjită la foc de lemn”).</div></div>
      <div class="fl"><label for="seo_d">Descriere SEO</label><textarea class="in" id="seo_d" name="meta_description" rows="2" data-count="160"><?= e($v('meta_description')) ?></textarea><div class="hint">Ideal 140–160 caractere, cu un îndemn (ex: „Livrare în 1–3 zile”).</div></div>
      <div class="row2">
        <div class="fl"><label>URL canonic (avansat)</label><input class="in" name="canonical" value="<?= e($v('canonical')) ?>" placeholder="automat"></div>
        <?= View::partial('admin/partials/field', ['f' => ['name' => 'og_image', 'label' => 'Imagine distribuire Facebook/WhatsApp (opțional)', 'type' => 'image'], 'val' => $v('og_image')]) ?>
      </div>
      <label class="chk"><input type="checkbox" name="noindex" value="1"<?= $v('noindex') ? ' checked' : '' ?>> Ascunde din Google (noindex)</label>
    </div>
  </div>

  <div style="position:sticky;top:76px">
    <div class="card f">
      <label class="chk"><input type="checkbox" name="published" value="1"<?= $v('published', 1) ? ' checked' : '' ?>> Publicat pe site</label>
      <label class="chk"><input type="checkbox" name="featured" value="1"<?= $v('featured') ? ' checked' : '' ?>> Recomandat (prima pagină)</label>
      <div class="actions"><button class="btn btn-p" type="submit" style="flex:1"><?= icon('check') ?> Salvează</button></div>
      <?php if (!empty($r['updated_at']) && !$isNew): ?><p class="small muted" style="margin:0">Ultima modificare: <?= e(local_time($r['updated_at'])) ?> · vândute: <?= (int)$v('sales_count') ?></p><?php endif; ?>
    </div>
    <div class="card f">
      <h2>Preț</h2>
      <div class="row2">
        <div class="fl"><label>Preț (lei) *</label><input class="in money" name="price" value="<?= e($num($v('price'))) ?>" inputmode="decimal" required></div>
        <div class="fl"><label>Preț redus (lei)</label><input class="in money" name="sale_price" value="<?= e($num($v('sale_price'))) ?>" inputmode="decimal" placeholder="—"></div>
      </div>
      <div class="fl"><label>Reducerea expiră la (opțional)</label><input class="in" type="datetime-local" name="sale_until" value="<?= $v('sale_until') ? e(local_time((string)$v('sale_until'), 'Y-m-d\TH:i')) : '' ?>"></div>
      <div class="row2">
        <div class="fl"><label>Unitate</label><input class="in" name="unit" value="<?= e($v('unit', 'buc')) ?>"></div>
        <div class="fl"><label>Etichetă (opțional)</label><input class="in" name="badge" value="<?= e($v('badge')) ?>" placeholder="ex: Nou, Best seller"></div>
      </div>
      <div class="fl"><label>Notă preț</label><input class="in" name="price_note" value="<?= e($v('price_note')) ?>" placeholder="ex: Preț per bucată · cutie de 150 bucăți"></div>
      <div class="row2">
        <div class="fl"><label>Cantitate minimă</label><input class="in" type="number" name="min_qty" min="1" value="<?= (int)$v('min_qty', 1) ?>"></div>
        <div class="fl"><label>Se vinde din … în …</label><input class="in" type="number" name="qty_step" min="1" value="<?= (int)$v('qty_step', 1) ?>"></div>
      </div>
      <div class="hint" style="margin-top:-8px">Ex: pentru monodoze vândute doar la cutie de 150: minim 150, pas 150.</div>
    </div>
    <div class="card f">
      <h2>Stoc</h2>
      <div class="fl"><select class="in" name="stock_status"><option value="instock"<?= $v('stock_status', 'instock') === 'instock' ? ' selected' : '' ?>>În stoc</option><option value="outofstock"<?= $v('stock_status') === 'outofstock' ? ' selected' : '' ?>>Stoc epuizat</option><option value="onbackorder"<?= $v('stock_status') === 'onbackorder' ? ' selected' : '' ?>>Disponibil la comandă</option></select></div>
      <label class="chk"><input type="checkbox" name="manage_stock" value="1"<?= $v('manage_stock') ? ' checked' : '' ?>> Urmăresc cantitatea în stoc</label>
      <div class="fl"><label>Cantitate în stoc</label><input class="in" type="number" name="stock" value="<?= (int)$v('stock') ?>"><div class="hint">Scade automat la fiecare comandă și revine la anulare.</div></div>
    </div>
    <div class="card f">
      <h2>Organizare</h2>
      <div class="fl"><label>Categorie</label><select class="in" name="category_id"><option value="">— fără categorie —</option><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)$v('category_id') === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <div class="row2">
        <div class="fl"><label>Brand</label><input class="in" name="brand" value="<?= e($v('brand')) ?>"></div>
        <div class="fl"><label>Cod produs (SKU)</label><input class="in" name="sku" value="<?= e($v('sku')) ?>"></div>
      </div>
      <div class="row2">
        <div class="fl"><label>Cod de bare (EAN/GTIN)</label><input class="in mono" name="gtin" value="<?= e($v('gtin')) ?>"><div class="hint">Ajută în Google Shopping.</div></div>
        <div class="fl"><label>Greutate (grame)</label><input class="in" type="number" name="weight_g" value="<?= (int)$v('weight_g') ?>"></div>
      </div>
      <div class="fl"><label>Ordine în listă</label><input class="in" type="number" name="sort" value="<?= (int)$v('sort') ?>"><div class="hint">Numerele mici apar primele.</div></div>
    </div>
  </div>
</form>
