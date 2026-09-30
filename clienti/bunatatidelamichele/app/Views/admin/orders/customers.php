<?php use App\Core\Shop; ?>
<div class="page-head"><div><h1>Clienți</h1><p><?= count($rows) ?> clienți, grupați după adresa de email din comenzi.</p></div></div>
<form class="search" method="get"><input class="in" name="q" value="<?= e($q) ?>" placeholder="Email, nume, telefon, firmă…"><button class="btn"><?= icon('search') ?> Caută</button></form>
<div class="table-wrap"><?php if ($rows): ?><table class="t"><thead><tr><th>Client</th><th>Contact</th><th>Comenzi</th><th class="r">Total cumpărat</th><th>Ultima comandă</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td><strong><?= e(trim($r['first_name'] . ' ' . $r['last_name'])) ?></strong><?= $r['company'] ? '<div class="small muted">' . e($r['company']) . '</div>' : '' ?><div class="small muted"><?= e($r['city']) ?></div></td>
<td class="small"><a href="mailto:<?= e($r['em']) ?>"><?= e($r['em']) ?></a><div><?= e($r['phone']) ?></div></td>
<td><a href="<?= e(url('/admin/comenzi?q=' . urlencode($r['em']))) ?>"><?= (int)$r['orders'] ?></a></td>
<td class="r money"><strong><?= e(Shop::money($r['spent'])) ?></strong></td>
<td class="small muted"><?= e(local_time($r['last_at'], 'd.m.Y')) ?></td></tr>
<?php endforeach; ?></tbody></table><?php else: ?><div class="empty">Încă nu există clienți.</div><?php endif; ?></div>
