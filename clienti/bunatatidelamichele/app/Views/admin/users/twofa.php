<?php use App\Core\Csrf; ?>
<div class="page-head"><div><h1>Autentificare în doi pași</h1><p>Protejează panoul chiar dacă parola ajunge pe mâini greșite.</p></div></div>
<?php if ($error): ?><div class="alert alert-err"><?= e($error) ?></div><?php endif; ?>
<?php if ($enabled): ?>
<div class="card" style="max-width:640px"><p><span class="badge b-ok">Activă</span> La fiecare autentificare ți se cere codul din aplicație.</p>
<form method="post" class="f" data-confirm="Sigur dezactivezi protecția?"><?= Csrf::field() ?><input type="hidden" name="action" value="disable"><div class="fl"><label>Confirmă cu parola pentru a dezactiva</label><input class="in" type="password" name="password" required></div><div><button class="btn btn-d">Dezactivează</button></div></form></div>
<?php else: ?>
<div class="card" style="max-width:720px">
  <ol style="padding-left:18px;margin-top:0">
    <li>Instalează pe telefon <strong>Google Authenticator</strong>, <strong>Microsoft Authenticator</strong> sau <strong>2FAS</strong>.</li>
    <li>Scanează codul QR de mai jos (sau introdu manual cheia).</li>
    <li>Scrie codul de 6 cifre afișat în aplicație și confirmă.</li>
  </ol>
  <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:center">
    <div data-qr="<?= e($uri) ?>" style="min-width:200px;min-height:200px"></div>
    <div><div class="small muted">Cheie manuală</div><code class="code" style="font-size:15px;letter-spacing:.1em"><?= e(trim(chunk_split($secret, 4, ' '))) ?></code></div>
  </div>
  <form method="post" class="f" style="margin-top:16px;max-width:320px"><?= Csrf::field() ?><div class="fl"><label>Cod din aplicație</label><input class="in" name="code" inputmode="numeric" maxlength="7" autocomplete="one-time-code" required style="font-size:20px;letter-spacing:.3em"></div><div><button class="btn btn-p">Activează</button></div></form>
</div>
<?php endif; ?>
