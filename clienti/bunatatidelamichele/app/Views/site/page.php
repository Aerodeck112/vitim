<?php use App\Core\View; ?>
<section class="page-hero"><div class="container narrow"><?= View::partial('site/partials/crumbs', ['seo' => $seo]) ?><h1><?= e($p['title']) ?></h1><?php if ($p['subtitle']): ?><p><?= e(\App\Core\Site::companyVars((string)$p['subtitle'])) ?></p><?php endif; ?></div></section>
<section class="section-sm" style="padding-bottom:90px"><div class="container narrow"><article class="prose"><?= $body ?></article><p class="small muted" style="margin-top:40px">Ultima actualizare: <?= e(ro_date($p['updated_at'])) ?></p></div></section>
