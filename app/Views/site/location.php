<?php
use App\Core\View;

echo View::partial('site/partials/page_hero', [
    'crumbs' => $crumbs,
    'eyebrow' => icon('map') . ' ' . ($isCounty ? 'Județul ' . e($l['name']) : e($l['name']) . ', jud. ' . e($countyName)),
    'title' => e($isCounty ? 'Servicii IT pentru firme în județul ' : 'Suport și service IT în ') . '<span class="grad">' . e($l['name']) . '</span>',
    'lead' => $l['intro'] ?: 'Mentenanță IT, intervenții la sediu, recuperări de date și securitate cibernetică pentru afacerile din ' . $display . '. Plus marketing online și soluții AI livrate remote.',
    'actions' => '<a class="btn btn-primary btn-lg" href="#oferta">Cere ofertă în ' . e($l['name']) . ' ' . icon('arrow-right', 'ico ico-move') . '</a><a class="btn btn-ghost btn-lg" href="' . e(phone_href((string)setting('phone'))) . '" data-loc="zone-hero">' . icon('phone') . ' ' . e(setting('phone')) . '</a>',
]);
?>
<section class="section-sm">
  <div class="container">
    <div class="section-head" data-reveal><span class="eyebrow">La sediul tău</span><h2>Servicii on-site în <?= e($display) ?></h2><p>Când e nevoie de cineva fizic lângă echipamente, venim la tine.</p></div>
    <div class="grid-3">
      <?php foreach ($onsite as $i => $s): ?>
      <article class="card" data-reveal data-delay="<?= ($i % 3) * 70 ?>"><div class="icon-tile"><?= icon($s['icon'] ?: 'wrench') ?></div><h3><?= e($s['title']) ?> <?= e($l['name']) ?></h3><p><?= e($s['excerpt']) ?></p><span class="more">Detalii <?= icon('arrow-up-right') ?></span><a class="card-link" href="<?= e(url('/servicii/' . $s['slug'])) ?>" aria-label="<?= e($s['title']) ?>"></a></article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if (trim(strip_tags((string)$body)) !== ''): ?>
<section class="section-sm"><div class="container" style="max-width:860px"><article class="prose" data-reveal><?= $body ?></article></div></section>
<?php endif; ?>

<?php if ($cities || $siblings): ?>
<section class="section-sm">
  <div class="container">
    <div class="card zone-card" data-reveal>
      <h3><?= icon('map') ?> <?= $cities ? 'Localități deservite în județul ' . e($l['name']) : 'Alte localități din județul ' . e($countyName) ?></h3>
      <ul>
        <?php foreach ($cities ?: $siblings as $c): ?><li><a href="<?= e(url('/zone/' . $c['slug'])) ?>"><?= e($c['name']) ?></a></li><?php endforeach; ?>
        <?php if ($parent): ?><li><a href="<?= e(url('/zone/' . $parent['slug'])) ?>">Tot județul <?= e($parent['name']) ?></a></li><?php endif; ?>
      </ul>
      <p style="margin-top:14px">Nu îți găsești localitatea? Acoperim întregul județ – sună-ne și stabilim.</p>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section-sm">
  <div class="container">
    <div class="section-head" data-reveal><span class="eyebrow">Remote</span><h2>Livrate de la distanță, cu aceeași grijă</h2></div>
    <div class="grid-3">
      <?php foreach ($remote as $i => $s): ?>
      <article class="card" data-reveal data-delay="<?= ($i % 3) * 70 ?>"><div class="icon-tile"><?= icon($s['icon'] ?: 'sparkles') ?></div><h3><?= e($s['title']) ?></h3><p><?= e($s['excerpt']) ?></p><a class="card-link" href="<?= e(url('/servicii/' . $s['slug'])) ?>" aria-label="<?= e($s['title']) ?>"></a></article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section bg-alt">
  <div class="container">
    <div class="section-head center" data-reveal><span class="eyebrow">Întrebări frecvente</span><h2>Servicii IT în <?= e($l['name']) ?> – întrebări frecvente</h2></div>
    <?= View::partial('site/partials/faq', ['faq' => $faq]) ?>
  </div>
</section>

<section class="section" id="oferta">
  <div class="container"><?= View::partial('site/partials/cta_form', ['title' => 'Cere ofertă în ' . $l['name'], 'text' => 'Spune-ne ce ai nevoie. Revenim în aceeași zi lucrătoare cu pașii următori.', 'county' => $countyName]) ?></div>
</section>
