<?php use App\Core\Auth; use App\Core\Csrf; ?>
<div class="page-head"><div><h1>Utilizatori</h1><p>Cine are acces la panou. Rolurile limitează ce poate vedea fiecare.</p></div><div class="actions"><a class="btn btn-p" href="<?= e(url('/admin/utilizatori/nou')) ?>"><?= icon('plus') ?> Utilizator nou</a></div></div>
<div class="table-wrap"><table class="t"><thead><tr><th>Nume</th><th>Email</th><th>Rol</th><th>2FA</th><th>Ultima autentificare</th><th class="r"></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td><a class="row-title" href="<?= e(url('/admin/utilizatori/' . $r['id'])) ?>"><?= e($r['name']) ?></a><?= $r['active'] ? '' : ' <span class="badge">inactiv</span>' ?></td><td><?= e($r['email']) ?></td><td><span class="badge b-info"><?= e(Auth::ROLES[$r['role']] ?? $r['role']) ?></span></td>
<td><?= $r['totp_secret'] ? '<span class="badge b-ok">activ</span>' : '<span class="badge b-warn">nu</span>' ?></td><td class="small muted"><?= e(local_time($r['last_login_at'])) ?></td>
<td class="r"><form method="post" action="<?= e(url('/admin/utilizatori/' . $r['id'] . '/sterge')) ?>" data-confirm="Ștergi utilizatorul?"><?= Csrf::field() ?><button class="btn btn-xs btn-d"><?= icon('trash') ?></button></form></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<div class="card" style="margin-top:16px"><h3>Roluri</h3><ul class="muted small" style="margin:0"><li><strong>Administrator</strong> – acces complet, inclusiv setări, utilizatori și actualizări.</li><li><strong>Editor conținut</strong> – servicii, zone, blog, pagini, media, SEO.</li><li><strong>Vânzări (CRM)</strong> – contacte, oportunități, formulare și campanii email.</li></ul></div>
