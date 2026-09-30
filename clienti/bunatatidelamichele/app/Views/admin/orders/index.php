<?php
use App\Core\Orders;
use App\Core\Shop;

$badge = fn(array $map, string $k) => '<span class="st" style="color:' . e($map[$k][1] ?? '#888') . ';background:' . e($map[$k][1] ?? '#888') . '1f">' . e($map[$k][0] ?? $k) . '</span>';
$qs = fn(array $o) => '?' . http_build_query(array_merge(array_intersect_key($_GET, array_flip(['q', 'plata', 'metoda', 'de_la', 'pana_la'])), $o));
$cur = str_input('stare');
?>
<div class="page-head"><div><h1>Comenzi</h1><p><?= $pg['total'] ?> comenzi · total <?= e(Shop::money($sum)) ?></p></div>
<div class="actions"><a class="btn" href="<?= e(url('/admin/comenzi/export' . $qs(['stare' => $cur]))) ?>"><?= icon('download') ?> Export CSV (Excel)</a></div></div>
<nav class="tabs">
  <a href="<?= e(url('/admin/comenzi' . $qs([]))) ?>" class="<?= $cur === '' ? 'on' : '' ?>">Toate</a>
  <a href="<?= e(url('/admin/comenzi' . $qs(['stare' => 'de-procesat']))) ?>" class="<?= $cur === 'de-procesat' ? 'on' : '' ?>">De procesat <span class="badge b-info"><?= ($counts['noua'] ?? 0) + ($counts['procesare'] ?? 0) ?></span></a>
  <?php foreach (Orders::STATUSES as $k => $s): ?><a href="<?= e(url('/admin/comenzi' . $qs(['stare' => $k]))) ?>" class="<?= $cur === $k ? 'on' : '' ?>"><?= e($s[0]) ?> <span class="small muted"><?= $counts[$k] ?? 0 ?></span></a><?php endforeach; ?>
</nav>
<form class="search" method="get">
  <input type="hidden" name="stare" value="<?= e($cur) ?>">
  <input class="in" name="q" value="<?= e(str_input('q')) ?>" placeholder="Număr, nume, email, telefon, AWB…">
  <select class="in" name="plata" onchange="this.form.submit()"><option value="">Orice stare plată</option><?php foreach (Orders::PAYMENT_STATUSES as $k => $s): ?><option value="<?= $k ?>"<?= str_input('plata') === $k ? ' selected' : '' ?>><?= e($s[0]) ?></option><?php endforeach; ?></select>
  <select class="in" name="metoda" onchange="this.form.submit()"><option value="">Orice metodă</option><?php foreach (Orders::PAYMENT_METHODS as $k => $l): ?><option value="<?= $k ?>"<?= str_input('metoda') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
  <input class="in" type="date" name="de_la" value="<?= e(str_input('de_la')) ?>" style="max-width:160px" title="De la">
  <input class="in" type="date" name="pana_la" value="<?= e(str_input('pana_la')) ?>" style="max-width:160px" title="Până la">
  <button class="btn" type="submit"><?= icon('search') ?> Caută</button>
</form>
<div class="table-wrap">
<?php if ($rows): ?>
<table class="t"><thead><tr><th>Comandă</th><th>Client</th><th>Stare</th><th>Plată</th><th>Livrare</th><th class="r">Total</th></tr></thead><tbody>
<?php foreach ($rows as $o): ?>
<tr>
  <td><a class="row-title" href="<?= e(url('/admin/comenzi/' . $o['id'])) ?>"><?= e($o['number']) ?></a><div class="small muted"><?= e(local_time($o['created_at'])) ?></div></td>
  <td><?= e(Orders::customerName($o)) ?><?= $o['company'] ? '<div class="small muted">' . e($o['company']) . '</div>' : '' ?><div class="small muted"><?= e($o['billing_city']) ?>, <?= e($o['billing_county']) ?></div></td>
  <td><?= $badge(Orders::STATUSES, $o['status']) ?></td>
  <td><?= $badge(Orders::PAYMENT_STATUSES, $o['payment_status']) ?><div class="small muted"><?= e(Orders::PAYMENT_METHODS[$o['payment_method']] ?? '') ?></div></td>
  <td class="small"><?= $o['awb'] ? e($o['courier']) . '<div class="muted mono">' . e($o['awb']) . '</div>' : '<span class="muted">—</span>' ?></td>
  <td class="r money"><strong><?= e(Shop::money($o['total'])) ?></strong><div class="small muted"><?= (int)$o['items'] ?> buc</div></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php else: ?><div class="empty">Nicio comandă pentru filtrele alese.</div><?php endif; ?>
</div>
<?php if ($pg['pages'] > 1): ?><div class="pagination"><?php for ($i = 1; $i <= $pg['pages']; $i++): ?><?php if ($i === $pg['page']): ?><span class="cur"><?= $i ?></span><?php else: ?><a href="<?= e($qs(['p' => $i, 'stare' => $cur])) ?>"><?= $i ?></a><?php endif; ?><?php endfor; ?></div><?php endif; ?>
<p class="small muted" style="margin-top:12px">Comenzile cu card neplătite mai vechi de 2 zile sunt ascunse din „Toate” – le găsești la „Așteaptă plata”. Dacă plata nu vine în <?= (int)setting('hold_unpaid_minutes', '60') ?> de minute, cron-ul le anulează și reface stocul.</p>
