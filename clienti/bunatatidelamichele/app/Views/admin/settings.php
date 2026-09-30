<?php
use App\Core\Auth;
use App\Core\BtIpay;
use App\Core\Csrf;
use App\Core\Settings;
use App\Core\View;

$t = $tabs[$tab];
?>
<div class="page-head"><div><h1><?= $tab === 'prima' ? 'Prima pagină' : 'Setări' ?></h1><p><?= $tab === 'prima' ? 'Textele, imaginile și secțiunile de pe prima pagină a magazinului.' : 'Configurează firma, livrarea, plățile, emailul și integrările.' ?></p></div>
<div class="actions"><?php if ($tab === 'prima'): ?><a class="btn" href="<?= e(url('/')) ?>" target="_blank"><?= icon('external') ?> Vezi prima pagină</a><?php endif; ?><form method="post" action="<?= e(url('/admin/cache/goleste')) ?>"><?= Csrf::field() ?><button class="btn btn-sm"><?= icon('refresh') ?> Golește cache</button></form></div></div>
<?php if ($tab !== 'prima' && Auth::can('settings')): ?>
<nav class="tabs"><?php foreach ($tabs as $k => $tb): if ($k === 'prima') continue; ?><a href="<?= e(url('/admin/setari/' . $k)) ?>" class="<?= $k === $tab ? 'on' : '' ?>"><?= e($tb['label']) ?></a><?php endforeach; ?></nav>
<?php endif; ?>

<?php if ($tab === 'plati'):
  $mode = BtIpay::mode();
  $cb = abs_url('/plata/bt/callback') . '?t=' . Settings::get('bt_callback_token');
?>
<div class="grid g2" style="margin-bottom:16px">
  <div class="card">
    <h2><?= icon('card') ?> Stare BT iPay</h2>
    <p style="margin:0 0 10px">Mod: <?= $mode === 'live' ? '<span class="badge b-ok">PRODUCȚIE – plăți reale</span>' : '<span class="badge b-warn">TEST – sandbox</span>' ?>
    · Date API: <?= BtIpay::configured() ? '<span class="badge b-ok">completate</span>' : '<span class="badge b-err">lipsă</span>' ?>
    · Card pe site: <?= Settings::get('pay_card_enabled') === '1' && BtIpay::configured() ? '<span class="badge b-ok">vizibil</span>' : '<span class="badge">ascuns</span>' ?></p>
    <?php if ($ts = Settings::get('bt_tested_at')): ?><p class="small muted" style="margin:0 0 10px">Ultimul test de conexiune reușit: <?= e(local_time((string)$ts)) ?></p><?php endif; ?>
    <form method="post" action="<?= e(url('/admin/setari/plati/test')) ?>"><?= Csrf::field() ?><button class="btn"<?= BtIpay::configured() ? '' : ' disabled' ?>><?= icon('zap') ?> Testează conexiunea</button></form>
  </div>
  <div class="card">
    <h2>Adrese pentru Banca Transilvania</h2>
    <p class="small muted" style="margin:0 0 8px">Adresa de întoarcere se trimite automat la fiecare plată. Adresa de notificare (callback) trimite-o echipei BT iPay, ca banca să confirme plățile chiar dacă clientul închide browserul.</p>
    <div class="fl"><label>URL de întoarcere (returnUrl)</label><code class="code" id="bt-ret"><?= e(abs_url('/plata/bt/retur')) ?></code></div>
    <div class="fl" style="margin-top:8px"><label>URL notificare (callback)</label><code class="code" id="bt-cb"><?= e($cb) ?></code><div class="actions" style="margin-top:6px"><button class="btn btn-sm" type="button" data-copy="#bt-cb">Copiază</button></div></div>
  </div>
</div>
<div class="alert alert-info"><strong>Pașii de reactivare BT iPay:</strong> 1) completează <a href="<?= e(url('/admin/setari/firma')) ?>">datele firmei</a> (CUI, Reg. Com., adresă, telefon) – banca verifică să apară pe site, împreună cu paginile Termeni, Livrare, Retur, Confidențialitate și siglele cardurilor (sunt deja pe site); 2) pune datele API de TEST, salvează și apasă „Testează conexiunea”; 3) plasează o comandă de test cu cardurile de test primite de la BT; 4) după aprobarea băncii, completează datele de PRODUCȚIE și schimbă modul pe PRODUCȚIE.</div>
<?php endif; ?>

<?php if ($tab === 'email'): ?>
<div class="alert alert-info"><strong>Configurare rapidă pe cPanel:</strong> creează adresa <em>office@bunatatidelamichele.ro</em> în cPanel → <em>Email Accounts</em>; <em>Connect Devices</em> îți arată serverul SMTP. De obicei: server <code>mail.bunatatidelamichele.ro</code>, port <code>465</code>, SSL, utilizator = adresa completă. Verifică în cPanel → <em>Email Deliverability</em> că SPF și DKIM sunt „Valid”.</div>
<?php endif; ?>

<form method="post" action="<?= e(url('/admin/setari/' . $tab)) ?>" class="card f">
  <?= Csrf::field() ?>
  <div class="grid g2">
  <?php foreach ($t['fields'] as $f):
      [$key, $label, $type] = $f;
      $hint = $f[3] ?? '';
      $val = Settings::get($key, '');
      $wide = in_array($type, ['textarea', 'richtext', 'repeater', 'code'], true);
  ?>
    <div style="<?= $wide ? 'grid-column:1/-1' : '' ?>">
    <?php if ($type === 'repeater'): ?>
      <?= View::partial('admin/partials/field', ['f' => ['name' => $key, 'label' => $label, 'type' => 'repeater', 'fields' => $f[4], 'hint' => $hint], 'val' => (string)$val]) ?>
    <?php elseif ($type === 'password'): ?>
      <div class="fl"><label><?= e($label) ?></label><input class="in" type="password" name="<?= e($key) ?>" value="" placeholder="<?= $val !== '' ? '•••••••• (salvată)' : '' ?>" autocomplete="new-password"><?php if ($hint): ?><div class="hint"><?= e($hint) ?></div><?php endif; ?></div>
    <?php elseif ($type === 'code'): ?>
      <div class="fl"><label><?= e($label) ?></label><textarea class="in mono" name="<?= e($key) ?>" rows="4" style="font-size:12.5px"><?= e($val) ?></textarea><?php if ($hint): ?><div class="hint"><?= e($hint) ?></div><?php endif; ?></div>
    <?php elseif ($type === 'select'): ?>
      <?= View::partial('admin/partials/field', ['f' => ['name' => $key, 'label' => $label, 'type' => 'select', 'options' => $f[4], 'hint' => $hint], 'val' => $val]) ?>
    <?php else: ?>
      <?= View::partial('admin/partials/field', ['f' => ['name' => $key, 'label' => $label, 'type' => in_array($type, ['email', 'url', 'money'], true) ? 'text' : $type, 'hint' => $hint], 'val' => $val]) ?>
    <?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div>
  <div class="sticky-save"><button class="btn btn-p" type="submit"><?= icon('check') ?> Salvează</button></div>
</form>

<?php if ($tab === 'email'): ?>
<form method="post" action="<?= e(url('/admin/setari/email/test')) ?>" class="card" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
  <?= Csrf::field() ?>
  <div class="fl" style="flex:1;min-width:240px"><label>Trimite un email de test către</label><input class="in" type="email" name="to" value="<?= e(Auth::user()['email'] ?? '') ?>"></div>
  <button class="btn"><?= icon('send') ?> Trimite test</button>
  <?php if (Settings::get('smtp_tested') === '1'): ?><span class="badge b-ok">Testat cu succes</span><?php endif; ?>
</form>
<?php endif; ?>
