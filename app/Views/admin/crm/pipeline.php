<?php use App\Core\Crm; $fmt = fn($v) => number_format((float)$v, 0, ',', '.'); ?>
<div class="page-head">
  <div><h1>Oportunități</h1><p>Trage cardurile între coloane ca să schimbi etapa. „Câștigat” transformă automat contactul în client.</p></div>
  <div class="actions">
    <form class="search" method="get" style="margin:0"><input class="in" name="q" value="<?= e($q) ?>" placeholder="Caută…"><label class="chk small"><input type="checkbox" name="inchise" value="1"<?= $showClosed ? ' checked' : '' ?> onchange="this.form.submit()"> toate închise</label></form>
    <a class="btn btn-p" href="<?= e(url('/admin/crm/oportunitati/nou')) ?>"><?= icon('plus') ?> Oportunitate</a>
  </div>
</div>
<div class="kanban">
<?php foreach ($stages as $key => $st): $items = $by[$key] ?? []; ?>
  <div class="col" data-stage="<?= e($key) ?>">
    <div class="col-h"><span><span class="dot" style="background:<?= e($st['color']) ?>"></span> <?= e($st['label']) ?> · <span class="col-count"><?= count($items) ?></span></span><small><?= $fmt(array_sum(array_column($items, 'value'))) ?> lei</small></div>
    <div class="col-body">
      <?php foreach ($items as $d): ?>
      <div class="deal" draggable="true" data-id="<?= (int)$d['id'] ?>">
        <b><a href="<?= e(url('/admin/crm/oportunitati/' . $d['id'])) ?>" style="color:inherit"><?= e($d['title']) ?></a></b>
        <div class="small muted"><?= e($d['cname']) ?><?= $d['company'] ? ' · ' . e($d['company']) : '' ?></div>
        <div class="m"><span><?= $d['value'] > 0 ? $fmt($d['value']) . ' lei' : '' ?></span><span><?= e(Crm::sourceLabel($d['source'])) ?> · <?= e(local_time($d['created_at'], 'd.m')) ?></span></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
