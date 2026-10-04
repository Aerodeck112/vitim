<?php
/** Studiu de caz: problema → soluția VITIM → ce am implementat. $p = rând din `projects`, $i = poziția. */
$features = array_values(array_filter(array_map('trim', explode("\n", (string)($p['features'] ?? '')))));
$max = $max ?? 6;
?>
<article class="case card" data-reveal data-delay="<?= (($i ?? 0) % 2) * 80 ?>">
  <header class="case-head">
    <?php if (!empty($p['logo'])): ?><img class="case-logo" src="<?= e(upload_url($p['logo'])) ?>" alt="<?= e($p['client']) ?>" loading="lazy" width="120" height="36"><?php else: ?><span class="case-client"><?= e($p['client'] ?: $p['title']) ?></span><?php endif; ?>
    <?php if (!empty($p['tag'])): ?><span class="tag"><?= e($p['tag']) ?></span><?php endif; ?>
  </header>
  <h3><?= e($p['summary']) ?></h3>
  <?php if (!empty($p['problem'])): ?>
  <ol class="case-steps">
    <li><small>Problema</small><p><?= e($p['problem']) ?></p></li>
    <li><small>Soluția VITIM</small><p><?= e((string)$p['solution']) ?></p></li>
    <?php if ($features): ?>
    <li><small>Ce am implementat</small>
      <ul class="chips-static"><?php foreach (array_slice($features, 0, $max) as $f): ?><li><?= e($f) ?></li><?php endforeach; ?><?php if (count($features) > $max): ?><li class="more-chip">+<?= count($features) - $max ?></li><?php endif; ?></ul>
    </li>
    <?php endif; ?>
  </ol>
  <?php endif; ?>
  <span class="more">Vezi proiectul <?= icon('arrow-up-right') ?></span>
  <a class="card-link" href="<?= e(url('/proiecte/' . $p['slug'])) ?>" aria-label="<?= e($p['title']) ?>"></a>
</article>
