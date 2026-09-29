<?php use App\Core\Crm; use App\Core\Csrf; ?>
<ul class="tl">
<?php foreach ($acts as $a): [$lbl, $ic] = Crm::ACTIVITY_TYPES[$a['type']] ?? ['Activitate', 'info']; ?>
  <li>
    <span class="ic"><?= icon($ic) ?></span>
    <div>
      <div class="when"><strong style="color:var(--text)"><?= e($lbl) ?></strong> · <?= e(local_time($a['created_at'])) ?><?= !empty($a['uname']) ? ' · ' . e($a['uname']) : '' ?>
        <?php if ($a['type'] === 'task'): ?> · <?= $a['done'] ? '<span class="badge b-ok">făcut</span>' : '<span class="badge b-warn">de făcut' . ($a['due_at'] ? ' până ' . e(local_time($a['due_at'])) : '') . '</span>' ?><?php endif; ?>
      </div>
      <div class="b"><?= e($a['body']) ?></div>
      <div class="actions" style="margin-top:4px">
        <?php if ($a['type'] === 'task' && !$a['done']): ?><form method="post" action="<?= e(url('/admin/crm/activitate/' . $a['id'] . '/gata')) ?>"><?= Csrf::field() ?><button class="btn btn-xs"><?= icon('check') ?> Gata</button></form><?php endif; ?>
        <form method="post" action="<?= e(url('/admin/crm/activitate/' . $a['id'] . '/sterge')) ?>" data-confirm="Ștergi activitatea?"><?= Csrf::field() ?><button class="btn btn-xs btn-ghost">Șterge</button></form>
      </div>
    </div>
  </li>
<?php endforeach; ?>
<?php if (!$acts): ?><li style="display:block" class="muted small">Nicio activitate încă.</li><?php endif; ?>
</ul>
