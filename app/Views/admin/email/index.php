<?php use App\Core\Csrf; use App\Core\Newsletter; $pct = fn($a, $b) => $b > 0 ? round($a * 100 / $b) . '%' : '—'; ?>
<div class="page-head"><div><h1>Campanii email</h1><p><?= $subs ?> abonați confirmați · <a href="<?= e(url('/admin/abonati')) ?>">vezi abonații</a> · <a href="<?= e(url('/admin/email/jurnal')) ?>">jurnal emailuri</a></p></div>
<div class="actions"><a class="btn btn-p" href="<?= e(url('/admin/email/nou')) ?>"><?= icon('plus') ?> Campanie nouă</a></div></div>
<div class="table-wrap">
<?php if ($rows): ?>
<table class="t"><thead><tr><th>Campanie</th><th>Tip</th><th>Status</th><th>Trimise</th><th>Deschideri</th><th>Click-uri</th><th>Dezabonări</th><th class="r"></th></tr></thead><tbody>
<?php foreach ($rows as $r): $link = in_array($r['status'], ['draft', 'scheduled'], true) ? '/admin/email/' . $r['id'] : '/admin/email/' . $r['id'] . '/raport'; ?>
<tr>
  <td><a class="row-title" href="<?= e(url($link)) ?>"><?= e($r['name']) ?></a><div class="small muted"><?= e($r['subject']) ?></div></td>
  <td><span class="badge"><?= $r['kind'] === 'newsletter' ? 'Newsletter' : 'Notificare' ?></span></td>
  <td><span class="badge <?= ['draft' => '', 'scheduled' => 'b-info', 'sending' => 'b-warn', 'paused' => 'b-err', 'sent' => 'b-ok'][$r['status']] ?? '' ?>"><?= e(Newsletter::STATUSES[$r['status']] ?? $r['status']) ?></span><?php if ($r['status'] === 'scheduled'): ?><div class="small muted"><?= e(local_time($r['scheduled_at'])) ?></div><?php endif; ?></td>
  <td><?= (int)$r['sent'] ?> / <?= (int)$r['total'] ?></td>
  <td><?= $pct((int)$r['opens'], (int)$r['sent']) ?></td>
  <td><?= $pct((int)$r['clicks'], (int)$r['sent']) ?></td>
  <td><?= (int)$r['unsubs'] ?></td>
  <td class="r" style="white-space:nowrap">
    <form method="post" action="<?= e(url('/admin/email/' . $r['id'] . '/duplica')) ?>" style="display:inline"><?= Csrf::field() ?><button class="btn btn-xs" title="Duplică">Duplică</button></form>
    <?php if ($r['status'] !== 'sending'): ?><form method="post" action="<?= e(url('/admin/email/' . $r['id'] . '/sterge')) ?>" style="display:inline" data-confirm="Ștergi campania și statisticile ei?"><?= Csrf::field() ?><button class="btn btn-xs btn-d"><?= icon('trash') ?></button></form><?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php else: ?><div class="empty">Nicio campanie încă. Trimite primul newsletter sau o notificare către clienți.</div><?php endif; ?>
</div>
<div class="card" style="margin-top:16px"><h3>Bune practici</h3><ul class="small muted" style="margin:0"><li><strong>Newsletter</strong> = doar abonații care și-au dat acordul (GDPR). <strong>Notificare de serviciu</strong> = informații legate de serviciile contractate (mentenanță programată, alertă de securitate), către clienți.</li><li>Trimite întâi un test la tine. Verifică și pe telefon.</li><li>Respectă limita orară a hostingului (Setări → Email). Trimiterea continuă automat prin cron.</li></ul></div>
