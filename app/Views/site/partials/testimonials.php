<div class="testimonials">
  <?php foreach ($items as $i => $t): ?>
  <figure class="card quote-card" data-reveal data-delay="<?= ($i % 3) * 80 ?>" style="margin:0">
    <?php if ((int)$t['rating'] > 0): ?><div class="stars" aria-label="<?= (int)$t['rating'] ?> din 5 stele"><?php for ($s = 0; $s < (int)$t['rating']; $s++): ?><?= icon('star') ?><?php endfor; ?></div><?php endif; ?>
    <blockquote>„<?= e($t['text']) ?>”</blockquote>
    <figcaption class="author"><span class="avatar"><?= e(mb_strtoupper(mb_substr($t['name'], 0, 1))) ?></span><span><strong><?= e($t['name']) ?></strong><small><?= e(trim(($t['role'] ?? '') . ($t['company'] ? ', ' . $t['company'] : ''), ', ')) ?></small></span></figcaption>
  </figure>
  <?php endforeach; ?>
</div>
