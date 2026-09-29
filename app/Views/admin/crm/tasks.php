<?php use App\Core\Csrf; ?>
<div class="page-head"><div><h1>Sarcini</h1><p>Follow-up-uri și lucruri de făcut. Adaugă sarcini din fișa unui contact sau a unei oportunități.</p></div></div>
<div class="table-wrap"><table class="t"><thead><tr><th>Sarcină</th><th>Contact</th><th>Termen</th><th class="r"></th></tr></thead><tbody>
<?php foreach ($open as $t): $late = $t['due_at'] && strtotime($t['due_at'] . ' UTC') < time(); ?>
<tr><td style="white-space:pre-wrap"><?= e($t['body']) ?><?= $t['dtitle'] ? '<div class="small muted">' . e($t['dtitle']) . '</div>' : '' ?></td><td><?php if ($t['contact_id']): ?><a href="<?= e(url('/admin/crm/contacte/' . $t['contact_id'])) ?>"><?= e($t['cname']) ?></a><?php endif; ?></td>
<td><?= $t['due_at'] ? '<span class="badge ' . ($late ? 'b-err' : 'b-info') . '">' . e(local_time($t['due_at'])) . '</span>' : '<span class="muted small">—</span>' ?></td>
<td class="r"><form method="post" action="<?= e(url('/admin/crm/activitate/' . $t['id'] . '/gata')) ?>"><?= Csrf::field() ?><button class="btn btn-xs"><?= icon('check') ?> Gata</button></form></td></tr>
<?php endforeach; ?>
<?php if (!$open): ?><tr><td colspan="4" class="empty">Nicio sarcină deschisă. 🎉</td></tr><?php endif; ?>
</tbody></table></div>
<?php if ($done): ?><div class="card" style="margin-top:16px"><h2>Finalizate recent</h2><?php foreach ($done as $t): ?><div class="small muted" style="padding:5px 0;border-bottom:1px solid var(--border)">✓ <?= e(excerpt((string)$t['body'], 100)) ?><?= $t['cname'] ? ' · ' . e($t['cname']) : '' ?></div><?php endforeach; ?></div><?php endif; ?>
