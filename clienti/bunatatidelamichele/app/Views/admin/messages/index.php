<div class="page-head"><div><h1>Mesaje</h1><p>Mesajele trimise prin formularul de contact. Răspunde direct din email – notificarea are „Reply-To” setat pe client.</p></div>
<div class="actions"><?php if ($spam): ?><a class="btn" href="<?= e(url('/admin/mesaje')) ?>">← Mesaje</a><?php elseif ($spamCount): ?><a class="btn btn-ghost" href="<?= e(url('/admin/mesaje?spam=1')) ?>">Spam filtrat (<?= $spamCount ?>)</a><?php endif; ?></div></div>
<div class="table-wrap"><?php if ($rows): ?><table class="t"><thead><tr><th>De la</th><th>Subiect / mesaj</th><th>Primit</th></tr></thead><tbody>
<?php foreach ($rows as $m): ?>
<tr><td><a class="row-title" href="<?= e(url('/admin/mesaje/' . $m['id'])) ?>"><?= $m['read_at'] ? '' : '<span class="dot" style="background:var(--brand)"></span> ' ?><?= e($m['name']) ?></a><div class="small muted"><?= e($m['email']) ?></div></td>
<td><?= $m['subject'] ? '<strong>' . e($m['subject']) . '</strong><br>' : '' ?><span class="small muted"><?= e(excerpt((string)$m['message'], 120)) ?></span></td>
<td class="small muted"><?= e(local_time($m['created_at'])) ?></td></tr>
<?php endforeach; ?></tbody></table><?php else: ?><div class="empty"><?= $spam ? 'Niciun mesaj spam.' : 'Niciun mesaj încă.' ?></div><?php endif; ?></div>
