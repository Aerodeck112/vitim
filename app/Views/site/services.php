<?php
use App\Core\View;

echo View::partial('site/partials/page_hero', [
    'crumbs' => [['Servicii', '/servicii']],
    'eyebrow' => icon('layers') . ' ' . count(\App\Core\Site::services()) . ' servicii, un singur partener',
    'title' => 'Servicii IT, securitate, marketing și <span class="grad">AI pentru firme</span>',
    'lead' => 'Alege exact ce ai nevoie sau lasă-ne să construim un pachet complet: infrastructură IT fiabilă, date protejate, clienți din online și procese automatizate.',
    'actions' => '<a class="btn btn-primary btn-lg" href="' . e(url('/contact')) . '">Cere o ofertă ' . icon('arrow-right', 'ico ico-move') . '</a>',
]);
?>
<section class="section">
  <div class="container">
    <?php foreach ($groups as $key => $g): ?>
    <div class="svc-group" id="<?= e($key) ?>">
      <div class="svc-group-head" data-reveal>
        <div style="max-width:640px">
          <span class="eyebrow"><?= e($g['cat']['short']) ?></span>
          <h2 style="margin-bottom:10px"><?= e($g['cat']['name']) ?></h2>
          <p class="muted" style="margin:0"><?= e($g['cat']['intro']) ?></p>
        </div>
      </div>
      <div class="grid-3">
        <?php foreach ($g['items'] as $i => $s): ?>
        <article class="card" data-reveal data-delay="<?= ($i % 3) * 70 ?>">
          <div class="icon-tile"><?= icon($s['icon'] ?: 'sparkles') ?></div>
          <h3><?= e($s['title']) ?></h3>
          <p><?= e($s['excerpt']) ?></p>
          <span class="more">Află mai mult <?= icon('arrow-up-right') ?></span>
          <a class="card-link" href="<?= e(url('/servicii/' . $s['slug'])) ?>" aria-label="<?= e($s['title']) ?>"></a>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<section class="section" style="padding-top:0">
  <div class="container"><?= View::partial('site/partials/cta_form', ['title' => 'Nu știi de unde să începi?', 'text' => 'Descrie-ne pe scurt situația. Îți recomandăm gratuit soluția potrivită și îți spunem sincer ce merită și ce nu.']) ?></div>
</section>
