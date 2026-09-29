<?php
use App\Core\View;

echo View::partial('site/partials/page_hero', ['crumbs' => [['Proiecte', '/proiecte']], 'eyebrow' => icon('award') . ' Studii de caz', 'title' => 'Proiecte și rezultate reale', 'lead' => 'Problema, soluția și ce s-a schimbat concret pentru clienții noștri.']);
?>
<section class="section"><div class="container"><div class="grid-3">
<?php foreach ($items as $i => $p): ?>
  <article class="card" data-reveal data-delay="<?= ($i % 3) * 70 ?>">
    <?php if ($p['cover']): ?><img src="<?= e(upload_url($p['cover'])) ?>" alt="" loading="lazy" style="border-radius:12px;margin:-8px -8px 16px;width:calc(100% + 16px);aspect-ratio:16/10;object-fit:cover"><?php endif; ?>
    <span class="tag"><?= e($p['service_title'] ?: 'Proiect') ?></span>
    <h3><?= e($p['title']) ?></h3><p><?= e($p['summary']) ?></p>
    <span class="more">Citește studiul <?= icon('arrow-up-right') ?></span>
    <a class="card-link" href="<?= e(url('/proiecte/' . $p['slug'])) ?>" aria-label="<?= e($p['title']) ?>"></a>
  </article>
<?php endforeach; ?>
</div></div></section>
