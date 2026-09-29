<?php
use App\Core\View;

echo View::partial('site/partials/page_hero', ['crumbs' => [['Proiecte', '/proiecte'], [$p['title'], '/proiecte/' . $p['slug']]], 'eyebrow' => icon('award') . ' ' . e($p['service_title'] ?: 'Studiu de caz') . ($p['client'] ? ' · ' . e($p['client']) : ''), 'title' => e($p['title']), 'lead' => $p['summary']]);
?>
<?php if ($results): ?>
<section class="section-sm"><div class="container"><div class="stats"><?php foreach ($results as $r): ?><div class="stat"><b><?= e($r['value'] ?? '') ?></b><span><?= e($r['label'] ?? '') ?></span></div><?php endforeach; ?></div></div></section>
<?php endif; ?>
<section class="section-sm"><div class="container" style="max-width:880px">
  <?php if ($p['cover']): ?><div class="article-cover"><img src="<?= e(upload_url($p['cover'])) ?>" alt="<?= e($p['title']) ?>"></div><?php endif; ?>
  <article class="prose"><?= $p['body'] ?></article>
  <?php if ($p['service_slug']): ?><p style="margin-top:32px"><a class="btn btn-ghost" href="<?= e(url('/servicii/' . $p['service_slug'])) ?>">Despre serviciul <?= e($p['service_title']) ?> <?= icon('arrow-right') ?></a></p><?php endif; ?>
</div></section>
<section class="section"><div class="container"><?= View::partial('site/partials/cta_form', ['title' => 'Vrei rezultate similare?', 'text' => 'Spune-ne unde ești acum și unde vrei să ajungi.', 'service' => $p['service_slug'] ?? '']) ?></div></section>
