<?php
use App\Core\View;

$features = array_values(array_filter(array_map('trim', explode("\n", (string)($p['features'] ?? '')))));
echo View::partial('site/partials/page_hero', ['crumbs' => [['Proiecte', '/proiecte'], [$p['client'] ?: $p['title'], '/proiecte/' . $p['slug']]], 'eyebrow' => icon('award') . ' ' . e($p['tag'] ?? '' ?: ($p['service_title'] ?: 'Studiu de caz')) . ($p['client'] ? ' · ' . e($p['client']) : ''), 'title' => e($p['title']), 'lead' => $p['summary']]);
?>
<?php if ($results): ?>
<section class="section-sm"><div class="container"><div class="stats"><?php foreach ($results as $r): ?><div class="stat"><b><?= e($r['value'] ?? '') ?></b><span><?= e($r['label'] ?? '') ?></span></div><?php endforeach; ?></div></div></section>
<?php endif; ?>
<section class="section-sm"><div class="container" style="max-width:980px">
  <?php if ($p['cover']): ?><div class="project-shot" data-reveal><?= View::partial('site/partials/showcase', ['p' => $p, 'size' => 'lg', 'eager' => true]) ?></div><?php endif; ?>
  <?php if (!empty($p['site'])): ?><p class="project-site"><?= icon('globe') ?> <a class="link" href="https://<?= e($p['site']) ?>" target="_blank" rel="noopener"><?= e($p['site']) ?> <?= icon('arrow-up-right') ?></a></p><?php endif; ?>
  <?php if (!empty($p['problem'])): ?>
  <ol class="case-flow">
    <li data-reveal><span class="mono">01</span><div><h2>Problema</h2><p><?= e($p['problem']) ?></p></div></li>
    <li data-reveal data-delay="80"><span class="mono">02</span><div><h2>Soluția VITIM</h2><p><?= e((string)$p['solution']) ?></p></div></li>
    <?php if ($features): ?><li data-reveal data-delay="160"><span class="mono">03</span><div><h2>Ce am implementat</h2><ul class="checklist"><?php foreach ($features as $f): ?><li><?= icon('check-circle') ?><span><?= e($f) ?></span></li><?php endforeach; ?></ul></div></li><?php endif; ?>
  </ol>
  <?php else: ?>
  <article class="prose"><?= $p['body'] ?></article>
  <?php endif; ?>
  <p style="margin-top:32px;display:flex;flex-wrap:wrap;gap:10px">
    <?php if ($p['service_slug']): ?><a class="btn btn-ghost" href="<?= e(url('/servicii/' . $p['service_slug'])) ?>">Despre serviciul <?= e($p['service_title']) ?> <?= icon('arrow-right') ?></a><?php endif; ?>
    <a class="btn btn-ghost" href="<?= e(url('/proiecte')) ?>">Toate proiectele</a>
  </p>
</div></section>
<section class="section"><div class="container"><?= View::partial('site/partials/cta_form', ['title' => 'Ai o provocare asemănătoare?', 'text' => 'Spune-ne unde ești acum și unde vrei să ajungi. Îți spunem sincer ce se poate face.', 'service' => $p['service_slug'] ?? '']) ?></div></section>
