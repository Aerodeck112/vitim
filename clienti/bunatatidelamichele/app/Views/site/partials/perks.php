<?php use App\Core\Shop; ?>
<section class="perks" aria-label="Avantaje">
  <div class="container">
    <div class="perk"><span class="ic"><?= icon('truck') ?></span><div><strong>Livrare în <?= e(setting('delivery_time')) ?></strong><span><?= (float)setting('shipping_cost') > 0 ? 'Curier rapid, ' . e(Shop::moneyShort(setting('shipping_cost'))) : 'Curier rapid, gratuit' ?></span></div></div>
    <div class="perk"><span class="ic"><?= icon('card') ?></span><div><strong>Plată sigură</strong><span>Card (BT iPay) sau ramburs</span></div></div>
    <div class="perk"><span class="ic"><?= icon('flame') ?></span><div><strong>Prăjită la foc de lemn</strong><span>În loturi mici, pentru aromă</span></div></div>
    <div class="perk"><span class="ic"><?= icon('return') ?></span><div><strong>Retur în <?= (int)setting('return_days', '14') ?> zile</strong><span>Produse sigilate</span></div></div>
  </div>
</section>
