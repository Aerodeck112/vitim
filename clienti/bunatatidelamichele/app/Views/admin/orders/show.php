<?php
use App\Controllers\Admin\OrderController;
use App\Core\BtIpay;
use App\Core\Csrf;
use App\Core\Orders;
use App\Core\Shop;

/** @var array $o */
$badge = fn(array $map, string $k) => '<span class="st" style="color:' . e($map[$k][1] ?? '#888') . ';background:' . e($map[$k][1] ?? '#888') . '1f">' . e($map[$k][0] ?? $k) . '</span>';
$base = '/admin/comenzi/' . $o['id'];
$refundable = round((float)$o['bt_deposited_amount'] - (float)$o['bt_refunded_amount'], 2);
$counties = Shop::counties();
?>
<div class="page-head">
  <div><h1>Comanda <?= e($o['number']) ?> <?= $badge(Orders::STATUSES, $o['status']) ?></h1><p><a href="<?= e(url('/admin/comenzi')) ?>">← Comenzi</a> · plasată <?= e(local_time($o['created_at'])) ?> · <?= e(Orders::PAYMENT_METHODS[$o['payment_method']] ?? '') ?> <?= $badge(Orders::PAYMENT_STATUSES, $o['payment_status']) ?></p></div>
  <div class="actions">
    <a class="btn" href="<?= e(url($base . '/tipareste')) ?>" target="_blank"><?= icon('printer') ?> Tipărește</a>
    <a class="btn" href="<?= e(url(Orders::publicUrl($o))) ?>" target="_blank"><?= icon('external') ?> Pagina clientului</a>
  </div>
</div>

<div class="split">
  <div>
    <div class="card">
      <h2>Produse</h2>
      <table class="t"><tbody>
      <?php foreach ($items as $it): ?>
        <tr><td style="width:60px"><?php if ($it['image']): ?><img class="thumb" src="<?= e(upload_url($it['image'])) ?>" alt=""><?php endif; ?></td>
        <td><?php if ($it['product_id']): ?><a class="row-title" href="<?= e(url('/admin/produse/' . $it['product_id'])) ?>"><?= e($it['name']) ?></a><?php else: ?><?= e($it['name']) ?><?php endif; ?><?= $it['sku'] ? '<div class="small muted">Cod: ' . e($it['sku']) . '</div>' : '' ?></td>
        <td class="money"><?= (int)$it['qty'] ?> × <?= e(Shop::money($it['price'])) ?></td><td class="r money"><strong><?= e(Shop::money($it['total'])) ?></strong></td></tr>
      <?php endforeach; ?>
      <tr><td></td><td colspan="2" class="muted">Subtotal</td><td class="r money"><?= e(Shop::money($o['subtotal'])) ?></td></tr>
      <?php if ((float)$o['discount'] > 0): ?><tr><td></td><td colspan="2" class="muted">Reducere <?= $o['coupon_code'] ? '(' . e($o['coupon_code']) . ')' : '' ?></td><td class="r money">−<?= e(Shop::money($o['discount'])) ?></td></tr><?php endif; ?>
      <tr><td></td><td colspan="2" class="muted"><?= e($o['shipping_method'] === 'ridicare' ? 'Ridicare personală' : 'Livrare') ?></td><td class="r money"><?= e(Shop::money($o['shipping_cost'])) ?></td></tr>
      <?php if ((float)$o['payment_fee'] > 0): ?><tr><td></td><td colspan="2" class="muted">Taxă ramburs</td><td class="r money"><?= e(Shop::money($o['payment_fee'])) ?></td></tr><?php endif; ?>
      <tr><td></td><td colspan="2"><strong>Total</strong></td><td class="r money" style="font-size:18px"><strong><?= e(Shop::money($o['total'])) ?></strong></td></tr>
      </tbody></table>
      <?php if ($o['customer_note']): ?><div class="alert alert-warn" style="margin:14px 0 0"><strong>Mențiunile clientului:</strong> <?= nl2br(e($o['customer_note'])) ?></div><?php endif; ?>
    </div>

    <div class="grid g2">
      <div class="card">
        <h2>Schimbă starea</h2>
        <form method="post" action="<?= e(url($base . '/stare')) ?>" class="f"><?= Csrf::field() ?>
          <div class="fl"><select class="in" name="status"><?php foreach (Orders::STATUSES as $k => $s): ?><option value="<?= $k ?>"<?= $o['status'] === $k ? ' selected' : '' ?>><?= e($s[0]) ?></option><?php endforeach; ?></select></div>
          <div class="fl"><textarea class="in" name="note" rows="2" placeholder="Mesaj pentru client / notă (opțional)"></textarea></div>
          <label class="chk"><input type="checkbox" name="notify" value="1" checked> Anunță clientul pe email</label>
          <div><button class="btn btn-p"><?= icon('check') ?> Actualizează</button></div>
        </form>
      </div>
      <div class="card">
        <h2><?= icon('truck') ?> Expediere</h2>
        <form method="post" action="<?= e(url($base . '/livrare')) ?>" class="f"><?= Csrf::field() ?>
          <div class="row2">
            <div class="fl"><label>Curier</label><select class="in" name="courier"><option value="">—</option><?php foreach (OrderController::COURIERS as $c => $u): ?><option<?= $o['courier'] === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
            <div class="fl"><label>AWB</label><input class="in mono" name="awb" value="<?= e($o['awb']) ?>"></div>
          </div>
          <div class="fl"><label>Link urmărire (opțional)</label><input class="in" name="tracking_url" value="<?= e($o['tracking_url']) ?>" placeholder="se completează automat pentru curierii din listă"></div>
          <label class="chk"><input type="checkbox" name="ship" value="1"<?= in_array($o['status'], ['expediata', 'livrata'], true) ? '' : ' checked' ?>> Marchează comanda ca expediată</label>
          <label class="chk"><input type="checkbox" name="notify" value="1" checked> Trimite clientului AWB-ul pe email</label>
          <div><button class="btn btn-p"><?= icon('send') ?> Salvează</button></div>
        </form>
      </div>
    </div>

    <div class="card">
      <details>
        <summary style="cursor:pointer;font-weight:700">✏️ Editează datele clientului și adresele</summary>
        <form method="post" action="<?= e(url($base . '/client')) ?>" class="f" style="margin-top:14px"><?= Csrf::field() ?>
          <div class="row3"><div class="fl"><label>Prenume</label><input class="in" name="first_name" value="<?= e($o['first_name']) ?>"></div><div class="fl"><label>Nume</label><input class="in" name="last_name" value="<?= e($o['last_name']) ?>"></div><div class="fl"><label>Telefon</label><input class="in" name="phone" value="<?= e($o['phone']) ?>"></div></div>
          <div class="row3"><div class="fl"><label>Email</label><input class="in" name="email" value="<?= e($o['email']) ?>"></div><div class="fl"><label>Firmă</label><input class="in" name="company" value="<?= e($o['company']) ?>"></div><div class="row2" style="gap:8px"><div class="fl"><label>CUI</label><input class="in" name="cui" value="<?= e($o['cui']) ?>"></div><div class="fl"><label>Reg. Com.</label><input class="in" name="reg_com" value="<?= e($o['reg_com']) ?>"></div></div></div>
          <strong>Facturare</strong>
          <div class="fl"><input class="in" name="billing_address" value="<?= e($o['billing_address']) ?>"></div>
          <div class="row3"><div class="fl"><input class="in" name="billing_city" value="<?= e($o['billing_city']) ?>"></div><div class="fl"><select class="in" name="billing_county"><?php foreach ($counties as $c): ?><option<?= $o['billing_county'] === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div><div class="fl"><input class="in" name="billing_postcode" value="<?= e($o['billing_postcode']) ?>" placeholder="Cod poștal"></div></div>
          <label class="chk"><input type="checkbox" name="ship_same" value="1"<?= (int)$o['ship_same'] ? ' checked' : '' ?>> Livrare la adresa de facturare</label>
          <strong>Livrare (dacă e diferită)</strong>
          <div class="row2"><div class="fl"><input class="in" name="shipping_name" value="<?= e($o['shipping_name']) ?>" placeholder="Nume destinatar"></div><div class="fl"><input class="in" name="shipping_phone" value="<?= e($o['shipping_phone']) ?>" placeholder="Telefon destinatar"></div></div>
          <div class="fl"><input class="in" name="shipping_address" value="<?= e($o['shipping_address']) ?>" placeholder="Adresă"></div>
          <div class="row3"><div class="fl"><input class="in" name="shipping_city" value="<?= e($o['shipping_city']) ?>" placeholder="Localitate"></div><div class="fl"><select class="in" name="shipping_county"><option value="">—</option><?php foreach ($counties as $c): ?><option<?= $o['shipping_county'] === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div><div class="fl"><input class="in" name="shipping_postcode" value="<?= e($o['shipping_postcode']) ?>" placeholder="Cod poștal"></div></div>
          <div class="fl"><label>Notă internă (nu o vede clientul)</label><textarea class="in" name="admin_note" rows="2"><?= e($o['admin_note']) ?></textarea></div>
          <div><button class="btn btn-p"><?= icon('check') ?> Salvează datele</button></div>
        </form>
      </details>
    </div>

    <div class="card">
      <div class="card-h"><h2>Istoric</h2></div>
      <form method="post" action="<?= e(url($base . '/nota')) ?>" class="inline-form" style="margin-bottom:16px"><?= Csrf::field() ?><input class="in" name="note" placeholder="Adaugă o notă internă…" style="flex:1;width:100%"><button class="btn btn-sm"><?= icon('plus') ?> Notă</button></form>
      <?php foreach ($events as $ev): ?>
      <div class="ev <?= e($ev['type']) ?>"><div><?= e($ev['message']) ?></div><div class="small muted"><?= e(local_time($ev['created_at'])) ?><?= $ev['user_name'] ? ' · ' . e($ev['user_name']) : '' ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div style="position:sticky;top:76px">
    <div class="card">
      <h2>Client</h2>
      <p style="margin:0 0 6px"><strong><?= e(Orders::customerName($o)) ?></strong><?= $o['customer_type'] === 'pj' ? ' <span class="badge b-vio">firmă</span>' : '' ?></p>
      <p class="small" style="margin:0 0 12px"><a href="mailto:<?= e($o['email']) ?>?subject=<?= rawurlencode('Comanda ' . $o['number']) ?>"><?= e($o['email']) ?></a><br><a href="<?= e(phone_href($o['phone'])) ?>"><?= e($o['phone']) ?></a></p>
      <dl class="kv2">
        <dt>Facturare</dt><dd><?= Orders::addressHtml($o, false) ?></dd>
        <dt>Livrare</dt><dd><?= (int)$o['ship_same'] ? '<span class="muted">aceeași adresă</span>' : Orders::addressHtml($o) ?></dd>
        <?php if ($o['awb']): ?><dt>AWB</dt><dd><?= e($o['courier']) ?> <span class="mono"><?= e($o['awb']) ?></span><?= $o['tracking_url'] ? ' · <a href="' . e($o['tracking_url']) . '" target="_blank">urmărire</a>' : '' ?></dd><?php endif; ?>
        <?php if ($o['admin_note']): ?><dt>Notă internă</dt><dd><?= nl2br(e($o['admin_note'])) ?></dd><?php endif; ?>
        <?php $utm = json_list($o['utm']); if ($utm): ?><dt>Sursă</dt><dd class="small"><?= e(implode(' · ', array_map(fn($k, $v) => $k . ': ' . $v, array_keys($utm), $utm))) ?></dd><?php endif; ?>
      </dl>
      <?php if ($history): ?><p class="small" style="margin:12px 0 0"><strong>Alte comenzi:</strong> <?php foreach ($history as $h): ?><a href="<?= e(url('/admin/comenzi/' . $h['id'])) ?>"><?= e($h['number']) ?></a> (<?= e(Shop::money($h['total'])) ?>) <?php endforeach; ?></p><?php endif; ?>
    </div>

    <div class="card">
      <h2><?= icon('card') ?> Plată</h2>
      <p style="margin:0 0 10px"><?= e(Orders::PAYMENT_METHODS[$o['payment_method']] ?? '') ?> · <?= $badge(Orders::PAYMENT_STATUSES, $o['payment_status']) ?><?= $o['paid_at'] ? '<br><span class="small muted">plătită ' . e(local_time($o['paid_at'])) . '</span>' : '' ?></p>
      <?php if ($o['bt_order_id']): ?>
      <div class="bt-box">
        <dl class="kv2" style="grid-template-columns:110px 1fr">
          <dt>Stare BT</dt><dd><?= e(BtIpay::STATUS_LABELS[(int)$o['bt_status']] ?? '—') ?></dd>
          <dt>ID tranzacție</dt><dd class="mono small" style="word-break:break-all"><?= e($o['bt_order_id']) ?></dd>
          <?php if ($o['bt_card']): ?><dt>Card</dt><dd class="mono"><?= e($o['bt_card']) ?></dd><?php endif; ?>
          <dt>Autorizat</dt><dd class="money"><?= e(Shop::money($o['bt_approved_amount'])) ?></dd>
          <dt>Încasat</dt><dd class="money"><?= e(Shop::money($o['bt_deposited_amount'])) ?></dd>
          <?php if ((float)$o['bt_refunded_amount'] > 0): ?><dt>Rambursat</dt><dd class="money"><?= e(Shop::money($o['bt_refunded_amount'])) ?></dd><?php endif; ?>
          <?php if ($o['bt_action_code'] !== null && $o['bt_action_code'] !== '0'): ?><dt>Motiv refuz</dt><dd class="small"><?= e(BtIpay::actionMessage((int)$o['bt_action_code'], (string)$o['bt_message'])) ?> (cod <?= e($o['bt_action_code']) ?>)</dd><?php endif; ?>
          <dt>Încercări</dt><dd><?= (int)$o['bt_attempts'] ?></dd>
        </dl>
        <div class="tests" style="margin-top:12px">
          <form method="post" action="<?= e(url($base . '/plata/verifica')) ?>"><?= Csrf::field() ?><button class="btn btn-sm" style="width:100%"><?= icon('refresh') ?> Verifică starea la bancă</button></form>
          <?php if ((int)$o['bt_status'] === 1): ?>
          <form method="post" action="<?= e(url($base . '/plata/incaseaza')) ?>" class="inline-form" data-confirm="Încasezi suma de pe cardul clientului?"><?= Csrf::field() ?><input class="in" name="amount" value="<?= e(number_format((float)$o['bt_approved_amount'], 2, '.', '')) ?>" style="width:110px"><button class="btn btn-sm btn-p" style="flex:1">Încasează</button></form>
          <form method="post" action="<?= e(url($base . '/plata/anuleaza')) ?>" data-confirm="Anulezi autorizarea? Suma se deblochează pe cardul clientului."><?= Csrf::field() ?><button class="btn btn-sm btn-d" style="width:100%">Anulează autorizarea</button></form>
          <?php endif; ?>
          <?php if ($refundable > 0 && in_array((int)$o['bt_status'], [2, 7], true)): ?>
          <form method="post" action="<?= e(url($base . '/plata/ramburseaza')) ?>" class="inline-form" data-confirm="Rambursezi suma pe cardul clientului? Operația nu poate fi anulată."><?= Csrf::field() ?><input class="in" name="amount" value="<?= e(number_format($refundable, 2, '.', '')) ?>" style="width:110px"><button class="btn btn-sm btn-d" style="flex:1"><?= icon('return') ?> Rambursează</button></form>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
      <form method="post" action="<?= e(url($base . '/plata/marcheaza')) ?>" class="inline-form" style="margin-top:12px"><?= Csrf::field() ?><select class="in" name="payment_status" style="flex:1"><?php foreach (Orders::PAYMENT_STATUSES as $k => $s): ?><option value="<?= $k ?>"<?= $o['payment_status'] === $k ? ' selected' : '' ?>><?= e($s[0]) ?></option><?php endforeach; ?></select><button class="btn btn-sm">Setează manual</button></form>
      <p class="small muted" style="margin:6px 0 0">Pentru ramburs: comanda se marchează automat „Plătită” când o treci la „Livrată”.</p>
    </div>

    <div class="card">
      <h2>Emailuri</h2>
      <div class="tests">
        <form method="post" action="<?= e(url($base . '/email')) ?>"><?= Csrf::field() ?><button class="btn btn-sm" style="width:100%"><?= icon('mail') ?> Retrimite confirmarea comenzii</button></form>
        <form method="post" action="<?= e(url($base . '/email')) ?>"><?= Csrf::field() ?><input type="hidden" name="what" value="stare"><button class="btn btn-sm" style="width:100%"><?= icon('send') ?> Retrimite emailul cu starea actuală</button></form>
      </div>
    </div>
  </div>
</div>
