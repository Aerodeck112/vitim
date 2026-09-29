<div class="page-head"><div><h1>Jurnal emailuri</h1><p>Ultimele 300 de emailuri trimise de site (notificări, răspunsuri automate, campanii, teste).</p></div></div>
<div class="table-wrap"><table class="t"><thead><tr><th>Când</th><th>Către</th><th>Subiect</th><th>Tip</th><th>Status</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td class="small muted"><?= e(local_time($r['created_at'])) ?></td><td class="small"><?= e($r['to_email']) ?></td><td class="small"><?= e($r['subject']) ?></td><td><span class="badge"><?= e($r['kind']) ?></span></td><td><span class="badge <?= $r['status'] === 'sent' ? 'b-ok' : 'b-err' ?>"><?= e($r['status']) ?></span><?php if ($r['error']): ?><div class="small" style="color:var(--err);max-width:320px"><?= e(excerpt((string)$r['error'], 160)) ?></div><?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="empty">Niciun email trimis încă.</td></tr><?php endif; ?>
</tbody></table></div>
