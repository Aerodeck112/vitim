<?php
use App\Core\Csrf;
use App\Core\Shop;
?>
<div class="page-head"><div><h1>Produse</h1><p><?= count($rows) ?> produse. Prețul și stocul se pot modifica direct din listă.</p></div><div class="actions"><a class="btn btn-p" href="<?= e(url('/admin/produse/nou')) ?>"><?= icon('plus') ?> Produs nou</a></div></div>
<form class="search" method="get">
  <input class="in" name="q" value="<?= e($q) ?>" placeholder="Nume, cod, brand…">
  <select class="in" name="categorie" onchange="this.form.submit()"><option value="">Toate categoriile</option><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)str_input('categorie') === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
  <select class="in" name="filtru" onchange="this.form.submit()"><option value="">Toate</option><option value="stoc"<?= str_input('filtru') === 'stoc' ? ' selected' : '' ?>>Stoc scăzut / epuizat</option><option value="reducere"<?= str_input('filtru') === 'reducere' ? ' selected' : '' ?>>La reducere</option><option value="ascunse"<?= str_input('filtru') === 'ascunse' ? ' selected' : '' ?>>Ascunse</option></select>
  <button class="btn"><?= icon('search') ?> Caută</button>
</form>
<div class="table-wrap">
<?php if ($rows): ?>
<table class="t"><thead><tr><th></th><th>Produs</th><th>Categorie</th><th>Preț (lei)</th><th>Stoc</th><th>Vândute</th><th>Vizibil</th><th class="r"></th></tr></thead><tbody>
<?php foreach ($rows as $p): [$sc, $sl] = Shop::stockLabel($p); ?>
<tr data-quick="<?= (int)$p['id'] ?>">
  <td style="width:60px"><?php if ($img = Shop::image($p)): ?><img class="thumb" src="<?= e(upload_url($img)) ?>" alt=""><?php endif; ?></td>
  <td><a class="row-title" href="<?= e(url('/admin/produse/' . $p['id'])) ?>"><?= e($p['name']) ?></a><div class="small muted"><?= $p['sku'] ? 'Cod ' . e($p['sku']) . ' · ' : '' ?><?= e($p['brand']) ?><?= $p['featured'] ? ' · <span class="badge b-vio">recomandat</span>' : '' ?><?= Shop::onSale($p) ? ' · <span class="badge b-warn">reducere ' . e(Shop::money($p['sale_price'])) . '</span>' : '' ?></div></td>
  <td class="small"><?= e($p['category_name'] ?? '—') ?></td>
  <td><input class="in money" name="price" value="<?= e(number_format((float)$p['price'], 2, '.', '')) ?>" style="width:100px" data-q></td>
  <td><?php if ($p['manage_stock']): ?><input class="in" name="stock" type="number" value="<?= (int)$p['stock'] ?>" style="width:80px" data-q><?php else: ?><span class="badge <?= $sc === 'out' ? 'b-err' : 'b-ok' ?>"><?= e($sl) ?></span><?php endif; ?></td>
  <td><?= (int)$p['sales_count'] ?></td>
  <td><label class="chk"><input type="checkbox" name="published" value="1"<?= $p['published'] ? ' checked' : '' ?> data-q></label></td>
  <td class="r" style="white-space:nowrap">
    <a class="btn btn-xs" href="<?= e(url(Shop::url($p))) ?>" target="_blank" title="Vezi pe site"><?= icon('external') ?></a>
    <a class="btn btn-xs" href="<?= e(url('/admin/produse/' . $p['id'])) ?>"><?= icon('edit') ?> Editează</a>
    <form method="post" action="<?= e(url('/admin/produse/' . $p['id'] . '/duplica')) ?>" style="display:inline"><?= Csrf::field() ?><button class="btn btn-xs" title="Duplică">⧉</button></form>
    <form method="post" action="<?= e(url('/admin/produse/' . $p['id'] . '/sterge')) ?>" style="display:inline" data-confirm="Ștergi „<?= e($p['name']) ?>”? Adresa lui va fi redirecționată către categorie."><?= Csrf::field() ?><button class="btn btn-xs btn-d"><?= icon('trash') ?></button></form>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php else: ?><div class="empty">Niciun produs găsit. <a href="<?= e(url('/admin/produse/nou')) ?>">Adaugă primul produs</a>.</div><?php endif; ?>
</div>
<script>
document.addEventListener('change', function (e) {
  var i = e.target.closest('[data-q]'); if (!i) return;
  var tr = i.closest('[data-quick]'), fd = new FormData();
  fd.append('id', tr.getAttribute('data-quick'));
  if (i.type === 'checkbox') fd.append('published', i.checked ? '1' : ''); else fd.append(i.name, i.value);
  window.vpost(document.querySelector('meta[name=base]').content + '/admin/produse/rapid', fd).then(function (r) { i.style.outline = '2px solid ' + (r.ok ? '#10b981' : '#ef4444'); setTimeout(function () { i.style.outline = ''; }, 1200); });
});
</script>
