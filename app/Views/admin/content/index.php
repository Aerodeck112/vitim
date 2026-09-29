<?php
$base = '/admin/c/' . $r['key'];
$fmt = function (string $col, $v, array $row) use ($r) {
    if (in_array($col, ['published', 'onsite', 'in_footer'], true)) {
        return $v ? '<span class="badge b-ok">' . ($col === 'published' ? 'Publicat' : 'Da') . '</span>' : '<span class="badge">' . ($col === 'published' ? 'Ascuns' : 'Nu') . '</span>';
    }
    if ($col === 'status') {
        $future = !empty($row['published_at']) && strtotime($row['published_at'] . ' UTC') > time();
        return $v === 'published' ? ($future ? '<span class="badge b-info">Programat</span>' : '<span class="badge b-ok">Publicat</span>') : '<span class="badge b-warn">Ciornă</span>';
    }
    if ($col === 'type') {
        return $v === 'judet' ? '<span class="badge b-vio">Județ</span>' : '<span class="badge">Localitate</span>';
    }
    if ($col === 'category') {
        $cats = \App\Core\Site::categories();
        return e($cats[$v]['short'] ?? $v);
    }
    if (str_ends_with($col, '_at')) {
        return '<span class="small muted">' . e(local_time($v, 'd.m.Y')) . '</span>';
    }
    if ($col === 'rating') {
        return $v ? str_repeat('★', (int)$v) : '—';
    }
    if ($col === 'slug') {
        return '<span class="mono small">/' . e($v) . '</span>';
    }
    return e($v);
};
$first = array_key_first($r['list']);
?>
<div class="page-head">
  <div><h1><?= e($r['label']) ?></h1><p><?= $pg['total'] ?> elemente<?= !empty($r['note']) ? ' · ' . e($r['note']) : '' ?></p></div>
  <div class="actions"><a class="btn btn-p" href="<?= e(url($base . '/nou')) ?>"><?= icon('plus') ?> Adaugă <?= e($r['singular']) ?></a></div>
</div>
<form class="search" method="get">
  <input class="in" name="q" value="<?= e($q) ?>" placeholder="Caută…">
  <?php foreach ($r['filters'] ?? [] as $col => $opts): ?>
  <select class="in" name="f_<?= e($col) ?>" onchange="this.form.submit()"><option value="">Toate</option><?php foreach ($opts as $k => $l): ?><option value="<?= e($k) ?>"<?= str_input('f_' . $col) === (string)$k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
  <?php endforeach; ?>
  <button class="btn" type="submit"><?= icon('search') ?> Caută</button>
</form>
<div class="table-wrap">
  <?php if ($rows): ?>
  <table class="t">
    <thead><tr><?php foreach ($r['list'] as $col => $label): ?><th><?= e($label) ?></th><?php endforeach; ?><th class="r">Acțiuni</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
      <tr>
        <?php foreach ($r['list'] as $col => $label): ?>
          <td><?php if ($col === $first): ?><a class="row-title" href="<?= e(url($base . '/' . $row['id'])) ?>"><?= e($row[$col]) ?></a><?php if (($row['type'] ?? '') === 'oras'): ?><span class="muted small"> · localitate</span><?php endif; ?><?php else: ?><?= $fmt($col, $row[$col] ?? '', $row) ?><?php endif; ?></td>
        <?php endforeach; ?>
        <td class="r" style="white-space:nowrap">
          <?php if ($r['url'] && !empty($row['slug'])): ?><a class="btn btn-xs" href="<?= e(url(str_replace('{slug}', $row['slug'], $r['url']))) ?>" target="_blank" title="Vezi pe site"><?= icon('external') ?></a><?php endif; ?>
          <a class="btn btn-xs" href="<?= e(url($base . '/' . $row['id'])) ?>"><?= icon('edit') ?> Editează</a>
          <form method="post" action="<?= e(url($base . '/' . $row['id'] . '/sterge')) ?>" style="display:inline" data-confirm="Sigur ștergi „<?= e($row[$first]) ?>”?"><?= \App\Core\Csrf::field() ?><button class="btn btn-xs btn-d" type="submit"><?= icon('trash') ?></button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?><div class="empty">Nimic aici încă. <a href="<?= e(url($base . '/nou')) ?>">Adaugă primul <?= e($r['singular']) ?></a>.</div><?php endif; ?>
</div>
<?php if ($pg['pages'] > 1): ?><div class="pagination"><?php for ($i = 1; $i <= $pg['pages']; $i++): ?><?php if ($i === $pg['page']): ?><span class="cur"><?= $i ?></span><?php else: ?><a href="?<?= e(http_build_query(array_merge($_GET, ['p' => $i]))) ?>"><?= $i ?></a><?php endif; ?><?php endfor; ?></div><?php endif; ?>
