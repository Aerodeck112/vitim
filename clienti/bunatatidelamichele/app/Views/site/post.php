<?php use App\Core\Site; use App\Core\View; ?>
<section class="page-hero"><div class="container narrow"><?= View::partial('site/partials/crumbs', ['seo' => $seo]) ?><h1><?= e($p['title']) ?></h1><p class="small"><?= e(ro_date($p['published_at'])) ?> · <?= reading_time((string)$p['body']) ?> min de citit</p></div></section>
<section class="section-sm"><div class="container narrow">
  <?php if ($p['cover']): ?><div class="photo" style="margin-bottom:34px"><?= Site::img($p['cover'], (string)$p['title'], '(max-width:820px) 100vw, 820px', '', false) ?></div><?php endif; ?>
  <?php if (count($toc) > 2): ?><nav class="card" style="margin-bottom:28px" aria-label="Cuprins"><strong>Cuprins</strong><ol style="margin:8px 0 0"><?php foreach ($toc as $t): ?><li><a href="#<?= e($t['id']) ?>"><?= e($t['text']) ?></a></li><?php endforeach; ?></ol></nav><?php endif; ?>
  <article class="prose"><?= $body ?></article>
  <?php if ($faq = json_list($p['faq'])): ?><h2 style="margin-top:40px">Întrebări frecvente</h2><?= View::partial('site/partials/faq', ['faq' => $faq]) ?><?php endif; ?>
</div></section>
<?php if ($products): ?><section class="section bg-paper"><div class="container"><h2 class="center">Din magazinul nostru</h2><div class="grid-products" style="margin-top:30px"><?php foreach ($products as $fp): ?><?= View::partial('site/partials/product_card', ['p' => $fp]) ?><?php endforeach; ?></div></div></section><?php endif; ?>
