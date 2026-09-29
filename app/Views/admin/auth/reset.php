<?php use App\Core\Csrf; $t = 'Parolă nouă'; include __DIR__ . '/_head.php'; ?>
<h1 style="font-size:22px">Setează o parolă nouă</h1>
<?php if ($error): ?><div class="alert alert-err"><?= e($error) ?></div><?php endif; ?>
<form method="post"><?= Csrf::field() ?>
  <label>Parolă nouă (minimum 10 caractere)</label><input class="in" name="password" type="password" minlength="10" required autocomplete="new-password">
  <label>Repetă parola</label><input class="in" name="password2" type="password" minlength="10" required autocomplete="new-password">
  <button class="btn btn-p" type="submit">Salvează parola</button>
</form>
<?php include __DIR__ . '/_foot.php'; ?>
