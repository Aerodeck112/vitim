<?php use App\Core\Csrf; ?>
<div class="page-head"><div><h1>Conversație #<?= (int)$c['id'] ?></h1><p><a href="<?= e(url('/admin/asistent')) ?>">← Asistent AI</a> · începută pe <span class="mono"><?= e($c['page']) ?></span> · <?= e(local_time($c['created_at'])) ?></p></div>
<div class="actions"><?php if ($c['deal_id']): ?><a class="btn btn-p" href="<?= e(url('/admin/crm/oportunitati/' . $c['deal_id'])) ?>"><?= icon('kanban') ?> Oportunitatea din CRM</a><?php endif; ?>
<form method="post" action="<?= e(url('/admin/asistent/' . $c['id'] . '/sterge')) ?>" data-confirm="Ștergi conversația?"><?= Csrf::field() ?><button class="btn btn-d"><?= icon('trash') ?> Șterge</button></form></div></div>
<div class="card" style="max-width:820px">
<?php foreach ($msgs as $m): ?>
  <div style="display:flex;<?= $m['role'] === 'user' ? 'justify-content:flex-end' : '' ?>;margin:8px 0">
    <div style="max-width:78%;padding:10px 14px;border-radius:14px;white-space:pre-wrap;<?= $m['role'] === 'user' ? 'background:var(--brand);color:#fff' : 'background:var(--panel-2);border:1px solid var(--border)' ?>"><?= e($m['text']) ?></div>
  </div>
<?php endforeach; if (!$msgs): ?><p class="muted">Conversație goală.</p><?php endif; ?>
</div>
