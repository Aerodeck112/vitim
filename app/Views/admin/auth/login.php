<?php use App\Core\Csrf; $t = 'Autentificare'; include __DIR__ . '/_head.php'; ?>
<h1 style="font-size:22px">Bine ai revenit</h1>
<?php if ($error): ?><div class="alert alert-err"><?= e($error) ?></div><?php endif; ?>
<form method="post" autocomplete="on">
  <?= Csrf::field() ?>
  <label for="email">Email</label><input class="in" id="email" name="email" type="email" required autofocus autocomplete="username" value="<?= e(str_input('email')) ?>">
  <label for="password">Parolă</label><input class="in" id="password" name="password" type="password" required autocomplete="current-password">
  <button class="btn btn-p" type="submit">Intră în cont</button>
</form>
<p style="text-align:center;margin:18px 0 0;font-size:13.5px"><a href="<?= e(url('/admin/parola-uitata')) ?>">Ai uitat parola?</a> · <a href="<?= e(url('/')) ?>">Înapoi la site</a></p>
<?php include __DIR__ . '/_foot.php'; ?>
