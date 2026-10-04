<?php
use App\Core\View;

echo View::partial('site/partials/page_hero', [
    'crumbs' => [['Proiecte', '/proiecte']],
    'eyebrow' => icon('award') . ' Studii de caz',
    'title' => 'Proiecte reale. Companii reale. Soluții reale.',
    'lead' => 'Pentru fiecare proiect: problema de la care am pornit, ce face VITIM și ce am implementat concret. Imaginile sunt capturi ale site-urilor reale.',
    'actions' => '<a class="btn btn-primary btn-lg" href="' . e(url('/contact')) . '">Solicită o evaluare ' . icon('arrow-right', 'ico ico-move') . '</a>',
]);
?>
<section class="section"><div class="container">
  <div class="case-rows">
  <?php foreach ($items as $i => $p): $features = array_values(array_filter(array_map('trim', explode("\n", (string)($p['features'] ?? ''))))); ?>
    <article class="case-row<?= $i % 2 ? ' flip' : '' ?>" data-reveal>
      <div class="case-row-media">
        <?php if (!empty($p['cover'])): ?><?= View::partial('site/partials/showcase', ['p' => $p, 'size' => 'sm']) ?><?php endif; ?>
      </div>
      <div class="case-row-text">
        <div class="case-head">
          <span class="case-client"><?= e($p['client'] ?: $p['title']) ?><?php if (!empty($p['own'])): ?> <span class="own-flag">proiect propriu</span><?php endif; ?></span>
          <?php if (!empty($p['tag'])): ?><span class="tag"><?= e($p['tag']) ?></span><?php endif; ?>
        </div>
        <h2 class="case-row-title"><a href="<?= e(url('/proiecte/' . $p['slug'])) ?>"><?= e($p['summary']) ?></a></h2>
        <?php if (!empty($p['solution'])): ?><p class="muted"><?= e($p['solution']) ?></p><?php endif; ?>
        <?php if ($features): ?><ul class="chips-static"><?php foreach ($features as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <a class="link" href="<?= e(url('/proiecte/' . $p['slug'])) ?>">Problema, soluția și ce am implementat <?= icon('arrow-right') ?></a>
      </div>
    </article>
  <?php endforeach; ?>
  </div>
</div></section>
<section class="section" style="padding-top:0"><div class="container"><?= View::partial('site/partials/cta_form', ['title' => 'Vrei un proiect ca acestea?', 'text' => 'Spune-ne cum lucrează firma ta acum. Îți arătăm ce putem administra, securiza și automatiza.']) ?></div></section>
