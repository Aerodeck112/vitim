<?php
use App\Core\View;
use App\Core\Site;

$h1 = $s['h1'] ?: $s['title'];
echo View::partial('site/partials/page_hero', [
    'crumbs' => [['Servicii', '/servicii'], [$s['title'], '/servicii/' . $s['slug']]],
    'eyebrow' => icon($s['icon'] ?: 'sparkles') . ' ' . e($cat['name']) . ($s['onsite'] ? ' · remote + la sediu' : ' · în toată România'),
    'title' => e($h1),
    'image' => $s['image'] ?? '',
    'lead' => $s['excerpt'],
    'actions' => '<a class="btn btn-primary btn-lg" href="#oferta">Cere o ofertă gratuită ' . icon('arrow-right', 'ico ico-move') . '</a><a class="btn btn-ghost btn-lg" href="' . e(phone_href((string)setting('phone'))) . '" data-loc="service-hero">' . icon('phone') . ' ' . e(setting('phone')) . '</a>',
]);
?>
<?php if ($features): ?>
<section class="section-sm">
  <div class="container">
    <div class="section-head" data-reveal><span class="eyebrow">Ce include</span><h2>Ce primești concret</h2></div>
    <div class="feature-grid">
      <?php foreach ($features as $i => $f): ?>
      <div class="feature" data-reveal data-delay="<?= ($i % 3) * 70 ?>"><?= icon($f['icon'] ?? 'check-circle') ?><h3><?= e($f['title'] ?? '') ?></h3><p><?= e($f['text'] ?? '') ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section-sm">
  <div class="container layout-side">
    <article class="prose" data-reveal><?= $body ?></article>
    <aside class="sticky">
      <?php if (count($toc) > 2): ?>
      <div class="side-card"><h3>Pe această pagină</h3><ul class="toc"><?php foreach ($toc as $t): ?><li class="l<?= $t['level'] ?>"><a href="#<?= e($t['id']) ?>"><?= e($t['text']) ?></a></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <div class="side-card">
        <h3>Discută cu un specialist</h3>
        <p class="muted" style="font-size:15px;margin:0">Consultanța inițială și oferta sunt gratuite.<?php if ($s['price_from']): ?> Prețuri <strong style="color:var(--text)">de la <?= e($s['price_from']) ?></strong>.<?php endif; ?></p>
        <a class="btn btn-primary btn-block" href="#oferta">Cere ofertă</a>
        <a class="btn btn-ghost btn-block" href="<?= e(phone_href((string)setting('phone'))) ?>" data-loc="service-side"><?= icon('phone') ?> <?= e(setting('phone')) ?></a>
      </div>
      <?php if ($s['onsite']): ?>
      <div class="side-card"><h3>Intervenții la sediu</h3><ul class="toc"><?php foreach (Site::counties() as $c): ?><li><a href="<?= e(url('/zone/' . $c['slug'])) ?>">Județul <?= e($c['name']) ?></a></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
    </aside>
  </div>
</section>

<?php if ($process): ?>
<section class="section bg-alt">
  <div class="container">
    <div class="section-head" data-reveal><span class="eyebrow">Proces</span><h2>Cum lucrăm</h2></div>
    <div class="steps"><?php foreach ($process as $i => $p): ?><div class="step" data-reveal data-delay="<?= $i * 80 ?>"><h3><?= e($p['title'] ?? '') ?></h3><p><?= e($p['text'] ?? '') ?></p></div><?php endforeach; ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($testimonials): ?>
<section class="section"><div class="container"><div class="section-head" data-reveal><span class="eyebrow">Păreri</span><h2>Ce spun clienții</h2></div><?= View::partial('site/partials/testimonials', ['items' => $testimonials]) ?></div></section>
<?php endif; ?>

<?php if ($faq): ?>
<section class="section<?= $testimonials ? ' bg-alt' : '' ?>">
  <div class="container">
    <div class="section-head center" data-reveal><span class="eyebrow">Întrebări frecvente</span><h2><?= e($s['title']) ?> – întrebări frecvente</h2></div>
    <?= View::partial('site/partials/faq', ['faq' => $faq]) ?>
  </div>
</section>
<?php endif; ?>

<?php if ($related): ?>
<section class="section-sm">
  <div class="container">
    <div class="section-head" data-reveal><span class="eyebrow">Servicii conexe</span><h2>Te-ar putea interesa și</h2></div>
    <div class="grid-3">
      <?php foreach ($related as $r): ?>
      <article class="card" data-reveal><?php if (!empty($r['image'])): ?><div class="card-img"><img src="<?= e(upload_url($r['image'])) ?>" srcset="<?= e(\App\Core\Uploader::srcset($r['image'])) ?>" sizes="400px" alt="" loading="lazy" width="960" height="540"></div><?php endif; ?><div class="icon-tile"><?= icon($r['icon'] ?: 'sparkles') ?></div><h3><?= e($r['title']) ?></h3><p><?= e($r['excerpt']) ?></p><span class="more">Detalii <?= icon('arrow-up-right') ?></span><a class="card-link" href="<?= e(url('/servicii/' . $r['slug'])) ?>" aria-label="<?= e($r['title']) ?>"></a></article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section" id="oferta">
  <div class="container"><?= View::partial('site/partials/cta_form', ['title' => 'Cere o ofertă: ' . $s['title'], 'text' => 'Spune-ne câteva detalii și revenim în aceeași zi lucrătoare cu o propunere clară – fără obligații.', 'service' => $s['slug']]) ?></div>
</section>
