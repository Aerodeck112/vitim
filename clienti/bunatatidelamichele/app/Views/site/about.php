<?php use App\Core\Site; use App\Core\View; ?>
<section class="page-hero"><div class="container"><?= View::partial('site/partials/crumbs', ['seo' => $seo]) ?><span class="kicker">Câteva cuvinte despre noi</span><h1><?= e($p['subtitle'] ?: $p['title']) ?></h1></div></section>
<section class="section-sm">
  <div class="container split" style="align-items:start">
    <div class="photo reveal" style="position:sticky;top:110px"><?= Site::img((string)setting('about_image'), 'Espresso turnat dintr-un espresor profesional', '(max-width:960px) 100vw, 600px', '', false) ?><div class="photo-tag"><b>Saka</b><span>&amp; Pareo<br>unicii importatori</span></div></div>
    <div class="prose reveal"><?= $body ?><p style="margin-top:28px"><a class="btn" href="<?= e(url('/produse')) ?>">Descoperă cafeaua noastră <?= icon('arrow-right') ?></a></p></div>
  </div>
</section>
<section class="section stats-band" style="margin-top:40px">
  <div class="container" style="grid-template-columns:1fr">
    <div class="stats stats-4"><?php foreach ($stats as $s): ?><div class="stat"><b><?= e($s['value']) ?></b><span><?= e($s['label']) ?></span></div><?php endforeach; ?></div>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="section-head"><span class="kicker"><?= e(setting('home_process_kicker')) ?></span><h2><?= e(setting('home_process_title')) ?></h2><p><?= e(setting('home_process_text')) ?></p></div>
    <div class="steps"><?php foreach ($process as $s): ?><div class="step reveal"><div class="im"><?= Site::img($s['image'] ?? '', (string)$s['title'], '120px') ?></div><h3><?= e($s['title']) ?></h3><p><?= e($s['text']) ?></p></div><?php endforeach; ?></div>
  </div>
</section>
<?php if ($featured): ?>
<section class="section bg-paper"><div class="container"><div class="section-head"><span class="kicker">Aromă autentică</span><h2>Produse recomandate</h2></div><div class="grid-products"><?php foreach ($featured as $fp): ?><?= View::partial('site/partials/product_card', ['p' => $fp]) ?><?php endforeach; ?></div></div></section>
<?php endif; ?>
