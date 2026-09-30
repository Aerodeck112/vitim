<?php
use App\Core\View;

$title = $cat ? ($cat['h1'] ?: $cat['name']) : 'Toate produsele';
?>
<section class="page-hero">
  <div class="container">
    <?= View::partial('site/partials/crumbs', ['seo' => $seo]) ?>
    <h1><?= e($q !== '' ? 'Rezultate pentru „' . $q . '”' : $title) ?></h1>
    <p><?= e($cat ? $cat['intro'] : 'Cafea italiană premium: boabe prăjite la foc de lemn, cialde și monodoze Saka, espresoare profesionale.') ?></p>
  </div>
</section>
<section class="section-sm">
  <div class="container shop-layout">
    <aside class="filters" aria-label="Categorii">
      <div>
        <h3>Categorii</h3>
        <nav>
          <a href="<?= e(url('/produse')) ?>"<?= !$cat ? ' aria-current="page"' : '' ?>>Toate produsele</a>
          <?php foreach ($categories as $c): ?><a href="<?= e(url('/categorie/' . $c['slug'])) ?>"<?= $cat && (int)$cat['id'] === (int)$c['id'] ? ' aria-current="page"' : '' ?>><?= e($c['name']) ?> <small><?= (int)$c['product_count'] ?></small></a><?php endforeach; ?>
        </nav>
      </div>
      <div class="card" style="padding:20px">
        <p style="margin:0 0 6px;font-weight:600">Ai nevoie de ajutor?</p>
        <p class="small muted" style="margin:0 0 12px">Îți recomandăm cafeaua potrivită pentru espressorul tău.</p>
        <a class="link-arrow" href="<?= e(url('/contact')) ?>">Scrie-ne <?= icon('arrow-right') ?></a>
      </div>
    </aside>
    <div>
      <div class="chips" aria-label="Categorii">
        <a href="<?= e(url('/produse')) ?>"<?= !$cat ? ' aria-current="page"' : '' ?>>Toate</a>
        <?php foreach ($categories as $c): ?><a href="<?= e(url('/categorie/' . $c['slug'])) ?>"<?= $cat && (int)$cat['id'] === (int)$c['id'] ? ' aria-current="page"' : '' ?>><?= e($c['name']) ?></a><?php endforeach; ?>
      </div>
      <div class="toolbar">
        <span class="muted"><?= count($products) ?> <?= count($products) === 1 ? 'produs' : 'produse' ?></span>
        <form method="get" action="<?= e(url($path)) ?>">
          <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
          <label class="sr" for="ord">Ordonează</label>
          <select class="select" id="ord" name="ordonare" onchange="this.form.submit()">
            <?php foreach ($sorts as $k => $l): ?><option value="<?= e($k) ?>"<?= $k === $sort ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
          </select>
          <noscript><button class="btn btn-sm" type="submit">Aplică</button></noscript>
        </form>
      </div>
      <?php if ($products): ?>
      <div class="grid-products grid-auto">
        <?php foreach ($products as $i => $p): ?><?= View::partial('site/partials/product_card', ['p' => $p, 'lazy' => $i > 3]) ?><?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="card center"><p style="font-size:19px;margin-bottom:8px">Nu am găsit produse<?= $q !== '' ? ' pentru „' . e($q) . '”' : '' ?>.</p><p class="muted">Încearcă alt cuvânt sau vezi <a href="<?= e(url('/produse')) ?>">toate produsele</a>.</p></div>
      <?php endif; ?>
      <?php if ($cat && (trim(strip_tags((string)$cat['body'])) !== '' || json_list($cat['faq']))): ?>
      <div class="cat-intro prose">
        <?= $cat['body'] ?>
        <?php if ($faq = json_list($cat['faq'])): ?><h2 style="font-size:24px">Întrebări frecvente</h2><?= View::partial('site/partials/faq', ['faq' => $faq]) ?><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?= View::partial('site/partials/perks') ?>
