<?php
/** @var array $crumbs [[label, path], ...] ultimul = pagina curentă */
?>
<section class="page-hero">
  <div class="hero-bg" aria-hidden="true"><div class="grid"></div><div class="blob b1"></div><div class="blob b2"></div></div>
  <div class="container">
    <nav aria-label="Breadcrumb">
      <ol class="breadcrumb">
        <li><a href="<?= e(url('/')) ?>">Acasă</a></li>
        <?php foreach ($crumbs as $i => [$label, $path]): ?>
        <li><?= icon('chevron-right') ?></li>
        <li><?php if ($i < count($crumbs) - 1): ?><a href="<?= e(url($path)) ?>"><?= e($label) ?></a><?php else: ?><span aria-current="page"><?= e($label) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
      </ol>
    </nav>
    <?php if (!empty($eyebrow)): ?><span class="pill pill-plain" style="margin-top:22px"><?= $eyebrow ?></span><?php endif; ?>
    <h1><?= $title ?></h1>
    <?php if (!empty($lead)): ?><p class="lead"><?= e($lead) ?></p><?php endif; ?>
    <?php if (!empty($actions)): ?><div class="hero-ctas" style="margin:0"><?= $actions ?></div><?php endif; ?>
  </div>
</section>
