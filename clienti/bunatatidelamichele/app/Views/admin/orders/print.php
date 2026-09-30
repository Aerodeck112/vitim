<?php
use App\Core\Orders;
use App\Core\Shop;
use App\Core\Site;
?><!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>Comanda <?= e($o['number']) ?></title>
<style>body{font:14px/1.5 Arial,sans-serif;color:#222;max-width:800px;margin:30px auto;padding:0 20px}h1{font-size:22px;margin:0}table{width:100%;border-collapse:collapse;margin:20px 0}th,td{padding:8px;border-bottom:1px solid #ddd;text-align:left}th{background:#f5f0ea}.r{text-align:right}.head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;border-bottom:2px solid #222;padding-bottom:16px}.cols{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-top:20px}.cols h3{font-size:12px;text-transform:uppercase;letter-spacing:.1em;color:#777;margin:0 0 6px}.tot td{border:0;padding:4px 8px}.big{font-size:18px;font-weight:700}.note{background:#faf5ee;padding:10px;border-radius:6px}@media print{.noprint{display:none}body{margin:0}}</style></head>
<body>
<p class="noprint"><button onclick="window.print()">Tipărește</button></p>
<div class="head"><div><img src="<?= e(url('/assets/img/logo.png')) ?>" alt="" style="height:70px"><br><strong><?= e(setting('company_name')) ?></strong><br><?= setting('company_cui') ? 'CUI ' . e(setting('company_cui')) . '<br>' : '' ?><?= e(Site::companyAddress()) ?><br><?= e(setting('email')) ?> <?= e(setting('phone')) ?></div>
<div class="r"><h1>Comanda <?= e($o['number']) ?></h1><?= e(local_time($o['created_at'])) ?><br><?= e(Orders::PAYMENT_METHODS[$o['payment_method']] ?? '') ?> – <?= e(Orders::paymentLabel($o['payment_status'])) ?><?php if ($o['awb']): ?><br><?= e($o['courier']) ?> AWB <?= e($o['awb']) ?><?php endif; ?></div></div>
<div class="cols"><div><h3>Client / Facturare</h3><?= Orders::addressHtml($o, false) ?><br><?= e($o['email']) ?></div><div><h3>Livrare</h3><?= Orders::addressHtml($o) ?></div><div><h3>Metodă livrare</h3><?= e($o['shipping_method'] === 'ridicare' ? setting('pickup_label') : setting('shipping_label')) ?></div></div>
<table><thead><tr><th>Produs</th><th>Cod</th><th class="r">Cant.</th><th class="r">Preț</th><th class="r">Total</th></tr></thead><tbody>
<?php foreach ($items as $it): ?><tr><td><?= e($it['name']) ?></td><td><?= e($it['sku']) ?></td><td class="r"><?= (int)$it['qty'] ?> <?= e($it['unit']) ?></td><td class="r"><?= e(Shop::money($it['price'])) ?></td><td class="r"><?= e(Shop::money($it['total'])) ?></td></tr><?php endforeach; ?>
</tbody></table>
<table class="tot" style="width:320px;margin-left:auto"><tr><td>Subtotal</td><td class="r"><?= e(Shop::money($o['subtotal'])) ?></td></tr>
<?php if ((float)$o['discount'] > 0): ?><tr><td>Reducere</td><td class="r">−<?= e(Shop::money($o['discount'])) ?></td></tr><?php endif; ?>
<tr><td>Livrare</td><td class="r"><?= e(Shop::money($o['shipping_cost'])) ?></td></tr>
<?php if ((float)$o['payment_fee'] > 0): ?><tr><td>Taxă ramburs</td><td class="r"><?= e(Shop::money($o['payment_fee'])) ?></td></tr><?php endif; ?>
<tr class="big"><td>Total</td><td class="r"><?= e(Shop::money($o['total'])) ?></td></tr></table>
<?php if ($o['customer_note']): ?><p class="note"><strong>Mențiuni client:</strong> <?= nl2br(e($o['customer_note'])) ?></p><?php endif; ?>
<p style="color:#777;font-size:12px;margin-top:30px">Document intern de pregătire a comenzii – nu ține loc de factură fiscală.</p>
</body></html>
