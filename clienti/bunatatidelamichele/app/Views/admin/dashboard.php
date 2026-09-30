<?php
use App\Core\Orders;
use App\Core\Shop;

$max = max(1, max($days));
$delta = $kpi['prev30'] > 0 ? round(($kpi['d30'] - $kpi['prev30']) / $kpi['prev30'] * 100) : null;
$done = count(array_filter($setup, fn($s) => $s[1]));
?>
<div class="page-head"><div><h1>Bună, <?= e(explode(' ', (string)(\App\Core\Auth::user()['name'] ?? ''))[0]) ?>!</h1><p>Iată cum merge magazinul.</p></div>
<div class="actions"><a class="btn" href="<?= e(url('/admin/comenzi')) ?>"><?= icon('receipt') ?> Comenzi</a><a class="btn btn-p" href="<?= e(url('/admin/produse/nou')) ?>"><?= icon('plus') ?> Produs nou</a></div></div>

<?php if ($kpi['todo'] || $kpi['toCapture']): ?>
<div class="alert alert-info"><?php if ($kpi['todo']): ?><strong><?= $kpi['todo'] ?></strong> <?= $kpi['todo'] === 1 ? 'comandă așteaptă' : 'comenzi așteaptă' ?> să fie pregătite. <a href="<?= e(url('/admin/comenzi?stare=de-procesat')) ?>">Vezi comenzile →</a><?php endif; ?>
<?php if ($kpi['toCapture']): ?> <strong><?= $kpi['toCapture'] ?></strong> plăți autorizate trebuie încasate. <a href="<?= e(url('/admin/comenzi?plata=autorizata')) ?>">Vezi →</a><?php endif; ?></div>
<?php endif; ?>

<div class="grid g4" style="margin-bottom:16px">
  <div class="card kpi"><small><?= icon('zap') ?> Azi</small><b class="money"><?= e(Shop::money($kpi['today'])) ?></b><span class="small muted"><?= $kpi['todayN'] ?> comenzi</span></div>
  <div class="card kpi"><small><?= icon('chart') ?> Ultimele 7 zile</small><b class="money"><?= e(Shop::money($kpi['d7'])) ?></b><span class="small muted"><?= $kpi['d7N'] ?> comenzi</span></div>
  <div class="card kpi"><small><?= icon('trending') ?> Ultimele 30 de zile</small><b class="money"><?= e(Shop::money($kpi['d30'])) ?></b><span class="small muted"><?= $kpi['d30N'] ?> comenzi<?= $delta !== null ? ' · <span style="color:' . ($delta >= 0 ? 'var(--ok)' : 'var(--err)') . '">' . ($delta >= 0 ? '+' : '') . $delta . '%</span>' : '' ?></span></div>
  <div class="card kpi"><small><?= icon('bag') ?> Valoare medie comandă</small><b class="money"><?= e(Shop::money($kpi['aov'])) ?></b><span class="small muted"><?= $kpi['customers'] ?> clienți în total</span></div>
</div>

<div class="split">
  <div>
    <div class="card">
      <div class="card-h"><h2>Vânzări – ultimele 30 de zile</h2><span class="small muted">fără comenzile anulate sau neplătite</span></div>
      <div class="chart"><?php foreach ($days as $d => $v): ?><div style="height:<?= max(2, (int)round($v / $max * 100)) ?>%" data-t="<?= e(date('d.m', strtotime($d)) . ': ' . Shop::money($v)) ?>"></div><?php endforeach; ?></div>
      <div class="chart-x"><span><?= e(date('d.m', strtotime(array_key_first($days)))) ?></span><span>azi</span></div>
    </div>
    <div class="card">
      <div class="card-h"><h2>Comenzi recente</h2><a class="btn btn-sm" href="<?= e(url('/admin/comenzi')) ?>">Toate</a></div>
      <?php if ($recent): ?>
      <table class="t"><tbody>
      <?php foreach ($recent as $o): $st = Orders::STATUSES[$o['status']] ?? ['?', '#999']; ?>
        <tr><td><a class="row-title" href="<?= e(url('/admin/comenzi/' . $o['id'])) ?>"><?= e($o['number']) ?></a><div class="small muted"><?= e(Orders::customerName($o)) ?></div></td>
        <td><span class="st" style="color:<?= e($st[1]) ?>;background:<?= e($st[1]) ?>1f"><?= e($st[0]) ?></span></td>
        <td class="small muted"><?= e(Orders::PAYMENT_METHODS[$o['payment_method']] ?? '') ?></td>
        <td class="r money"><strong><?= e(Shop::money($o['total'])) ?></strong><div class="small muted"><?= e(local_time($o['created_at'], 'd.m H:i')) ?></div></td></tr>
      <?php endforeach; ?>
      </tbody></table>
      <?php else: ?><div class="empty">Încă nu ai comenzi pe noul magazin. Prima e pe drum! ☕</div><?php endif; ?>
    </div>
  </div>
  <div>
    <div class="card">
      <div class="card-h"><h2>Configurare magazin</h2><span class="badge <?= $done === count($setup) ? 'b-ok' : 'b-warn' ?>"><?= $done ?>/<?= count($setup) ?></span></div>
      <?php foreach ($setup as [$label, $ok, $link]): ?>
      <a href="<?= e(url($link)) ?>" style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);color:var(--text);text-decoration:none;font-size:13.5px"><?= $ok ? '<span class="badge b-ok">✓</span>' : '<span class="badge b-warn">!</span>' ?><span><?= e($label) ?></span></a>
      <?php endforeach; ?>
    </div>
    <div class="card">
      <h2>Cele mai vândute (30 zile)</h2>
      <?php if ($top): foreach ($top as $t): ?><div style="display:flex;justify-content:space-between;gap:10px;padding:6px 0;font-size:13.5px"><span><?= e($t['name']) ?> <span class="muted">× <?= (int)$t['q'] ?></span></span><strong class="money"><?= e(Shop::money($t['t'])) ?></strong></div><?php endforeach; else: ?><p class="muted small">Încă nu sunt vânzări.</p><?php endif; ?>
      <?php if ($payMix): ?><p class="small muted" style="margin:10px 0 0">Plăți: <?= e(implode(' · ', array_map(fn($p) => (Orders::PAYMENT_METHODS[$p['m']] ?? $p['m']) . ' ' . $p['n'], $payMix))) ?></p><?php endif; ?>
    </div>
    <?php if ($low): ?>
    <div class="card"><h2>Stoc scăzut</h2><?php foreach ($low as $l): ?><div style="display:flex;justify-content:space-between;padding:5px 0;font-size:13.5px"><a href="<?= e(url('/admin/produse/' . $l['id'])) ?>"><?= e($l['name']) ?></a><span class="badge <?= (int)$l['stock'] <= 0 ? 'b-err' : 'b-warn' ?>"><?= (int)$l['stock'] ?> buc</span></div><?php endforeach; ?></div>
    <?php endif; ?>
  </div>
</div>
