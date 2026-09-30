<?php
use App\Core\Shop;
use App\Core\Site;
use App\Core\View;

/** @var array $p */
$imgs = Shop::images($p);
$sale = Shop::onSale($p);
$in = Shop::inStock($p);
[$stCls, $stLabel] = Shop::stockLabel($p);
$attrs = json_list($p['attributes']);
$faq = json_list($p['faq']);
$avg = $reviews ? round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1) : 0;
$min = max(1, (int)$p['min_qty']);
$step = max(1, (int)$p['qty_step']);
$ship = (float)setting('shipping_cost');
$free = Shop::freeShippingOver();
$stars = function (float $v): string {
    $o = '<span class="stars" aria-hidden="true">';
    for ($i = 1; $i <= 5; $i++) {
        $o .= icon('star', 'ico' . ($i <= round($v) ? '' : ' off'));
    }
    return $o . '</span>';
};
?>
<div class="container">
  <div style="padding-top:26px"><?= View::partial('site/partials/crumbs', ['seo' => $seo]) ?></div>
  <div class="product">
    <div class="gallery" data-gallery>
      <div class="main" data-zoom>
        <div class="badges"><?php if (!$in): ?><span class="badge out">Stoc epuizat</span><?php elseif ($sale): ?><span class="badge sale">-<?= Shop::discountPercent($p) ?>%</span><?php elseif ($p['badge']): ?><span class="badge"><?= e($p['badge']) ?></span><?php endif; ?></div>
        <?= Site::img($imgs[0] ?? '', (string)$p['name'], '(max-width:960px) 100vw, 620px', '', false, ['data-main' => '1']) ?>
      </div>
      <?php if (count($imgs) > 1): ?>
      <div class="thumbs">
        <?php foreach ($imgs as $i => $im): ?><button type="button" data-src="<?= e(upload_url($im)) ?>" data-srcset="<?= e(\App\Core\Uploader::srcset($im)) ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?> aria-label="Imaginea <?= $i + 1 ?>"><?= Site::img($im, '', '84px') ?></button><?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="pinfo">
      <?php if ($p['brand']): ?><span class="brand"><?= e($p['brand']) ?></span><?php endif; ?>
      <h1><?= e($p['name']) ?></h1>
      <?php if ($reviews): ?><a class="rating" href="#recenzii"><?= $stars($avg) ?> <?= number_format($avg, 1, ',', '') ?> · <?= count($reviews) ?> <?= count($reviews) === 1 ? 'recenzie' : 'recenzii' ?></a><?php endif; ?>
      <div style="margin-top:14px">
        <div class="price<?= $sale ? ' sale' : '' ?>"><?php if ($sale): ?><del><?= e(Shop::money($p['price'])) ?></del><ins><?= e(Shop::money($p['sale_price'])) ?></ins><?php else: ?><?= e(Shop::money($p['price'])) ?><?php endif; ?></div>
        <?php if ($p['price_note']): ?><span class="pnote" style="font-size:14.5px"><?= e($p['price_note']) ?></span><?php endif; ?>
      </div>
      <?php if (trim(strip_tags((string)$p['short_description'])) !== ''): ?><div class="short"><?= $p['short_description'] ?></div><?php endif; ?>
      <?php if (Shop::isExclusive($p)): ?><div class="excl-note"><span class="ring"><?= icon('award') ?></span><div><strong><?= e(setting('exclusive_label', 'Import exclusiv')) ?> în România</strong>Cafeaua <?= e($p['brand']) ?> este adusă direct din Italia de Bunătăți de la Michele, unicul importator din țară.</div></div><?php endif; ?>
      <div class="stock <?= e($stCls) ?>"><?= e($stLabel) ?></div>

      <form method="post" action="<?= e(url('/cos/adauga')) ?>" data-add data-buy-form>
        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
        <div class="buy">
          <div class="qty" data-qty>
            <button type="button" data-dec aria-label="Scade cantitatea"><?= icon('minus') ?></button>
            <label class="sr" for="qty">Cantitate</label>
            <input id="qty" name="qty" type="number" inputmode="numeric" value="<?= $min ?>" min="<?= $min ?>" step="<?= $step ?>" max="<?= $p['manage_stock'] && $p['stock_status'] !== 'onbackorder' ? max($min, (int)$p['stock']) : 9999 ?>">
            <button type="button" data-inc aria-label="Crește cantitatea"><?= icon('plus') ?></button>
          </div>
          <button class="btn" type="submit"<?= $in ? '' : ' disabled' ?>><?= icon('bag') ?> Adaugă în coș</button>
        </div>
        <?php if ($in): ?><button class="btn btn-dark btn-block" type="submit" name="then" value="checkout" data-buy-now>Cumpără acum</button><?php endif; ?>
      </form>

      <div class="assure">
        <div><?= icon('truck') ?><span><strong><?= e(setting('shipping_label')) ?></strong> în <?= e(setting('delivery_time')) ?> · <?= $ship > 0 ? e(Shop::money($ship)) : 'gratuit' ?><?= $free > 0 ? ' · gratuit peste ' . e(Shop::moneyShort($free)) : '' ?></span></div>
        <div><?= icon('card') ?><span><strong>Plată sigură</strong> online cu cardul (BT iPay, 3D Secure) sau ramburs la livrare</span></div>
        <div><?= icon('return') ?><span><strong>Retur în <?= (int)setting('return_days', '14') ?> zile</strong> pentru produsele sigilate – <a href="<?= e(url('/politica-de-retur')) ?>">detalii</a></span></div>
      </div>
      <p class="meta-line"><?php if ($p['sku']): ?>Cod produs: <?= e($p['sku']) ?> · <?php endif; ?><?php if ($p['category_slug']): ?>Categorie: <a href="<?= e(url('/categorie/' . $p['category_slug'])) ?>"><?= e($p['category_name']) ?></a><?php endif; ?></p>
    </div>
  </div>

  <nav class="tabs" aria-label="Secțiuni produs">
    <a href="#descriere" class="on">Descriere</a>
    <?php if ($attrs): ?><a href="#specificatii">Specificații</a><?php endif; ?>
    <a href="#livrare">Livrare și retur</a>
    <?php if (setting('reviews_enabled') === '1'): ?><a href="#recenzii">Recenzii (<?= count($reviews) ?>)</a><?php endif; ?>
  </nav>

  <section id="descriere" class="prose narrow" style="padding-bottom:40px">
    <h2 class="sr">Descriere</h2>
    <?= trim(strip_tags((string)$p['description'])) !== '' ? $p['description'] : $p['short_description'] ?>
  </section>

  <?php if ($attrs): ?>
  <section id="specificatii" class="psection narrow">
    <h2 style="font-size:28px">Specificații</h2>
    <table class="specs"><tbody>
      <?php if ($p['brand']): ?><tr><th scope="row">Brand</th><td><?= e($p['brand']) ?></td></tr><?php endif; ?>
      <?php foreach ($attrs as $a): if (empty($a['name'])) continue; ?><tr><th scope="row"><?= e($a['name']) ?></th><td><?= e($a['value'] ?? '') ?></td></tr><?php endforeach; ?>
    </tbody></table>
  </section>
  <?php endif; ?>

  <section id="livrare" class="psection narrow">
    <h2 style="font-size:28px">Livrare și retur</h2>
    <div class="prose">
      <ul>
        <li><strong><?= e(setting('shipping_label')) ?></strong> în toată România: <?= $ship > 0 ? e(Shop::money($ship)) : 'gratuit' ?>, în <?= e(setting('delivery_time')) ?>.</li>
        <li>Plătești online cu cardul, prin BT iPay (Banca Transilvania), sau ramburs, la curier.</li>
        <li>Ai <?= (int)setting('return_days', '14') ?> zile să returnezi produsele sigilate. Citește <a href="<?= e(url('/politica-de-retur')) ?>">politica de retur</a>.</li>
      </ul>
    </div>
    <?php if ($faq): ?><h3 style="margin-top:30px">Întrebări frecvente</h3><?= View::partial('site/partials/faq', ['faq' => $faq]) ?><?php endif; ?>
  </section>

  <?php if (setting('reviews_enabled') === '1'): ?>
  <section id="recenzii" class="psection">
    <div class="row-between" style="flex-wrap:wrap;margin-bottom:22px"><h2 style="font-size:28px;margin:0">Recenzii<?= $reviews ? ' · ' . number_format($avg, 1, ',', '') . '/5' : '' ?></h2><button class="btn btn-ghost btn-sm" type="button" data-toggle="#review-form"><?= icon('pen') ?> Scrie o recenzie</button></div>
    <?php if ($reviews): ?>
    <div class="reviews">
      <?php foreach ($reviews as $r): ?>
      <article class="review"><?= $stars((float)$r['rating']) ?><div class="who"><?= e($r['name']) ?><?= $r['city'] ? ', ' . e($r['city']) : '' ?> <?php if ($r['verified']): ?><span class="ver">✓ cumpărător verificat</span><?php endif; ?></div><p><?= nl2br(e($r['text'])) ?></p></article>
      <?php endforeach; ?>
    </div>
    <?php else: ?><p class="muted">Încă nu există recenzii. Ai încercat produsul? Spune-ne părerea ta!</p><?php endif; ?>
    <form class="card" id="review-form" hidden data-ajax="<?= e(url('/api/recenzie')) ?>" style="margin-top:20px;max-width:720px">
      <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="_t" value=""><input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div class="row"><div class="field"><label for="rv-n">Nume</label><input id="rv-n" name="name" required maxlength="80"></div><div class="field"><label for="rv-c">Oraș (opțional)</label><input id="rv-c" name="city" maxlength="80"></div></div>
      <div class="row" style="margin-top:14px"><div class="field"><label for="rv-r">Notă</label><select id="rv-r" name="rating"><option value="5">★★★★★ – Excelent</option><option value="4">★★★★ – Foarte bun</option><option value="3">★★★ – Bun</option><option value="2">★★ – Slab</option><option value="1">★ – Nemulțumit</option></select></div><div class="field"><label for="rv-e">Email folosit la comandă (opțional)</label><input id="rv-e" name="email" type="email"><span class="hint">Nu îl publicăm; ne ajută să marcăm „cumpărător verificat”.</span></div></div>
      <div class="field" style="margin-top:14px"><label for="rv-t">Părerea ta</label><textarea id="rv-t" name="text" required minlength="10" maxlength="2000"></textarea></div>
      <div class="form-msg" role="status" style="margin-top:12px"></div>
      <button class="btn" type="submit" style="margin-top:8px">Trimite recenzia</button>
    </form>
  </section>
  <?php endif; ?>

  <?php if ($related): ?>
  <section class="psection" style="padding-bottom:80px">
    <h2 style="font-size:30px;margin-bottom:26px">Te-ar putea interesa și</h2>
    <div class="grid-products"><?php foreach ($related as $r): ?><?= View::partial('site/partials/product_card', ['p' => $r]) ?><?php endforeach; ?></div>
  </section>
  <?php endif; ?>
</div>

<?php if ($in): ?>
<div class="sticky-buy" data-sticky-buy>
  <div><div class="price<?= $sale ? ' sale' : '' ?>"><?= e(Shop::money(Shop::price($p))) ?></div><?php if ($p['price_note']): ?><span class="pnote"><?= e(explode('·', (string)$p['price_note'])[0]) ?></span><?php endif; ?></div>
  <button class="btn" type="button" data-sticky-add><?= icon('bag') ?> Adaugă în coș</button>
</div>
<?php endif; ?>
