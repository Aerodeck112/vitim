<?php use App\Core\Auth; use App\Core\Csrf; ?>
<div class="page-head"><div><h1><?= e($title) ?></h1><p><a href="<?= e(url('/admin/utilizatori')) ?>">← Utilizatori</a></p></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-err"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" class="card f" style="max-width:640px"><?= Csrf::field() ?>
  <div class="row2"><div class="fl"><label>Nume</label><input class="in" name="name" value="<?= e($u['name'] ?? '') ?>" required></div><div class="fl"><label>Email</label><input class="in" type="email" name="email" value="<?= e($u['email'] ?? '') ?>" required></div></div>
  <div class="row2"><div class="fl"><label>Rol</label><select class="in" name="role"><?php foreach (Auth::ROLES as $k => $l): ?><option value="<?= $k ?>"<?= ($u['role'] ?? 'editor') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="fl"><label><?= !empty($u['id']) ? 'Parolă nouă (opțional)' : 'Parolă (min. 10 caractere)' ?></label><input class="in" type="password" name="password" autocomplete="new-password"></div></div>
  <label class="chk"><input type="checkbox" name="active" value="1"<?= ($u['active'] ?? 1) ? ' checked' : '' ?>> Cont activ</label>
  <div><button class="btn btn-p"><?= icon('check') ?> Salvează</button></div>
</form>
