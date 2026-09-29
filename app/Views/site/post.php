<?php
use App\Core\View;

echo View::partial('site/partials/page_hero', [
    'crumbs' => [['Blog', '/blog'], [$post['title'], '/blog/' . $post['slug']]],
    'eyebrow' => icon('calendar') . ' <time datetime="' . e(substr((string)$post['published_at'], 0, 10)) . '">' . e(ro_date($post['published_at'])) . '</time> · ' . reading_time((string)$post['body']) . ' min de citit' . ($post['category'] ? ' · ' . e($post['category']) : ''),
    'title' => e($post['title']),
    'lead' => $post['excerpt'],
]);
?>
<section class="section-sm">
  <div class="container layout-side">
    <div>
      <?php if ($post['cover']): ?><div class="article-cover"><img src="<?= e(upload_url($post['cover'])) ?>" srcset="<?= e(\App\Core\Uploader::srcset($post['cover'])) ?>" sizes="(max-width:1024px) 100vw, 860px" alt="<?= e($post['title']) ?>" width="1600" height="900" fetchpriority="high"></div><?php endif; ?>
      <article class="prose"><?= $body ?></article>
      <?php if ($faq): ?><h2 style="margin-top:56px">Întrebări frecvente</h2><?= View::partial('site/partials/faq', ['faq' => $faq]) ?><?php endif; ?>
      <div class="side-card" style="margin-top:48px;display:flex;gap:16px;align-items:center">
        <span class="avatar" style="width:52px;height:52px;flex:none"><?= e(mb_strtoupper(mb_substr($author, 0, 1))) ?></span>
        <div><strong><?= e($author) ?></strong><p class="muted" style="margin:0;font-size:15px">Echipa <?= e(setting('brand_name')) ?> – IT, securitate, marketing și AI pentru firme din Mureș, Bistrița-Năsăud și Alba.</p></div>
      </div>
    </div>
    <aside class="sticky">
      <?php if (count($toc) > 1): ?>
      <div class="side-card"><h3>Cuprins</h3><ul class="toc"><?php foreach ($toc as $t): ?><li class="l<?= $t['level'] ?>"><a href="#<?= e($t['id']) ?>"><?= e($t['text']) ?></a></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <div class="side-card">
        <h3>Ai nevoie de ajutor?</h3>
        <p class="muted" style="font-size:15px;margin:0">Discută gratuit cu un specialist VITIM despre situația ta.</p>
        <a class="btn btn-primary btn-block" href="<?= e(url('/contact')) ?>">Cere o consultanță</a>
        <a class="btn btn-ghost btn-block" href="<?= e(phone_href((string)setting('phone'))) ?>" data-loc="post-side"><?= icon('phone') ?> <?= e(setting('phone')) ?></a>
      </div>
    </aside>
  </div>
</section>
<?php if ($related): ?>
<section class="section bg-alt">
  <div class="container">
    <div class="section-head" data-reveal><span class="eyebrow">Mai citește</span><h2>Articole similare</h2></div>
    <div class="posts"><?php foreach ($related as $p): ?><?= View::partial('site/partials/post_card', ['p' => $p]) ?><?php endforeach; ?></div>
  </div>
</section>
<?php endif; ?>
