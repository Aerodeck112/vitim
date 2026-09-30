<?php use App\Core\Csrf; ?>
<div class="page-head"><div><h1>Redirecționări</h1><p>Păstrează pozițiile din Google când muți sau ștergi pagini. Adresele vechi ale site-ului WordPress sunt deja redirecționate.</p></div></div>
<form method="post" action="<?= e(url('/admin/seo/redirectionari')) ?>" class="card" style="display:grid;grid-template-columns:1fr 1fr 170px auto;gap:10px;align-items:end">
  <?= Csrf::field() ?>
  <div class="fl"><label>De la (adresa veche)</label><input class="in mono" name="from_path" placeholder="/pagina-veche" required value="<?= e($_GET['from'] ?? '') ?>"></div>
  <div class="fl"><label>Către (adresa nouă)</label><input class="in mono" name="to_path" placeholder="/produs/... sau https://…"></div>
  <div class="fl"><label>Tip</label><select class="in" name="code"><option value="301">301 – permanent</option><option value="302">302 – temporar</option><option value="410">410 – șters definitiv</option></select></div>
  <button class="btn btn-p"><?= icon('plus') ?> Adaugă</button>
</form>

<?php if ($nf): ?>
<div class="card">
  <div class="card-h"><h2>Pagini negăsite (404) recente</h2><form method="post" action="<?= e(url('/admin/seo/404/0/sterge')) ?>"><?= Csrf::field() ?><button class="btn btn-sm">Golește jurnalul</button></form></div>
  <p class="small muted">Adrese accesate care nu există. Dacă una are multe accesări, creează o redirecționare către pagina potrivită.</p>
  <table class="t"><thead><tr><th>Adresă</th><th>Accesări</th><th>Ultima dată</th><th>Venit de la</th><th class="r"></th></tr></thead><tbody>
  <?php foreach ($nf as $n): ?>
    <tr><td class="mono small"><?= e($n['path']) ?></td><td><?= (int)$n['hits'] ?></td><td class="small muted"><?= e(local_time($n['last_seen_at'])) ?></td><td class="small muted" style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($n['referer']) ?></td>
    <td class="r" style="white-space:nowrap"><a class="btn btn-xs" href="?from=<?= e(urlencode($n['path'])) ?>">Redirecționează</a><form method="post" action="<?= e(url('/admin/seo/404/' . $n['id'] . '/sterge')) ?>" style="display:inline"><?= Csrf::field() ?><button class="btn btn-xs btn-ghost">✕</button></form></td></tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-h"><h2>Redirecționări active (<?= count($rows) ?>)</h2><form class="search" method="get" style="margin:0"><input class="in" name="q" value="<?= e($q) ?>" placeholder="Caută…"></form></div>
  <table class="t"><thead><tr><th>De la</th><th>Către</th><th>Tip</th><th>Accesări</th><th class="r"></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td class="mono small"><?= e($r['from_path']) ?></td><td class="mono small"><?= $r['code'] == 410 ? '<span class="muted">— șters —</span>' : e($r['to_path']) ?></td>
    <td><span class="badge <?= $r['code'] == 410 ? 'b-err' : ($r['code'] == 301 ? 'b-ok' : 'b-warn') ?>"><?= (int)$r['code'] ?></span></td><td><?= (int)$r['hits'] ?></td>
    <td class="r"><form method="post" action="<?= e(url('/admin/seo/redirectionari/' . $r['id'] . '/sterge')) ?>" data-confirm="Ștergi redirecționarea?"><?= Csrf::field() ?><button class="btn btn-xs btn-d"><?= icon('trash') ?></button></form></td></tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
