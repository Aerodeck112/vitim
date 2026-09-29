<div class="faq">
  <?php foreach ($faq as $i => $f): if (empty($f['q'])) continue; ?>
  <details<?= $i === 0 ? ' open' : '' ?> data-reveal>
    <summary><?= e($f['q']) ?><span class="pm"><?= icon('plus') ?></span></summary>
    <div class="answer"><?= nl2br(e(strip_tags((string)$f['a']))) ?></div>
  </details>
  <?php endforeach; ?>
</div>
