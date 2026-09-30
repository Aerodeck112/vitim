<?php use App\Core\Csrf; ?>
<div class="page-head">
  <div><h1>Media</h1><p><?= count($items) ?> imagini · <?= number_format($size / 1048576, 1, ',', '.') ?> MB. Imaginile sunt convertite automat în WebP și redimensionate pentru mobil.</p></div>
</div>
<div class="card" style="margin-bottom:16px">
  <div class="drop" data-drop><?= icon('upload') ?><br><strong>Trage imaginile aici</strong> sau apasă pentru a alege · JPG, PNG, WebP, GIF · max. 15 MB</div>
  <input type="file" accept="image/*" multiple hidden>
</div>
<?php if ($items): ?>
<div class="media-grid">
  <?php foreach ($items as $m): $v = json_list($m['variants']); ?>
  <div class="media-item" style="cursor:default">
    <a href="<?= e(upload_url($m['path'])) ?>" target="_blank"><img src="<?= e(upload_url($v[480] ?? $v['480'] ?? $m['path'])) ?>" alt="<?= e($m['alt']) ?>" loading="lazy"></a>
    <div class="meta" title="<?= e($m['original_name']) ?>"><?= e($m['original_name']) ?><br><?= (int)$m['width'] ?>×<?= (int)$m['height'] ?> · <?= round($m['size'] / 1024) ?> KB</div>
    <form method="post" action="<?= e(url('/admin/media/' . $m['id'])) ?>" style="padding:0 8px 8px;display:flex;gap:4px"><?= Csrf::field() ?><input class="in" name="alt" value="<?= e($m['alt']) ?>" placeholder="Text alternativ (SEO)" style="padding:5px 8px;font-size:12px"><button class="btn btn-xs" title="Salvează"><?= icon('check') ?></button></form>
    <form method="post" action="<?= e(url('/admin/media/' . $m['id'] . '/sterge')) ?>" data-confirm="Ștergi imaginea? Paginile care o folosesc vor rămâne fără ea." style="position:absolute;top:6px;right:6px"><?= Csrf::field() ?><button class="btn btn-xs btn-d"><?= icon('trash') ?></button></form>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?><div class="card empty">Încă nu ai încărcat nicio imagine.</div><?php endif; ?>
