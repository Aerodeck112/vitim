<?php use App\Core\Site; use App\Core\View; ?>
<section class="page-hero"><div class="container"><?= View::partial('site/partials/crumbs', ['seo' => $seo]) ?><h1>Blog</h1><p>Ghiduri, rețete și povești despre cafeaua italiană.</p></div></section>
<section class="section-sm" style="padding-bottom:90px"><div class="container"><div class="grid-products" style="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))">
<?php foreach ($posts as $p): ?>
<article class="pcard reveal"><a class="pimg" href="<?= e(url('/blog/' . $p['slug'])) ?>" style="aspect-ratio:16/10" tabindex="-1"><?= Site::img($p['cover'], (string)$p['title'], '(max-width:720px) 100vw, 400px') ?></a><div class="pbody"><span class="pcat"><?= e(ro_date($p['published_at'])) ?></span><h3><a href="<?= e(url('/blog/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3><p class="small muted" style="margin:0"><?= e($p['excerpt'] ?: excerpt((string)$p['body'])) ?></p></div></article>
<?php endforeach; ?>
</div></div></section>
