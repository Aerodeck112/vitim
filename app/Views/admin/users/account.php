<?php use App\Core\Csrf; ?>
<div class="page-head"><div><h1>Contul meu</h1><p><?= e($u['email']) ?></p></div><div class="actions"><a class="btn" href="<?= e(url('/admin/cont/2fa')) ?>"><?= icon('lock') ?> Autentificare în doi pași <?= $u['totp_secret'] ? '<span class="badge b-ok">activă</span>' : '<span class="badge b-warn">inactivă</span>' ?></a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-err"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" class="card f" style="max-width:640px"><?= Csrf::field() ?>
  <div class="fl"><label>Nume afișat</label><input class="in" name="name" value="<?= e($u['name']) ?>"></div>
  <h3 style="margin:10px 0 0">Schimbă parola</h3>
  <div class="fl"><label>Parola curentă</label><input class="in" type="password" name="current" autocomplete="current-password"></div>
  <div class="row2"><div class="fl"><label>Parolă nouă</label><input class="in" type="password" name="password" autocomplete="new-password"></div><div class="fl"><label>Repetă parola nouă</label><input class="in" type="password" name="password2" autocomplete="new-password"></div></div>
  <div><button class="btn btn-p"><?= icon('check') ?> Salvează</button></div>
</form>
