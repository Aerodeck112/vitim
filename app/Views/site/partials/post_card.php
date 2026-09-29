<article class="post-card" data-reveal>
  <?php if (!empty($p['cover'])): ?>
  <div class="cover"><img src="<?= e(upload_url($p['cover'])) ?>" srcset="<?= e(\App\Core\Uploader::srcset($p['cover'])) ?>" sizes="(max-width:720px) 100vw, 400px" alt="" loading="lazy" width="800" height="450"></div>
  <?php else: ?>
  <div class="cover ph"><?= icon('pen') ?></div>
  <?php endif; ?>
  <div class="body">
    <div class="post-meta"><time datetime="<?= e(substr((string)$p['published_at'], 0, 10)) ?>"><?= e(ro_date($p['published_at'])) ?></time><span>· <?= reading_time((string)($p['body'] ?? '')) ?> min</span></div>
    <h3><a href="<?= e(url('/blog/' . $p['slug'])) ?>" class="card-link-inline"><?= e($p['title']) ?></a></h3>
    <p><?= e($p['excerpt'] ?: excerpt((string)($p['body'] ?? ''), 140)) ?></p>
  </div>
  <a class="card-link" href="<?= e(url('/blog/' . $p['slug'])) ?>" aria-hidden="true" tabindex="-1"></a>
</article>
