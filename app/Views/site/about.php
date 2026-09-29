<?php
use App\Core\Settings;
use App\Core\View;

$why = Settings::json('home_why');
$stats = Settings::json('home_stats');
echo View::partial('site/partials/page_hero', [
    'crumbs' => [['Despre noi', '/despre-noi']],
    'eyebrow' => icon('users') . ' ' . e(setting('company_name')),
    'title' => e($page['title'] === 'Despre noi' ? '' : $page['title']) ?: 'Tehnologie făcută simplu, <span class="grad">de oameni care te ascultă</span>',
    'lead' => $page['subtitle'] ?: 'Suntem o echipă din ' . setting('company_city') . ' care ajută firmele să folosească tehnologia fără stres: IT care funcționează, date în siguranță, clienți din online și procese automatizate cu AI.',
    'actions' => '<a class="btn btn-primary btn-lg" href="' . e(url('/contact')) . '">Hai să ne cunoaștem ' . icon('arrow-right', 'ico ico-move') . '</a>',
]);
?>
<?php if (trim(strip_tags($body)) !== ''): ?>
<section class="section-sm"><div class="container" style="max-width:880px"><article class="prose" data-reveal><?= $body ?></article></div></section>
<?php endif; ?>
<?php if ($stats): ?>
<section class="section-sm"><div class="container"><div class="stats" data-reveal><?php foreach ($stats as $st): ?><div class="stat"><b class="grad"><?= e($st['value']) ?></b><span><?= e($st['label']) ?></span></div><?php endforeach; ?></div></div></section>
<?php endif; ?>
<section class="section bg-alt">
  <div class="container">
    <div class="section-head" data-reveal><span class="eyebrow">Valori</span><h2>Ce ne definește</h2></div>
    <div class="grid-3"><?php foreach ($why as $i => $w): ?><div class="card" data-reveal data-delay="<?= ($i % 3) * 70 ?>"><div class="icon-tile"><?= icon($w['icon'] ?? 'check') ?></div><h3><?= e($w['title'] ?? '') ?></h3><p><?= e($w['text'] ?? '') ?></p></div><?php endforeach; ?></div>
  </div>
</section>
<?php if ($testimonials): ?>
<section class="section"><div class="container"><div class="section-head" data-reveal><span class="eyebrow">Clienți</span><h2>Ce spun clienții</h2></div><?= View::partial('site/partials/testimonials', ['items' => $testimonials]) ?></div></section>
<?php endif; ?>
<section class="section"><div class="container"><?= View::partial('site/partials/cta_form', ['title' => 'Hai să lucrăm împreună', 'text' => 'Spune-ne câteva cuvinte despre firma ta. Îți propunem pașii potriviți, fără obligații.']) ?></div></section>
