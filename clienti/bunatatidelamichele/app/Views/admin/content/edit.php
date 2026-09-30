<?php
use App\Core\Csrf;
use App\Core\View;

$base = '/admin/c/' . $r['key'];
$main = array_filter($r['fields'], fn($f) => ($f['col'] ?? 'main') === 'main');
$side = array_filter($r['fields'], fn($f) => ($f['col'] ?? 'main') === 'side');
$val = fn($f) => $row[$f['name']] ?? ($f['default'] ?? ($f['type'] === 'repeater' ? '[]' : ''));
$publicUrl = ($r['url'] && !empty($row['slug']) && !empty($row['id'])) ? str_replace('{slug}', $row['slug'], $r['url']) : null;
$titleField = $r['fields'][0]['name'];
$descField = null;
foreach ($r['fields'] as $f) { if (in_array($f['name'], ['excerpt', 'summary', 'intro', 'subtitle'], true)) { $descField = $f['name']; break; } }
?>
<div class="page-head">
  <div><h1><?= e($title) ?></h1><p><a href="<?= e(url($base)) ?>">← <?= e($r['label']) ?></a><?php if ($publicUrl): ?> · <a href="<?= e(url($publicUrl)) ?>" target="_blank">Vezi pe site <?= icon('external') ?></a><?php endif; ?></p></div>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endforeach; ?>
<form method="post" class="split">
  <?= Csrf::field() ?>
  <div>
    <div class="card f">
      <?php foreach ($main as $f): ?><?= View::partial('admin/partials/field', ['f' => $f, 'val' => $val($f)]) ?><?php endforeach; ?>
    </div>
    <?php if (!empty($r['seo'])): ?>
    <div class="card f">
      <div class="card-h"><h2><?= icon('search') ?> SEO</h2><span class="small muted">Lasă gol pentru valorile automate</span></div>
      <div class="serp" data-serp data-title="#seo_t" data-desc="#seo_d" data-fb-title="#fld_<?= e($titleField) ?>" data-fb-desc="<?= $descField ? '#fld_' . e($descField) : '#none' ?>">
        <div class="u"><?= e(parse_url(abs_url('/'), PHP_URL_HOST)) ?> › <?= e(trim((string)($publicUrl ?? ''), '/')) ?></div>
        <div class="t"></div><div class="d"></div>
      </div>
      <div class="fl"><label for="seo_t">Titlu SEO (meta title)</label><input class="in" id="seo_t" name="meta_title" value="<?= e($row['meta_title'] ?? '') ?>" data-count="60"><div class="hint">Ideal 50–60 caractere, cu cuvântul cheie la început.</div></div>
      <div class="fl"><label for="seo_d">Descriere SEO (meta description)</label><textarea class="in" id="seo_d" name="meta_description" rows="2" data-count="160"><?= e($row['meta_description'] ?? '') ?></textarea><div class="hint">Ideal 140–160 caractere. Un îndemn clar crește click-urile.</div></div>
      <div class="row2">
        <div class="fl"><label>URL canonic (avansat)</label><input class="in" name="canonical" value="<?= e($row['canonical'] ?? '') ?>" placeholder="automat"></div>
        <?= View::partial('admin/partials/field', ['f' => ['name' => 'og_image', 'label' => 'Imagine pentru distribuire (1200×630)', 'type' => 'image'], 'val' => $row['og_image'] ?? '']) ?>
      </div>
      <label class="chk"><input type="checkbox" name="noindex" value="1"<?= !empty($row['noindex']) ? ' checked' : '' ?>> Ascunde din Google (noindex)</label>
    </div>
    <?php endif; ?>
  </div>
  <div style="position:sticky;top:76px">
    <div class="card f">
      <?php foreach ($side as $f): ?><?= View::partial('admin/partials/field', ['f' => $f, 'val' => $val($f)]) ?><?php endforeach; ?>
      <div class="actions" style="margin-top:6px"><button class="btn btn-p" type="submit" style="flex:1"><?= icon('check') ?> Salvează</button></div>
      <?php if (!empty($row['updated_at'])): ?><p class="small muted" style="margin:0">Ultima modificare: <?= e(local_time($row['updated_at'])) ?></p><?php endif; ?>
    </div>
  </div>
</form>
