<?php
/**
 * Captura reală a site-ului unui proiect: fereastră de browser (desktop) + telefon suprapus.
 * $p = rând din `projects`; $size = 'sm' (carduri) sau 'lg' (pagina proiectului); $eager = imagine prioritară.
 */
use App\Core\Uploader;

if (empty($p['cover'])) {
    return;
}
$size ??= 'sm';
$alt = 'Site-ul ' . ($p['site'] ?: $p['client']) . ' realizat sau administrat de VITIM';
$load = !empty($eager) ? 'fetchpriority="high"' : 'loading="lazy"';
?>
<figure class="showcase showcase-<?= e($size) ?>">
  <div class="sc-browser">
    <div class="sc-bar" aria-hidden="true"><i></i><i></i><i></i><span><?= e($p['site'] ?: '') ?></span></div>
    <img src="<?= e(upload_url($p['cover'])) ?>" srcset="<?= e(Uploader::srcset($p['cover'])) ?>" sizes="<?= $size === 'lg' ? '(max-width:1024px) 100vw, 900px' : '(max-width:720px) 100vw, 620px' ?>" alt="<?= e($alt . ', pe calculator') ?>" width="1440" height="900" <?= $load ?> decoding="async">
  </div>
  <?php if (!empty($p['cover_mobile'])): ?>
  <div class="sc-phone"><img src="<?= e(upload_url($p['cover_mobile'])) ?>" alt="<?= e($alt . ', pe telefon') ?>" width="585" height="1266" loading="lazy" decoding="async"></div>
  <?php endif; ?>
</figure>
