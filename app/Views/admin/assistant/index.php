<?php use App\Core\Csrf; use App\Core\Auth; ?>
<div class="page-head"><div><h1>Asistent AI</h1><p>Conversațiile vizitatorilor cu asistentul de pe site. Cererile de ofertă ajung automat în CRM.</p></div>
<div class="actions">
  <?php if (Auth::can('settings')): ?><a class="btn" href="<?= e(url('/admin/setari/asistent')) ?>"><?= icon('settings') ?> Setări asistent</a><?php endif; ?>
  <form method="post" action="<?= e(url('/admin/asistent/test')) ?>"><?= Csrf::field() ?><button class="btn"><?= icon('zap') ?> Testează conexiunea</button></form>
</div></div>
<?php if (!$configured): ?>
<div class="alert alert-info"><strong>Asistentul nu este încă activ.</strong> Creează o cheie API în contul tău de la furnizorul AI (console.anthropic.com → API Keys), adaug-o în <a href="<?= e(url('/admin/setari/asistent')) ?>">Setări → Asistent AI</a> și bifează „Activat”. Costul uzual: câțiva lei pe lună pentru un site de firmă.</div>
<?php elseif (!$enabled): ?>
<div class="alert alert-warn">Cheia este configurată, dar asistentul este dezactivat. Activează-l din <a href="<?= e(url('/admin/setari/asistent')) ?>">Setări → Asistent AI</a>.</div>
<?php endif; ?>
<div class="grid g4">
  <div class="card kpi"><small>Conversații (30 zile)</small><b><?= (int)$stats['n'] ?></b></div>
  <div class="card kpi"><small>Mesaje</small><b><?= (int)$stats['turns'] ?></b></div>
  <div class="card kpi"><small>Cereri salvate în CRM</small><b><?= (int)$stats['leads'] ?></b></div>
  <div class="card kpi"><small>Tokeni folosiți</small><b style="font-size:20px"><?= number_format((int)$stats['tin'] + (int)$stats['tout'], 0, ',', '.') ?></b><span class="small muted"><?= number_format((int)$stats['tout'], 0, ',', '.') ?> generați</span></div>
</div>
<div class="table-wrap" style="margin-top:16px"><table class="t"><thead><tr><th>Conversație</th><th>Pagina</th><th>Mesaje</th><th>Rezultat</th><th>Ultima activitate</th></tr></thead><tbody>
<?php foreach ($rows as $r): $t = \App\Core\Assistant::transcript(json_list($r['messages'])); $first = $t[0]['text'] ?? ''; ?>
<tr><td><a class="row-title" href="<?= e(url('/admin/asistent/' . $r['id'])) ?>">#<?= (int)$r['id'] ?></a> <span class="small muted"><?= e(excerpt($first, 90)) ?></span></td><td class="mono small"><?= e($r['page']) ?></td><td><?= (int)$r['turns'] ?></td>
<td><?php if ($r['deal_id']): ?><a class="badge b-ok" href="<?= e(url('/admin/crm/oportunitati/' . $r['deal_id'])) ?>">Lead: <?= e($r['cname']) ?></a><?php else: ?><span class="badge">informare</span><?php endif; ?></td><td class="small muted"><?= e(local_time($r['updated_at'])) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="5" class="empty">Nicio conversație încă.</td></tr><?php endif; ?>
</tbody></table></div>
