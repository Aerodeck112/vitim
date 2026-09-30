<?php
use App\Core\Shop;
use App\Core\Site;

/** @var array $p */
$sale = Shop::onSale($p);
$in = Shop::inStock($p);
$img = Shop::image($p);
?>
<article class="pcard reveal">
  <a class="pimg" href="<?= e(url(Shop::url($p))) ?>" tabindex="-1" aria-hidden="true">
    <div class="badges">
      <?php if (!$in): ?><span class="badge out">Stoc epuizat</span><?php elseif ($sale): ?><span class="badge sale">-<?= Shop::discountPercent($p) ?>%</span><?php elseif (!empty($p['badge'])): ?><span class="badge"><?= e($p['badge']) ?></span><?php elseif (Shop::isExclusive($p)): ?><span class="badge badge-excl"><?= e(setting('exclusive_label', 'Import exclusiv')) ?></span><?php endif; ?>
    </div>
    <?= Site::img($img, (string)$p['name'], '(max-width:720px) 50vw, (max-width:1100px) 33vw, 300px', '', $lazy ?? true) ?>
  </a>
  <div class="pbody">
    <?php if (!empty($p['brand']) || !empty($p['category_name'])): ?><span class="pcat"><?= e(implode(' · ', array_filter([$p['brand'] ?? '', $p['category_name'] ?? '']))) ?></span><?php endif; ?>
    <h3><a href="<?= e(url(Shop::url($p))) ?>"><?= e($p['name']) ?></a></h3>
    <div class="pfoot">
      <div>
        <div class="price<?= $sale ? ' sale' : '' ?>"><?php if ($sale): ?><del><?= e(Shop::money($p['price'])) ?></del><ins><?= e(Shop::money($p['sale_price'])) ?></ins><?php else: ?><?= e(Shop::money($p['price'])) ?><?php endif; ?></div>
        <?php if (!empty($p['price_note'])): ?><span class="pnote"><?= e(explode('·', (string)$p['price_note'])[0]) ?></span><?php endif; ?>
      </div>
      <form method="post" action="<?= e(url('/cos/adauga')) ?>" data-add>
        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="qty" value="<?= max(1, (int)$p['min_qty']) ?>">
        <button class="add-btn" type="submit" aria-label="Adaugă în coș: <?= e($p['name']) ?>"<?= $in ? '' : ' disabled' ?>><?= icon('bag') ?></button>
      </form>
    </div>
  </div>
</article>
