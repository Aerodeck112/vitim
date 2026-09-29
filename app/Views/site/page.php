<?php
use App\Core\View;

echo View::partial('site/partials/page_hero', [
    'crumbs' => [[$page['title'], '/' . $page['slug']]],
    'title' => e($page['title']),
    'lead' => $page['subtitle'],
]);
?>
<section class="section-sm">
  <div class="container<?= count($toc) > 3 ? ' layout-side' : '' ?>" <?= count($toc) > 3 ? '' : 'style="max-width:880px"' ?>>
    <article class="prose"><?= $body ?></article>
    <?php if (count($toc) > 3): ?>
    <aside class="sticky"><div class="side-card"><h3>Cuprins</h3><ul class="toc"><?php foreach ($toc as $t): ?><li class="l<?= $t['level'] ?>"><a href="#<?= e($t['id']) ?>"><?= e($t['text']) ?></a></li><?php endforeach; ?></ul></div></aside>
    <?php endif; ?>
  </div>
</section>
<?php if ($page['template'] === 'cta'): ?>
<section class="section"><div class="container"><?= View::partial('site/partials/cta_form', ['title' => 'Hai să vorbim', 'text' => 'Spune-ne ce ai nevoie și revenim rapid.']) ?></div></section>
<?php endif; ?>
