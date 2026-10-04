<?php
use App\Core\Settings;
use App\Core\View;

$why = Settings::json('home_why');
$stats = Settings::json('home_stats');
echo View::partial('site/partials/page_hero', [
    'crumbs' => [['Despre noi', '/despre-noi']],
    'eyebrow' => icon('map') . ' Firmă din ' . e(setting('company_city')),
    'title' => 'Cine este VITIM',
    'lead' => $page['subtitle'] ?: 'Înainte să ne dai acces la infrastructura și datele companiei tale, vrem să știi cine suntem.',
    'actions' => '<a class="btn btn-primary btn-lg" href="' . e(url('/contact')) . '">Hai să ne cunoaștem ' . icon('arrow-right', 'ico ico-move') . '</a> <a class="btn btn-ghost btn-lg" href="' . e(phone_href((string)setting('phone'))) . '" data-loc="despre">' . icon('phone') . ' ' . e(setting('phone')) . '</a>',
]);
?>
<section class="section">
  <div class="container">
    <div class="philosophy" data-reveal>
      <span class="eyebrow">Filosofia noastră</span>
      <h2>Om, nu robot.</h2>
      <p>Folosim AI și automatizări ca să scăpăm firmele de munca repetitivă. Dar când ai o problemă, vorbești cu un om care îți cunoaște firma, nu cu un formular sau cu o coadă de tichete.</p>
      <ul class="pillars pillars-inline">
        <li><?= icon('phone') ?><strong>Răspundem la telefon</strong><span>Un singur număr pentru tot.</span></li>
        <li><?= icon('message') ?><strong>Vorbim pe înțeles</strong><span>Fără jargon și fără promisiuni goale.</span></li>
        <li><?= icon('key') ?><strong>Acces cu responsabilitate</strong><span>Lucrăm în datele tale ca în ale noastre.</span></li>
      </ul>
    </div>
  </div>
</section>

<?php if ($aboutImg = setting('about_image')): ?>
<section class="section-sm" style="padding-top:0"><div class="container"><figure class="wide-photo" style="margin:0" data-reveal><img src="<?= e(upload_url((string)$aboutImg)) ?>" srcset="<?= e(\App\Core\Uploader::srcset((string)$aboutImg)) ?>" sizes="100vw" alt="<?= e(\App\Core\DB::val('SELECT alt FROM media WHERE path = ?', [$aboutImg]) ?: setting('company_name')) ?>" loading="lazy" width="1600" height="686"></figure></div></section>
<?php endif; ?>
<?php if (trim(strip_tags($body)) !== ''): ?>
<section class="section-sm"><div class="container" style="max-width:880px"><article class="prose" data-reveal><?= $body ?></article></div></section>
<?php endif; ?>
<?php if ($stats): ?>
<section class="section-sm"><div class="container"><div class="stats" data-reveal><?php foreach ($stats as $st): ?><div class="stat"><b><?= e($st['value']) ?></b><span><?= e($st['label']) ?></span></div><?php endforeach; ?></div></div></section>
<?php endif; ?>
<section class="section bg-alt">
  <div class="container">
    <div class="section-head" data-reveal><span class="eyebrow">Cum lucrăm</span><h2>Ce poți aștepta de la noi</h2></div>
    <div class="grid-3 varied"><?php foreach ($why as $i => $w): ?><div class="card" data-reveal data-delay="<?= ($i % 3) * 70 ?>"><div class="icon-tile"><?= icon($w['icon'] ?? 'check') ?></div><h3><?= e($w['title'] ?? '') ?></h3><p><?= e($w['text'] ?? '') ?></p></div><?php endforeach; ?></div>
    <p style="margin:28px 0 0;display:flex;flex-wrap:wrap;gap:10px"><a class="btn btn-ghost" href="<?= e(url('/proiecte')) ?>">Vezi proiectele noastre <?= icon('arrow-right') ?></a> <a class="btn btn-ghost" href="<?= e(url('/servicii')) ?>">Toate serviciile</a></p>
  </div>
</section>
<?php if ($testimonials): ?>
<section class="section"><div class="container"><div class="section-head" data-reveal><span class="eyebrow">Clienți</span><h2>Ce spun clienții</h2></div><?= View::partial('site/partials/testimonials', ['items' => $testimonials]) ?></div></section>
<?php endif; ?>
<section class="section"><div class="container"><?= View::partial('site/partials/cta_form', ['title' => 'Hai să ne cunoaștem', 'text' => 'Spune-ne cum lucrează firma ta acum. Venim cu întrebări, nu cu o ofertă standard.']) ?></div></section>
