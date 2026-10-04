<?php
use App\Core\View;

echo View::partial('site/partials/page_hero', [
    'crumbs' => [['Proiecte', '/proiecte']],
    'eyebrow' => icon('award') . ' Studii de caz',
    'title' => 'Proiecte reale. Companii reale. Soluții reale.',
    'lead' => 'Pentru fiecare proiect: problema de la care am pornit, soluția VITIM și ce am implementat concret.',
    'actions' => '<a class="btn btn-primary btn-lg" href="' . e(url('/contact')) . '">Solicită o evaluare ' . icon('arrow-right', 'ico ico-move') . '</a>',
]);
?>
<section class="section"><div class="container"><div class="cases">
<?php foreach ($items as $i => $p): ?>
  <?php if (!empty($p['problem'])): ?>
  <?= View::partial('site/partials/case_card', ['p' => $p, 'i' => $i, 'max' => 12]) ?>
  <?php else: ?>
  <article class="card" data-reveal data-delay="<?= ($i % 2) * 70 ?>">
    <?php if ($p['cover']): ?><img src="<?= e(upload_url($p['cover'])) ?>" alt="" loading="lazy" style="border-radius:12px;margin:-8px -8px 16px;width:calc(100% + 16px);aspect-ratio:16/10;object-fit:cover"><?php endif; ?>
    <span class="tag"><?= e($p['service_title'] ?: 'Proiect') ?></span>
    <h3><?= e($p['title']) ?></h3><p><?= e($p['summary']) ?></p>
    <span class="more">Citește studiul <?= icon('arrow-up-right') ?></span>
    <a class="card-link" href="<?= e(url('/proiecte/' . $p['slug'])) ?>" aria-label="<?= e($p['title']) ?>"></a>
  </article>
  <?php endif; ?>
<?php endforeach; ?>
</div></div></section>
<section class="section" style="padding-top:0"><div class="container"><?= View::partial('site/partials/cta_form', ['title' => 'Vrei un proiect ca acestea?', 'text' => 'Spune-ne cum lucrează firma ta acum. Îți arătăm ce putem administra, securiza și automatiza.']) ?></div></section>
