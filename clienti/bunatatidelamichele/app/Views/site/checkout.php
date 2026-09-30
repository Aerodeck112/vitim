<?php
use App\Core\Shop;
use App\Core\Site;

/** @var array $t @var array $v @var array $errors */
$err = fn(string $k) => isset($errors[$k]) ? '<span class="err">' . e($errors[$k]) . '</span>' : '';
$cls = fn(string $k) => isset($errors[$k]) ? ' has-err' : '';
$val = fn(string $k) => e($v[$k] ?? '');
$general = array_filter($errors, fn($k) => is_int($k), ARRAY_FILTER_USE_KEY);
$countySelect = function (string $name, string $id) use ($counties, $v, $cls, $err) {
    $o = '<div class="field' . $cls($name) . '"><label for="' . $id . '">Județ <span class="req">*</span></label><select id="' . $id . '" name="' . $name . '" autocomplete="address-level1"><option value="">Alege județul</option>';
    foreach ($counties as $c) {
        $o .= '<option' . (($v[$name] ?? '') === $c ? ' selected' : '') . '>' . e($c) . '</option>';
    }
    return $o . '</select>' . $err($name) . '</div>';
};
$cfg = [
    'subtotal' => $t['subtotal'], 'discount' => $t['discount'],
    'freeOver' => Shop::freeShippingOver(), 'couponFreeShip' => (bool)($t['coupon']['free_shipping'] ?? false),
    'ship' => array_map(fn($m) => $m['cost'], $shipping), 'fees' => array_map(fn($m) => $m['fee'], $payments),
];
?>
<section class="page-hero" style="padding-bottom:22px"><div class="container"><h1>Finalizare comandă</h1><p>Mai ai doar un pas. Datele tale sunt transmise securizat.</p></div></section>
<div class="container">
  <?php if ($errors): ?>
  <div class="alert alert-err" role="alert"><strong>Te rugăm să verifici datele comenzii:</strong><ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <?php if (!$payments): ?><div class="alert alert-warn">Momentan nu este activă nicio metodă de plată. Te rugăm să ne scrii la <a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a>.</div><?php endif; ?>
  <form class="checkout-grid" method="post" action="<?= e(url('/finalizare')) ?>" data-checkout='<?= e(json_encode($cfg)) ?>' novalidate>
    <input type="hidden" name="_t" value="<?= e($token) ?>"><input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div>
      <div class="card">
        <h2><span class="n">1</span> Date de contact</h2>
        <div class="row">
          <div class="field<?= $cls('email') ?>"><label for="c-email">Email <span class="req">*</span></label><input id="c-email" name="email" type="email" autocomplete="email" value="<?= $val('email') ?>" required><?= $err('email') ?><span class="hint">Aici primești confirmarea și numărul de urmărire.</span></div>
          <div class="field<?= $cls('phone') ?>"><label for="c-phone">Telefon <span class="req">*</span></label><input id="c-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" placeholder="07xx xxx xxx" value="<?= $val('phone') ?>" required><?= $err('phone') ?><span class="hint">Pentru curier.</span></div>
        </div>
      </div>

      <div class="card">
        <h2><span class="n">2</span> Date de facturare și livrare</h2>
        <div class="seg" role="radiogroup" aria-label="Tip client">
          <label><input type="radio" name="customer_type" value="pf"<?= ($v['customer_type'] ?? 'pf') !== 'pj' ? ' checked' : '' ?> data-ctype><span>Persoană fizică</span></label>
          <label><input type="radio" name="customer_type" value="pj"<?= ($v['customer_type'] ?? '') === 'pj' ? ' checked' : '' ?> data-ctype><span>Firmă</span></label>
        </div>
        <div style="display:grid;gap:16px;margin-top:14px">
          <div class="row">
            <div class="field<?= $cls('first_name') ?>"><label for="c-fn">Prenume <span class="req">*</span></label><input id="c-fn" name="first_name" autocomplete="given-name" value="<?= $val('first_name') ?>" required><?= $err('first_name') ?></div>
            <div class="field<?= $cls('last_name') ?>"><label for="c-ln">Nume <span class="req">*</span></label><input id="c-ln" name="last_name" autocomplete="family-name" value="<?= $val('last_name') ?>" required><?= $err('last_name') ?></div>
          </div>
          <div class="row" data-company<?= ($v['customer_type'] ?? '') === 'pj' ? '' : ' hidden' ?>>
            <div class="field<?= $cls('company') ?>"><label for="c-co">Denumire firmă <span class="req">*</span></label><input id="c-co" name="company" autocomplete="organization" value="<?= $val('company') ?>"><?= $err('company') ?></div>
            <div class="row" style="gap:12px">
              <div class="field<?= $cls('cui') ?>"><label for="c-cui">CUI <span class="req">*</span></label><input id="c-cui" name="cui" value="<?= $val('cui') ?>" placeholder="RO12345678"><?= $err('cui') ?></div>
              <div class="field"><label for="c-rc">Nr. Reg. Com.</label><input id="c-rc" name="reg_com" value="<?= $val('reg_com') ?>" placeholder="J00/000/2020"></div>
            </div>
          </div>
          <div class="field<?= $cls('billing_address') ?>"><label for="c-addr">Adresă (stradă, număr, bloc, scară, apartament) <span class="req">*</span></label><input id="c-addr" name="billing_address" autocomplete="street-address" value="<?= $val('billing_address') ?>" required><?= $err('billing_address') ?></div>
          <div class="row3">
            <div class="field<?= $cls('billing_city') ?>"><label for="c-city">Localitate <span class="req">*</span></label><input id="c-city" name="billing_city" autocomplete="address-level2" value="<?= $val('billing_city') ?>" required><?= $err('billing_city') ?></div>
            <?= $countySelect('billing_county', 'c-county') ?>
            <div class="field<?= $cls('billing_postcode') ?>"><label for="c-zip">Cod poștal</label><input id="c-zip" name="billing_postcode" autocomplete="postal-code" inputmode="numeric" maxlength="6" value="<?= $val('billing_postcode') ?>"><?= $err('billing_postcode') ?></div>
          </div>
          <label class="check"><input type="checkbox" name="ship_same" value="1"<?= !empty($v['ship_same']) ? ' checked' : '' ?> data-ship-same> <span>Livrez la aceeași adresă</span></label>
          <div data-ship-box style="display:grid;gap:16px"<?= !empty($v['ship_same']) ? ' hidden' : '' ?>>
            <div class="row">
              <div class="field<?= $cls('shipping_name') ?>"><label for="s-name">Nume destinatar <span class="req">*</span></label><input id="s-name" name="shipping_name" autocomplete="shipping name" value="<?= $val('shipping_name') ?>"><?= $err('shipping_name') ?></div>
              <div class="field"><label for="s-phone">Telefon destinatar</label><input id="s-phone" name="shipping_phone" type="tel" autocomplete="shipping tel" value="<?= $val('shipping_phone') ?>"></div>
            </div>
            <div class="field<?= $cls('shipping_address') ?>"><label for="s-addr">Adresă de livrare <span class="req">*</span></label><input id="s-addr" name="shipping_address" autocomplete="shipping street-address" value="<?= $val('shipping_address') ?>"><?= $err('shipping_address') ?></div>
            <div class="row3">
              <div class="field<?= $cls('shipping_city') ?>"><label for="s-city">Localitate <span class="req">*</span></label><input id="s-city" name="shipping_city" autocomplete="shipping address-level2" value="<?= $val('shipping_city') ?>"><?= $err('shipping_city') ?></div>
              <?= $countySelect('shipping_county', 's-county') ?>
              <div class="field<?= $cls('shipping_postcode') ?>"><label for="s-zip">Cod poștal</label><input id="s-zip" name="shipping_postcode" autocomplete="shipping postal-code" inputmode="numeric" maxlength="6" value="<?= $val('shipping_postcode') ?>"><?= $err('shipping_postcode') ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <h2><span class="n">3</span> Livrare</h2>
        <div class="opts">
          <?php foreach ($shipping as $k => $m): ?>
          <label class="opt"><input type="radio" name="shipping_method" value="<?= e($k) ?>"<?= ($v['shipping_method'] ?? 'curier') === $k ? ' checked' : '' ?> data-recalc><div><strong><?= e($m['label']) ?></strong><?php if ($m['text']): ?><small><?= e($k === 'curier' ? 'Livrare în ' . $m['text'] : $m['text']) ?></small><?php endif; ?></div><span class="cost"><?= $m['cost'] > 0 ? e(Shop::money($m['cost'])) : 'Gratuit' ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <h2><span class="n">4</span> Plată</h2>
        <div class="opts<?= isset($errors['payment_method']) ? ' has-err' : '' ?>">
          <?php foreach ($payments as $k => $m): ?>
          <label class="opt"><input type="radio" name="payment_method" value="<?= e($k) ?>"<?= ($v['payment_method'] ?? '') === $k ? ' checked' : '' ?> data-recalc>
            <div><strong><?= e($m['label']) ?></strong><small><?= e($m['text']) ?></small>
              <?php if ($k === 'card'): ?><div class="paylogos" style="margin-top:8px"><span class="pl bt">BT <b>iPay</b></span><span class="pl visa">VISA</span><span class="pl mc"><i></i><i></i></span><span class="pl maestro"><i></i><i></i></span><span class="pl secure"><?= icon('lock') ?>3D Secure</span></div><?php endif; ?>
            </div>
            <span class="cost"><?= $m['fee'] > 0 ? '+' . e(Shop::money($m['fee'])) : icon($k === 'card' ? 'card' : ($k === 'ramburs' ? 'cash' : 'receipt')) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
        <?= $err('payment_method') ?>
      </div>

      <div class="card">
        <div class="field"><label for="c-note">Mențiuni pentru comandă (opțional)</label><textarea id="c-note" name="note" maxlength="1000" placeholder="Ex: interval orar preferat pentru livrare, interfon…"><?= $val('note') ?></textarea></div>
        <?php if ($cn = setting('checkout_note')): ?><div class="alert alert-info" style="margin:16px 0 0"><?= e($cn) ?></div><?php endif; ?>
      </div>
    </div>

    <aside class="summary">
      <div class="card">
        <h2 style="font-size:22px">Comanda ta</h2>
        <div class="sum-items">
          <?php foreach ($t['lines'] as $l): if (!$l['available']) continue; $p = $l['product']; ?>
          <div class="sum-item"><div class="th"><?= Site::img(Shop::image($p), (string)$p['name'], '58px') ?><span class="q"><?= (int)$l['qty'] ?></span></div><div><?= e($p['name']) ?></div><strong><?= e(Shop::money($l['total'])) ?></strong></div>
          <?php endforeach; ?>
        </div>
        <div class="line"><span>Subtotal</span><span><?= e(Shop::money($t['subtotal'])) ?></span></div>
        <?php if ($t['discount'] > 0): ?><div class="line discount"><span>Reducere (<?= e($t['coupon']['code']) ?>)</span><span>−<?= e(Shop::money($t['discount'])) ?></span></div><?php endif; ?>
        <div class="line"><span>Livrare</span><span data-sum-ship><?= $t['shipping'] > 0 ? e(Shop::money($t['shipping'])) : 'Gratuit' ?></span></div>
        <div class="line" data-sum-fee-row<?= $t['payment_fee'] > 0 ? '' : ' hidden' ?>><span>Taxă ramburs</span><span data-sum-fee><?= e(Shop::money($t['payment_fee'])) ?></span></div>
        <div class="line total"><span>Total</span><span data-sum-total><?= e(Shop::money($t['total'])) ?></span></div>
        <p class="small muted" style="margin:4px 0 16px"><?= e(setting('prices_note')) ?></p>
        <label class="check<?= isset($errors['terms']) ? ' has-err' : '' ?>" style="margin-bottom:16px"><input type="checkbox" name="terms" value="1" required> <span>Am citit și sunt de acord cu <a href="<?= e(url('/termeni-si-conditii')) ?>" target="_blank">Termenii și condițiile</a>, <a href="<?= e(url('/politica-de-retur')) ?>" target="_blank">Politica de retur</a> și <a href="<?= e(url('/politica-de-confidentialitate')) ?>" target="_blank">Politica de confidențialitate</a>. <span class="req">*</span></span></label>
        <button class="btn btn-block" type="submit" data-place<?= $payments ? '' : ' disabled' ?>><?= icon('lock') ?> <span data-place-label>Plasează comanda</span></button>
        <p class="small muted center" style="margin:12px 0 0" data-card-note hidden>Vei fi redirecționat în pagina securizată a Băncii Transilvania pentru plată.</p>
        <div class="secure-note"><?= icon('shield') ?> Conexiune securizată · datele cardului nu ajung la noi</div>
      </div>
      <p class="small muted center" style="margin-top:14px"><a href="<?= e(url('/cos')) ?>">← Înapoi la coș</a></p>
    </aside>
  </form>
</div>
