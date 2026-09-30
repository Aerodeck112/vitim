<?php
use App\Core\Shop;
use App\Core\Site;
use App\Core\View;

/** @var array $t */
$lines = $t['lines'];
?>
<section class="page-hero" style="padding-bottom:26px"><div class="container"><h1>Coșul tău</h1><?php if ($lines): ?><p><?= (int)$t['count'] ?> <?= $t['count'] === 1 ? 'produs' : 'produse' ?> · livrare în <?= e(setting('delivery_time')) ?></p><?php endif; ?></div></section>
<div class="container" style="padding-bottom:70px">
  <?php if ($error !== ''): ?><div class="alert alert-err"><?= e($error) ?></div><?php endif; ?>
  <?php if (str_input('cupon') === 'ok' && $t['coupon']): ?><div class="alert alert-ok">Codul <strong><?= e($t['coupon']['code']) ?></strong> a fost aplicat.</div><?php endif; ?>
  <?php if ($t['coupon_error']): ?><div class="alert alert-warn"><?= e($t['coupon_error']) ?></div><?php endif; ?>
  <?php if (!$lines): ?>
  <div class="card empty-cart" style="padding:60px 20px">
    <?= icon('bag') ?>
    <h2 style="font-size:26px">Coșul este gol</h2>
    <p>Descoperă cafeaua noastră prăjită la foc de lemn, cialdele și monodozele Saka.</p>
    <a class="btn" href="<?= e(url('/produse')) ?>">Vezi produsele <?= icon('arrow-right') ?></a>
  </div>
  <?php else: ?>
  <div class="checkout-grid">
    <div>
      <form method="post" action="<?= e(url('/cos/actualizeaza')) ?>" data-cart-form>
        <table class="cart-table">
          <thead><tr><th>Produs</th><th class="c-price">Preț</th><th>Cantitate</th><th style="text-align:right">Total</th></tr></thead>
          <tbody>
          <?php foreach ($lines as $l): $p = $l['product']; ?>
            <tr>
              <td>
                <div class="prod">
                  <a href="<?= e(url(Shop::url($p))) ?>" tabindex="-1"><?= Site::img(Shop::image($p), (string)$p['name'], '86px') ?></a>
                  <div>
                    <a href="<?= e(url(Shop::url($p))) ?>"><?= e($p['name']) ?></a>
                    <?php if ($p['price_note']): ?><div class="pnote"><?= e($p['price_note']) ?></div><?php endif; ?>
                    <?php if (!$l['available']): ?><div class="small" style="color:var(--err)">Nu mai este disponibil – nu intră în total.</div><?php elseif ($l['qty'] !== $l['requested']): ?><div class="small" style="color:var(--warn)">Cantitate ajustată la stocul disponibil.</div><?php endif; ?>
                    <button class="rm" type="submit" name="remove" value="<?= (int)$l['id'] ?>"><?= icon('trash', 'ico') ?> Elimină</button>
                  </div>
                </div>
              </td>
              <td class="c-price"><?= e(Shop::money($l['price'])) ?></td>
              <td class="c-qty"><div class="qty sm" data-qty><button type="button" data-dec aria-label="Scade"><?= icon('minus') ?></button><input name="qty[<?= (int)$l['id'] ?>]" type="number" inputmode="numeric" value="<?= (int)$l['qty'] ?>" min="0" step="<?= max(1, (int)$p['qty_step']) ?>" aria-label="Cantitate <?= e($p['name']) ?>" data-autosubmit><button type="button" data-inc aria-label="Crește"><?= icon('plus') ?></button></div></td>
              <td class="c-total" style="text-align:right;font-weight:700"><?= e(Shop::money($l['total'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <noscript><p><button class="btn btn-ghost btn-sm" type="submit">Actualizează coșul</button></p></noscript>
      </form>
      <?php if ($note !== ''): ?><div class="alert alert-info" style="margin-top:20px"><?= e($note) ?></div><?php endif; ?>
      <p style="margin-top:22px"><a class="link-arrow" href="<?= e(url('/produse')) ?>"><?= icon('arrow-right') ?> Continuă cumpărăturile</a></p>
    </div>
    <aside class="summary">
      <div class="card">
        <h2 style="font-size:22px">Sumar comandă</h2>
        <div class="line"><span>Subtotal</span><span><?= e(Shop::money($t['subtotal'])) ?></span></div>
        <?php if ($t['discount'] > 0): ?><div class="line discount"><span>Reducere (<?= e($t['coupon']['code']) ?>) <form method="post" action="<?= e(url('/cos/cupon')) ?>" style="display:inline"><button class="rm" name="remove" value="1" style="border:0;background:none;color:var(--text-3);cursor:pointer;text-decoration:underline;font-size:13px">elimină</button></form></span><span>−<?= e(Shop::money($t['discount'])) ?></span></div><?php endif; ?>
        <div class="line"><span><?= e($t['shipping_label']) ?></span><span><?= $t['shipping'] > 0 ? e(Shop::money($t['shipping'])) : 'Gratuit' ?></span></div>
        <?php if ($t['free_left'] > 0): ?><div class="small muted">Mai adaugă <?= e(Shop::money($t['free_left'])) ?> pentru livrare gratuită.<div class="progress"><i style="width:<?= (int)min(100, 100 - $t['free_left'] / max(1, Shop::freeShippingOver()) * 100) ?>%"></i></div></div><?php endif; ?>
        <div class="line total"><span>Total</span><span><?= e(Shop::money($t['total'])) ?></span></div>
        <a class="btn btn-block" style="margin-top:14px" href="<?= e(url('/finalizare')) ?>"><?= icon('lock') ?> Finalizează comanda</a>
        <?php if (!$t['coupon']): ?>
        <form class="coupon" method="post" action="<?= e(url('/cos/cupon')) ?>"><label class="sr" for="cp">Cod de reducere</label><input class="input" id="cp" name="code" placeholder="Cod de reducere" maxlength="40"><button class="btn btn-ghost btn-sm" type="submit" style="min-height:48px">Aplică</button></form>
        <?php endif; ?>
        <div class="secure-note"><?= icon('shield') ?> Plată securizată prin BT iPay sau ramburs</div>
        <div class="paylogos" style="justify-content:center;margin-top:10px"><span class="pl bt">BT <b>iPay</b></span><span class="pl visa">VISA</span><span class="pl mc"><i></i><i></i></span><span class="pl maestro"><i></i><i></i></span></div>
      </div>
    </aside>
  </div>
  <?php endif; ?>
</div>
