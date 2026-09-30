<?php
use App\Core\Site;
use App\Core\View;

$stepIcons = ['bean', 'flame', 'coffee', 'package'];
$excl = \App\Core\Settings::json('home_excl');
$brands = \App\Core\Settings::json('home_brands');
?>
<section class="hero" aria-label="Prezentare">
  <div class="container">
    <div class="slides" data-slides>
      <?php foreach ($slides as $i => $s): ?>
      <div class="slide" id="slide-<?= $i ?>" aria-roledescription="slide" aria-label="<?= $i + 1 ?> din <?= count($slides) ?>">
        <div class="txt">
          <?php if (!empty($s['kicker'])): ?><span class="kicker"><?= e($s['kicker']) ?></span><?php endif; ?>
          <?php if ($i === 0): ?><h1><?= e($s['title']) ?> <span class="accent"><?= e($s['accent'] ?? '') ?></span></h1><?php else: ?><p class="h1" role="heading" aria-level="2"><?= e($s['title']) ?> <span class="accent"><?= e($s['accent'] ?? '') ?></span></p><?php endif; ?>
          <?php if (!empty($s['text'])): ?><p><?= e($s['text']) ?></p><?php endif; ?>
          <div class="cta">
            <a class="btn" href="<?= e(url($s['link'] ?: '/produse')) ?>"><?= e($s['button'] ?: 'Vezi produsele') ?> <?= icon('arrow-right') ?></a>
            <a class="btn btn-ghost" href="<?= e(url('/despre-noi')) ?>">Povestea noastră</a>
          </div>
          <?php if ($i === 0): ?><div class="hero-seal"><span class="ring"><?= icon('award') ?></span><span>Unicul importator Saka &amp; Pareo în România<br>Prăjită la foc de lemn · Direct din Italia</span></div><?php endif; ?>
        </div>
        <div class="art"><?= Site::img($s['image'] ?? '', trim((string)($s['title'] . ' ' . ($s['accent'] ?? ''))), '(max-width:960px) 90vw, 600px', '', $i > 0) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php if (count($slides) > 1): ?><div class="dots" data-dots><?php foreach ($slides as $i => $s): ?><button type="button" aria-label="Slide <?= $i + 1 ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>></button><?php endforeach; ?></div><?php endif; ?>
</section>

<?= View::partial('site/partials/perks') ?>

<?php if ($excl): ?>
<section class="section">
  <div class="container">
    <div class="section-head reveal"><span class="kicker"><?= e(setting('home_excl_kicker')) ?></span><h2><?= e(setting('home_excl_title')) ?></h2></div>
    <div class="excl reveal">
      <?php foreach ($excl as $i => $x): ?><div><div class="n"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></div><h3><?= e($x['title']) ?></h3><p><?= e($x['text']) ?></p></div><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section bg-paper" style="padding-top:clamp(56px,6vw,90px)">
  <div class="container">
    <div class="section-head reveal">
      <span class="kicker">Colecția</span>
      <h2><?= e(setting('home_intro_title')) ?></h2>
      <p><?= e(setting('home_intro_text')) ?></p>
    </div>
    <div class="cats">
      <?php foreach ($categories as $c): ?>
      <a class="cat reveal" href="<?= e(url('/categorie/' . $c['slug'])) ?>">
        <?= Site::img($c['icon'] ?: $c['image'], (string)$c['name'], '220px') ?>
        <h3><?= e($c['name']) ?></h3>
        <p><?= e(excerpt((string)$c['intro'], 110)) ?></p>
        <span class="link-arrow">Descoperă <?= icon('arrow-right') ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" id="recomandate">
  <div class="container">
    <div class="section-head reveal">
      <span class="kicker"><?= e(setting('home_featured_kicker')) ?></span>
      <h2><?= e(setting('home_featured_title')) ?></h2>
      <p><?= e(setting('home_featured_text')) ?></p>
    </div>
    <div class="grid-products">
      <?php foreach ($featured as $p): ?><?= View::partial('site/partials/product_card', ['p' => $p]) ?><?php endforeach; ?>
    </div>
    <p class="center" style="margin:46px 0 0"><a class="btn btn-dark" href="<?= e(url('/produse')) ?>">Toată colecția <?= icon('arrow-right') ?></a></p>
  </div>
</section>

<?php if ($brands): ?>
<section class="section dark">
  <div class="container">
    <div class="section-head reveal"><span class="kicker"><?= e(setting('home_brands_kicker')) ?></span><h2><?= e(setting('home_brands_title')) ?></h2></div>
    <div class="brands">
      <?php foreach ($brands as $b): ?>
      <a class="brand-card reveal" href="<?= e(url($b['link'] ?: '/produse')) ?>">
        <div><span class="bn"><?= e($b['name']) ?></span><span class="bt"><?= e($b['tag']) ?></span><p><?= e($b['text']) ?></p><span class="link-arrow">Vezi produsele <?= icon('arrow-right') ?></span></div>
        <?= Site::img($b['image'] ?? '', 'Cafea ' . $b['name'], '(max-width:720px) 70vw, 280px') ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php if ($q = setting('home_quote')): ?>
    <div class="quote-band reveal" style="margin-top:80px"><blockquote>„<?= e($q) ?>”</blockquote><cite><?= e(setting('home_quote_cite')) ?></cite></div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div class="section-head reveal">
      <span class="kicker"><?= e(setting('home_process_kicker')) ?></span>
      <h2><?= e(setting('home_process_title')) ?></h2>
      <p><?= e(setting('home_process_text')) ?></p>
    </div>
    <div class="steps">
      <?php foreach ($process as $i => $s): ?>
      <div class="step reveal">
        <div class="im"><?= icon($stepIcons[$i % 4]) ?></div>
        <h3><?= e($s['title']) ?></h3>
        <p><?= e($s['text']) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section stats-band">
  <div class="container">
    <div class="reveal">
      <span class="kicker"><?= e(setting('home_stats_kicker')) ?></span>
      <h2><?= e(setting('home_stats_title')) ?></h2>
      <p><?= e(setting('home_stats_text')) ?></p>
      <div class="stats" style="margin-top:30px">
        <?php foreach ($stats as $s): ?><div class="stat"><b><?= e($s['value']) ?></b><span><?= e($s['label']) ?></span></div><?php endforeach; ?>
      </div>
    </div>
    <div class="stats-art reveal"><?= Site::img((string)setting('home_intro_image'), 'Ceașcă de espresso cu scorțișoară', '(max-width:960px) 90vw, 520px') ?></div>
  </div>
</section>

<section class="section bg-sand">
  <div class="container split">
    <div class="photo reveal">
      <?= Site::img((string)setting('about_image'), 'Espresso turnat dintr-un espresor profesional', '(max-width:960px) 100vw, 600px') ?>
      <div class="photo-tag"><b>100%</b><span>cafea italiană<br>autentică</span></div>
    </div>
    <div class="reveal">
      <span class="kicker">Despre noi</span>
      <h2>Gusturi autentice din Italia, aduse în România</h2>
      <p class="muted" style="font-size:18px">Suntem mândri să fim <strong>unicii importatori ai cafelei Saka și Pareo</strong>, două branduri care duc mai departe tradiția italiană a cafelei de calitate. Fie că preferi cafeaua boabe pentru un espresso intens sau pad-urile pentru un confort rapid, fiecare ceașcă aduce cu sine o bucată din Italia.</p>
      <ul class="checks">
        <li><?= icon('check-circle') ?> Boabe verzi atent alese, prăjite tradițional la foc de lemn</li>
        <li><?= icon('check-circle') ?> Cialde și monodoze ambalate individual, mereu proaspete</li>
        <li><?= icon('check-circle') ?> Livrare rapidă acasă sau la birou, în toată țara</li>
      </ul>
      <a class="btn btn-dark" href="<?= e(url('/despre-noi')) ?>">Citește povestea noastră <?= icon('arrow-right') ?></a>
    </div>
  </div>
</section>

<?php if ($faq): ?>
<section class="section">
  <div class="container">
    <div class="section-head reveal"><span class="kicker">Întrebări frecvente</span><h2>Răspunsuri la întrebările tale</h2></div>
    <?= View::partial('site/partials/faq', ['faq' => $faq, 'openFirst' => true]) ?>
  </div>
</section>
<?php endif; ?>

<section class="section-sm" style="padding-bottom:90px">
  <div class="container">
    <div class="cta-box reveal">
      <div><span class="kicker" style="color:var(--gold)">Pentru birou, cafenea sau cadou</span><h2>Vrei cafea italiană pe care n-o are nimeni altcineva?</h2><p>Scrie-ne și îți recomandăm sortimentul potrivit și cantitatea de care ai nevoie.</p></div>
      <div class="btns"><a class="btn" href="<?= e(url('/produse')) ?>">Comandă online</a><a class="btn btn-ghost" href="<?= e(url('/contact')) ?>">Contactează-ne</a></div>
    </div>
  </div>
</section>
