<?php
use App\Core\Site;

$counties = Site::counties();
?>
<div class="zones">
  <?php foreach ($counties as $i => $c): ?>
  <div class="card zone-card" data-reveal data-delay="<?= $i * 80 ?>">
    <div class="icon-tile"><?= icon('map') ?></div>
    <h3>Județul <?= e($c['name']) ?></h3>
    <p>Intervenții la sediu, mentenanță periodică și suport remote pentru firmele din județul <?= e($c['name']) ?>.</p>
    <?php if ($c['cities']): ?>
    <ul>
      <?php foreach ($c['cities'] as $city): ?><li><a href="<?= e(url('/zone/' . $city['slug'])) ?>"><?= e($city['name']) ?></a></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <span class="more">Vezi zona <?= icon('arrow-up-right') ?></span>
    <a class="card-link" href="<?= e(url('/zone/' . $c['slug'])) ?>" aria-label="Servicii IT în județul <?= e($c['name']) ?>" style="z-index:1"></a>
  </div>
  <?php endforeach; ?>
</div>
<div class="remote-banner" data-reveal><?= icon('globe') ?><span><strong style="color:var(--text)">În afara acestor județe?</strong> Suportul remote, marketingul, SEO, automatizările și proiectele AI le livrăm oriunde în România și în UE.</span></div>
