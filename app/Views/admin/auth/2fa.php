<?php use App\Core\Csrf; $t = 'Verificare în doi pași'; include __DIR__ . '/_head.php'; ?>
<h1 style="font-size:22px">Verificare în doi pași</h1>
<p style="color:#a3adc2">Introdu codul de 6 cifre din aplicația de autentificare (Google Authenticator, Microsoft Authenticator, 2FAS…).</p>
<?php if ($error): ?><div class="alert alert-err"><?= e($error) ?></div><?php endif; ?>
<form method="post">
  <?= Csrf::field() ?>
  <label for="code">Cod</label><input class="in" id="code" name="code" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" required autofocus style="font-size:22px;letter-spacing:.3em;text-align:center">
  <button class="btn btn-p" type="submit">Verifică</button>
</form>
<?php include __DIR__ . '/_foot.php'; ?>
