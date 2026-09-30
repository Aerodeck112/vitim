<?php /** @var array $faq */ if ($faq): ?>
<div class="faq">
  <?php foreach ($faq as $i => $f): if (empty($f['q'])) continue; ?>
  <details<?= $i === 0 && !empty($openFirst) ? ' open' : '' ?>><summary><?= e($f['q']) ?> <?= icon('plus') ?></summary><div class="a"><?= nl2br(e(strip_tags((string)$f['a']))) ?></div></details>
  <?php endforeach; ?>
</div>
<?php endif; ?>
