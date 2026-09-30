<?php /** @var \App\Core\Seo $seo */ if (!empty($seo->breadcrumbs)): ?>
<nav aria-label="Breadcrumb"><ol class="crumbs"><li><a href="<?= e(url('/')) ?>">Acasă</a></li><?php $n = count($seo->breadcrumbs); foreach ($seo->breadcrumbs as $i => [$name, $path]): ?><li><?php if ($i < $n - 1): ?><a href="<?= e(url($path)) ?>"><?= e($name) ?></a><?php else: ?><span aria-current="page"><?= e($name) ?></span><?php endif; ?></li><?php endforeach; ?></ol></nav>
<?php endif; ?>
