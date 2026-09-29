<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Settings;
use App\Core\View;

$t = $tabs[$tab];
?>
<div class="page-head"><div><h1><?= $tab === 'prima' ? 'Prima pagină' : 'Setări' ?></h1><p><?= $tab === 'prima' ? 'Textele și secțiunile de pe prima pagină a site-ului.' : 'Configurează firma, emailul, integrările și comportamentul site-ului.' ?></p></div>
<div class="actions"><form method="post" action="<?= e(url('/admin/cache/goleste')) ?>"><?= Csrf::field() ?><button class="btn btn-sm"><?= icon('refresh') ?> Golește cache</button></form></div></div>
<?php if ($tab !== 'prima' && Auth::can('settings')): ?>
<nav class="tabs"><?php foreach ($tabs as $k => $tb): if ($k === 'prima') continue; ?><a href="<?= e(url('/admin/setari/' . $k)) ?>" class="<?= $k === $tab ? 'on' : '' ?>"><?= e($tb['label']) ?></a><?php endforeach; ?></nav>
<?php endif; ?>

<?php if ($tab === 'email'): ?>
<div class="alert alert-info"><strong>Configurare rapidă pe cPanel:</strong> creează o adresă (ex. <em>office@vitim.ro</em>) în cPanel → <em>Email Accounts</em>, apoi <em>Connect Devices</em> îți arată serverul SMTP. De obicei: server <code>mail.vitim.ro</code>, port <code>465</code>, SSL, utilizator = adresa completă. Pentru livrabilitate maximă verifică în cPanel → <em>Email Deliverability</em> că SPF și DKIM sunt „Valid”.</div>
<?php endif; ?>

<form method="post" action="<?= e(url('/admin/setari/' . $tab)) ?>" class="card f">
  <?= Csrf::field() ?>
  <div class="grid g2">
  <?php foreach ($t['fields'] as $f):
      [$key, $label, $type] = $f;
      $hint = $f[3] ?? '';
      $val = Settings::get($key, '');
      $wide = in_array($type, ['textarea', 'richtext', 'repeater', 'list', 'code'], true);
  ?>
    <div style="<?= $wide ? 'grid-column:1/-1' : '' ?>">
    <?php if ($type === 'list'): ?>
      <div class="fl"><label><?= e($label) ?></label><textarea class="in" name="<?= e($key) ?>" rows="4"><?= e(implode("\n", Settings::json($key))) ?></textarea><div class="hint">Câte un element pe rând. <?= e($hint) ?></div></div>
    <?php elseif ($type === 'repeater'): ?>
      <?= View::partial('admin/partials/field', ['f' => ['name' => $key, 'label' => $label, 'type' => 'repeater', 'fields' => $f[4], 'hint' => $hint], 'val' => (string)$val]) ?>
    <?php elseif ($type === 'password'): ?>
      <div class="fl"><label><?= e($label) ?></label><input class="in" type="password" name="<?= e($key) ?>" value="" placeholder="<?= $val !== '' ? '•••••••• (salvată)' : '' ?>" autocomplete="new-password"><?php if ($hint): ?><div class="hint"><?= e($hint) ?></div><?php endif; ?></div>
    <?php elseif ($type === 'code'): ?>
      <div class="fl"><label><?= e($label) ?></label><textarea class="in mono" name="<?= e($key) ?>" rows="4" style="font-size:12.5px"><?= e($val) ?></textarea><?php if ($hint): ?><div class="hint"><?= e($hint) ?></div><?php endif; ?></div>
    <?php elseif ($type === 'select'): ?>
      <?= View::partial('admin/partials/field', ['f' => ['name' => $key, 'label' => $label, 'type' => 'select', 'options' => $f[4], 'hint' => $hint], 'val' => $val]) ?>
    <?php else: ?>
      <?= View::partial('admin/partials/field', ['f' => ['name' => $key, 'label' => $label, 'type' => in_array($type, ['email', 'url'], true) ? 'text' : $type, 'hint' => $hint], 'val' => $val]) ?>
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
