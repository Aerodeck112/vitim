<div class="page-head"><div><h1>Formulare primite</h1><p>Fiecare trimitere creează automat contact + oportunitate în CRM.</p></div><div class="actions"><a class="btn btn-sm<?= !$spam ? ' btn-p' : '' ?>" href="<?= e(url('/admin/formulare')) ?>">Valide</a><a class="btn btn-sm<?= $spam ? ' btn-p' : '' ?>" href="<?= e(url('/admin/formulare?spam=1')) ?>">Spam filtrat</a></div></div>
<div class="table-wrap"><table class="t"><thead><tr><th></th><th>Contact</th><th>Mesaj</th><th>Pagina</th><th>Primit</th></tr></thead><tbody>
<?php foreach ($rows as $s): $d = json_list($s['data']); ?>
<tr style="<?= $s['read_at'] ? '' : 'font-weight:600' ?>"><td><?= $s['read_at'] ? '' : '<span class="dot" style="background:var(--brand)"></span>' ?></td>
<td><a href="<?= e(url('/admin/formulare/' . $s['id'])) ?>"><?= e($d['name'] ?? $s['cname'] ?? '—') ?></a><div class="small muted"><?= e($d['email'] ?? '') ?> <?= e($d['phone'] ?? '') ?></div></td>
<td class="small" style="max-width:420px"><?= e(excerpt((string)($d['message'] ?? ''), 140)) ?></td><td class="small mono"><?= e($s['page']) ?></td><td class="small muted"><?= e(local_time($s['created_at'])) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="5" class="empty">Nimic aici.</td></tr><?php endif; ?>
</tbody></table></div>
