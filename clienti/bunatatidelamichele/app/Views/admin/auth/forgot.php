<?php use App\Core\Csrf; $t = 'Parolă uitată'; include __DIR__ . '/_head.php'; ?>
<h1 style="font-size:22px">Resetare parolă</h1>
<?php if (!empty($error)): ?><div class="alert alert-err"><?= e($error) ?></div><?php endif; ?>
<?php if ($sent): ?>
  <div class="alert alert-ok">Dacă adresa există în sistem, vei primi în câteva minute un email cu linkul de resetare.</div>
<?php else: ?>
<p style="color:#a3adc2">Introdu emailul contului. Îți trimitem un link valabil o oră.</p>
<form method="post"><?= Csrf::field() ?>
  <label for="email">Email</label><input class="in" id="email" name="email" type="email" required autofocus>
  <button class="btn btn-p" type="submit">Trimite linkul</button>
</form>
<?php endif; ?>
<p style="text-align:center;margin:18px 0 0;font-size:13.5px"><a href="<?= e(url('/admin/login')) ?>">Înapoi la autentificare</a></p>
<?php include __DIR__ . '/_foot.php'; ?>
