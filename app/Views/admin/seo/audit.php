<?php $col = fn($s) => $s >= 85 ? '#10b981' : ($s >= 65 ? '#f59e0b' : '#ef4444'); ?>
<div class="page-head"><div><h1>Audit SEO</h1><p>Verificare automată a fiecărei pagini publicate. Rezolvă întâi ce e roșu.</p></div>
<div class="actions"><span class="score" style="background:<?= $col($avg) ?>"><?= $avg ?></span><span class="muted">scor mediu</span></div></div>
<div class="card">
  <h2>Verificări generale</h2>
  <?php foreach ($global as [$ok, $label, $fix]): ?>
  <div style="display:flex;gap:10px;align-items:flex-start;padding:8px 0;border-bottom:1px solid var(--border)"><?= $ok ? '<span class="badge b-ok">✓</span>' : '<span class="badge b-warn">!</span>' ?><div><strong><?= e($label) ?></strong><?php if (!$ok): ?><div class="small muted"><?= e($fix) ?></div><?php endif; ?></div></div>
  <?php endforeach; ?>
</div>
<?php if ($dups): ?><div class="alert alert-warn"><strong>Titluri SEO duplicate:</strong> <?php foreach ($dups as $t => $list): ?><br>„<?= e($t) ?>” – <?= e(implode(', ', $list)) ?><?php endforeach; ?></div><?php endif; ?>
<div class="table-wrap">
<table class="t"><thead><tr><th>Scor</th><th>Pagină</th><th>Cuvinte</th><th>Probleme</th><th class="r"></th></tr></thead><tbody>
<?php foreach ($items as $it): ?>
<tr>
  <td><span class="score" style="width:38px;height:38px;font-size:13px;background:<?= $col($it['score']) ?>"><?= $it['score'] ?></span></td>
  <td><strong><?= e($it['name']) ?></strong><div class="small muted"><?= e($it['type']) ?> · <a href="<?= e(url($it['url'])) ?>" target="_blank" class="mono"><?= e($it['url']) ?></a></div></td>
  <td><?= $it['words'] ?></td>
  <td><?php foreach ($it['issues'] as [$lvl, $msg]): ?><div class="small" style="margin:2px 0"><span class="badge <?= $lvl === 'err' ? 'b-err' : ($lvl === 'warn' ? 'b-warn' : 'b-info') ?>"><?= $lvl === 'err' ? 'important' : ($lvl === 'warn' ? 'atenție' : 'sfat') ?></span> <?= e($msg) ?></div><?php endforeach; ?><?php if (!$it['issues']): ?><span class="badge b-ok">Totul e în regulă</span><?php endif; ?></td>
  <td class="r"><a class="btn btn-xs" href="<?= e(url($it['edit'])) ?>"><?= icon('edit') ?> Editează</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
