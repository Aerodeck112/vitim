<div class="page-head"><div><h1>Formular #<?= (int)$s['id'] ?></h1><p><a href="<?= e(url('/admin/formulare')) ?>">← Formulare</a> · <?= e(local_time($s['created_at'])) ?></p></div>
<div class="actions"><?php if ($s['contact_id']): ?><a class="btn" href="<?= e(url('/admin/crm/contacte/' . $s['contact_id'])) ?>"><?= icon('users') ?> Contact</a><?php endif; ?><?php if ($s['deal_id']): ?><a class="btn btn-p" href="<?= e(url('/admin/crm/oportunitati/' . $s['deal_id'])) ?>"><?= icon('kanban') ?> Oportunitate</a><?php endif; ?></div></div>
<div class="card"><dl class="dl">
<?php foreach ($data as $k => $v): if ($k === 'utm') continue; ?><dt><?= e($k) ?></dt><dd style="white-space:pre-wrap"><?= e(is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v) ?></dd><?php endforeach; ?>
<?php foreach ((array)($data['utm'] ?? []) as $k => $v): ?><dt><?= e($k) ?></dt><dd class="small"><?= e($v) ?></dd><?php endforeach; ?>
<dt>IP</dt><dd class="mono small"><?= e($s['ip']) ?></dd><dt>Browser</dt><dd class="small muted"><?= e($s['user_agent']) ?></dd><dt>Scor spam</dt><dd><?= (int)$s['spam_score'] ?></dd>
</dl></div>
