<?php
use App\Core\View;

echo View::partial('site/partials/page_hero', [
    'crumbs' => [['Blog', '/blog']],
    'eyebrow' => icon('pen') . ' Ghiduri practice',
    'title' => 'Blog: IT, securitate, marketing și <span class="grad">AI pentru firme</span>',
    'lead' => 'Explicații clare, fără jargon, pentru antreprenori care vor să ia decizii bune în tehnologie.',
]);
?>
<section class="section">
  <div class="container">
    <?php if ($posts): ?>
    <div class="posts"><?php foreach ($posts as $p): ?><?= View::partial('site/partials/post_card', ['p' => $p]) ?><?php endforeach; ?></div>
    <?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Paginare">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?><span class="cur" aria-current="page"><?= $i ?></span><?php else: ?><a href="<?= e(url($i === 1 ? '/blog' : '/blog/pagina/' . $i)) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
    </nav>
    <?php endif; ?>
    <?php else: ?>
    <div class="empty">Primele articole apar în curând. Între timp, <a class="link" href="<?= e(url('/contact')) ?>">scrie-ne</a> ce te interesează.</div>
    <?php endif; ?>
  </div>
</section>
