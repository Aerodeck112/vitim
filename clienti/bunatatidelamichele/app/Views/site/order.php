<?php
use App\Core\BtIpay;
use App\Core\Orders;
use App\Core\Shop;

/** @var array $o */
$paid = in_array($o['payment_status'], ['platita', 'autorizata'], true);
$cancelled = in_array($o['status'], ['anulata', 'returnata'], true);
$waitingCard = $canPay;
$failed = $waitingCard && ($payError || $o['payment_status'] === 'esuata' || (int)$o['bt_status'] === 6);
$steps = [
    ['Comandă plasată', true],
    [$o['payment_method'] === 'ramburs' ? 'Confirmată' : 'Plată confirmată', $o['payment_method'] === 'ramburs' ? !in_array($o['status'], ['asteptare_plata'], true) && !$cancelled : $paid],
    ['Expediată', in_array($o['status'], ['expediata', 'livrata'], true)],
    ['Livrată', $o['status'] === 'livrata'],
];
?>
<div class="container narrow" style="padding-bottom:80px">
  <div class="order-hero">
    <?php if ($cancelled): ?>
      <div class="big fail"><?= icon('x') ?></div>
      <h1 style="font-size:36px">Comanda <?= e($o['number']) ?> este <?= e(mb_strtolower(Orders::statusLabel($o['status']))) ?></h1>
      <p class="muted">Dacă ai întrebări, scrie-ne la <a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a>.</p>
    <?php elseif ($failed): ?>
      <div class="big fail"><?= icon('card') ?></div>
      <h1 style="font-size:36px">Plata nu a fost finalizată</h1>
      <p class="muted"><?= e(BtIpay::actionMessage($o['bt_action_code'] !== null ? (int)$o['bt_action_code'] : null, (string)$o['bt_message'])) ?> Comanda <strong><?= e($o['number']) ?></strong> este păstrată – poți încerca din nou<?= $codAvailable ? ' sau poți alege plata ramburs' : '' ?>.</p>
    <?php elseif ($waitingCard): ?>
      <div class="big wait"><?= icon('clock') ?></div>
      <h1 style="font-size:36px">Comanda așteaptă plata</h1>
      <p class="muted">Comanda <strong><?= e($o['number']) ?></strong> a fost înregistrată. Finalizează plata cu cardul ca să o putem pregăti.</p>
    <?php else: ?>
      <div class="big"><?= icon('check') ?></div>
      <h1 style="font-size:36px"><?= $isNew ? 'Mulțumim, ' . e($o['first_name']) . '!' : 'Comanda ' . e($o['number']) ?></h1>
      <p class="muted"><?= $isNew ? 'Comanda <strong>' . e($o['number']) . '</strong> a fost înregistrată' . ($paid ? ' și plătită' : '') . '. Ți-am trimis confirmarea pe <strong>' . e($o['email']) . '</strong>.' : e(Orders::STATUSES[$o['status']][2] ?? '') ?></p>
    <?php endif; ?>

    <?php if ($waitingCard): ?>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:22px">
      <?php if ($cardAvailable): ?><form method="post" action="<?= e(url('/comanda/' . $o['token'] . '/plateste')) ?>"><button class="btn" type="submit"><?= icon('card') ?> <?= $failed ? 'Încearcă din nou plata' : 'Plătește cu cardul' ?> · <?= e(Shop::money($o['total'])) ?></button></form><?php endif; ?>
      <?php if ($codAvailable): ?><form method="post" action="<?= e(url('/comanda/' . $o['token'] . '/plateste')) ?>"><input type="hidden" name="metoda" value="ramburs"><button class="btn btn-ghost" type="submit"><?= icon('cash') ?> Plătesc ramburs la livrare</button></form><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if (!$cancelled): ?>
  <ol class="timeline" aria-label="Stadiul comenzii"><?php foreach ($steps as [$l, $d]): ?><li class="<?= $d ? 'done' : '' ?>"><?= e($l) ?></li><?php endforeach; ?></ol>
  <?php endif; ?>

  <?php if ($o['status'] === 'expediata' && $o['awb']): ?>
  <div class="alert alert-info" style="margin-top:20px"><?= icon('truck') ?> Coletul este la <strong><?= e($o['courier'] ?: 'curier') ?></strong>, AWB <strong><?= e($o['awb']) ?></strong><?php if ($o['tracking_url']): ?> – <a href="<?= e($o['tracking_url']) ?>" target="_blank" rel="noopener">urmărește coletul</a><?php endif; ?>.</div>
  <?php endif; ?>

  <?php if ($o['payment_method'] === 'transfer' && !$paid && !$cancelled): ?>
  <div class="card" style="margin-top:24px"><h2 style="font-size:20px">Date pentru plata prin transfer bancar</h2><p style="margin:0">Beneficiar: <strong><?= e(setting('company_name')) ?></strong><br>CUI: <?= e(setting('company_cui')) ?><br>IBAN: <strong><?= e(setting('company_iban')) ?></strong><br>Banca: <?= e(setting('company_bank')) ?><br>Suma: <strong><?= e(Shop::money($o['total'])) ?></strong><br>Detalii plată: <strong>Comanda <?= e($o['number']) ?></strong></p></div>
  <?php endif; ?>

  <div class="card" style="margin-top:24px">
    <div class="row-between" style="flex-wrap:wrap;margin-bottom:10px"><h2 style="font-size:22px;margin:0">Comanda <?= e($o['number']) ?></h2><span class="muted small"><?= e(ro_date($o['created_at'], true)) ?></span></div>
    <div class="sum-items">
      <?php foreach ($items as $it): ?>
      <div class="sum-item"><div class="th"><?php if ($it['image']): ?><img src="<?= e(upload_url($it['image'])) ?>" alt="" width="58" height="58" loading="lazy"><?php endif; ?><span class="q"><?= (int)$it['qty'] ?></span></div><div><?= e($it['name']) ?><div class="small muted"><?= (int)$it['qty'] ?> × <?= e(Shop::money($it['price'])) ?></div></div><strong><?= e(Shop::money($it['total'])) ?></strong></div>
      <?php endforeach; ?>
    </div>
    <div class="summary" style="position:static">
      <div class="line"><span>Subtotal</span><span><?= e(Shop::money($o['subtotal'])) ?></span></div>
      <?php if ((float)$o['discount'] > 0): ?><div class="line discount"><span>Reducere<?= $o['coupon_code'] ? ' (' . e($o['coupon_code']) . ')' : '' ?></span><span>−<?= e(Shop::money($o['discount'])) ?></span></div><?php endif; ?>
      <div class="line"><span>Livrare</span><span><?= (float)$o['shipping_cost'] > 0 ? e(Shop::money($o['shipping_cost'])) : 'Gratuit' ?></span></div>
      <?php if ((float)$o['payment_fee'] > 0): ?><div class="line"><span>Taxă ramburs</span><span><?= e(Shop::money($o['payment_fee'])) ?></span></div><?php endif; ?>
      <div class="line total"><span>Total</span><span><?= e(Shop::money($o['total'])) ?></span></div>
    </div>
  </div>

  <div class="card kv" style="margin-top:20px">
    <div><h3>Livrare</h3><p style="margin:0"><?= e($o['shipping_method'] === 'ridicare' ? setting('pickup_label') : setting('shipping_label')) ?><br><?= Orders::addressHtml($o) ?></p></div>
    <div><h3>Facturare</h3><p style="margin:0"><?= Orders::addressHtml($o, false) ?></p></div>
    <div><h3>Plată</h3><p style="margin:0"><?= e(Orders::PAYMENT_METHODS[$o['payment_method']] ?? $o['payment_method']) ?><br><strong><?= e(Orders::paymentLabel($o['payment_status'])) ?></strong><?= $o['bt_card'] ? '<br><span class="small muted">Card ' . e($o['bt_card']) . '</span>' : '' ?></p></div>
    <div><h3>Stare</h3><p style="margin:0"><strong><?= e(Orders::statusLabel($o['status'])) ?></strong><?php if ($o['customer_note']): ?><br><span class="small muted">Mențiuni: <?= e($o['customer_note']) ?></span><?php endif; ?></p></div>
  </div>
  <p class="center muted small" style="margin-top:26px">Ai întrebări despre comandă? Scrie-ne la <a href="mailto:<?= e(setting('email')) ?>?subject=<?= rawurlencode('Comanda ' . $o['number']) ?>"><?= e(setting('email')) ?></a><?= setting('phone') ? ' sau sună la <a href="' . e(phone_href((string)setting('phone'))) . '">' . e(setting('phone')) . '</a>' : '' ?>.</p>
  <p class="center"><a class="btn btn-ghost" href="<?= e(url('/produse')) ?>">Continuă cumpărăturile</a></p>
</div>
<?php if ($isNew && $paid || $isNew && $o['payment_method'] !== 'card'): ?>
<script>window.dataLayer=window.dataLayer||[];dataLayer.push({event:'purchase',ecommerce:{transaction_id:<?= json_encode($o['number']) ?>,value:<?= json_encode((float)$o['total']) ?>,shipping:<?= json_encode((float)$o['shipping_cost']) ?>,currency:'RON',items:<?= json_encode(array_map(fn($i) => ['item_id' => (string)$i['product_id'], 'item_name' => $i['name'], 'price' => (float)$i['price'], 'quantity' => (int)$i['qty']], $items), JSON_UNESCAPED_UNICODE) ?>}});
if(typeof gtag==='function'){gtag('event','purchase',{transaction_id:<?= json_encode($o['number']) ?>,value:<?= json_encode((float)$o['total']) ?>,currency:'RON'});<?php if (setting('google_ads_id') && setting('google_ads_purchase_label')): ?>gtag('event','conversion',{send_to:<?= json_encode(setting('google_ads_id') . '/' . setting('google_ads_purchase_label')) ?>,value:<?= json_encode((float)$o['total']) ?>,currency:'RON',transaction_id:<?= json_encode($o['number']) ?>});<?php endif; ?>}
if(typeof fbq==='function'){fbq('track','Purchase',{value:<?= json_encode((float)$o['total']) ?>,currency:'RON'});}</script>
<?php endif; ?>
