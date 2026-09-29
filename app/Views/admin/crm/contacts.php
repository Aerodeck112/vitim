<?php use App\Core\Crm; ?>
<div class="page-head">
  <div><h1>Contacte</h1><p><?= $pg['total'] ?> contacte</p></div>
  <div class="actions">
    <a class="btn" href="<?= e(url('/admin/crm/import')) ?>"><?= icon('upload') ?> Import CSV</a>
    <a class="btn" href="<?= e(url('/admin/crm/export')) ?>?<?= e(http_build_query($_GET)) ?>"><?= icon('download') ?> Export CSV</a>
    <a class="btn btn-p" href="<?= e(url('/admin/crm/contacte/nou')) ?>"><?= icon('plus') ?> Contact nou</a>
  </div>
</div>
<form class="search" method="get">
  <input class="in" name="q" value="<?= e(str_input('q')) ?>" placeholder="Nume, email, telefon, firmă…">
  <select class="in" name="status"><option value="">Toate statusurile</option><?php foreach (Crm::STATUSES as $k => $l): ?><option value="<?= $k ?>"<?= str_input('status') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
  <select class="in" name="newsletter"><option value="">Newsletter: oricare</option><?php foreach (Crm::NEWSLETTER as $k => $l): ?><option value="<?= $k ?>"<?= str_input('newsletter') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
  <?php if ($counties): ?><select class="in" name="county"><option value="">Toate județele</option><?php foreach ($counties as $co): ?><option<?= str_input('county') === $co ? ' selected' : '' ?>><?= e($co) ?></option><?php endforeach; ?></select><?php endif; ?>
  <input class="in" name="tag" value="<?= e(str_input('tag')) ?>" placeholder="Etichetă" style="max-width:150px">
  <button class="btn"><?= icon('search') ?> Filtrează</button>
</form>
<div class="table-wrap">
<?php if ($rows): ?>
<table class="t"><thead><tr><th>Nume</th><th>Contact</th><th>Firmă</th><th>Status</th><th>Newsletter</th><th>Etichete</th><th>Ultima interacțiune</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr>
  <td><a class="row-title" href="<?= e(url('/admin/crm/contacte/' . $r['id'])) ?>"><?= e($r['name']) ?></a><?php if ($r['open_deals']): ?> <span class="badge b-info"><?= (int)$r['open_deals'] ?> deschise</span><?php endif; ?></td>
  <td class="small"><?= e($r['email']) ?><br><span class="muted"><?= e($r['phone']) ?></span></td>
  <td><?= e($r['company']) ?><div class="small muted"><?= e(trim(($r['city'] ?? '') . ' ' . ($r['county'] ? '(' . $r['county'] . ')' : ''))) ?></div></td>
  <td><span class="badge <?= $r['status'] === 'client' ? 'b-ok' : ($r['status'] === 'prospect' ? 'b-vio' : '') ?>"><?= e(Crm::STATUSES[$r['status']] ?? $r['status']) ?></span></td>
  <td><span class="badge <?= $r['newsletter'] === 'subscribed' ? 'b-ok' : ($r['newsletter'] === 'pending' ? 'b-warn' : ($r['newsletter'] === 'unsubscribed' ? 'b-err' : '')) ?>"><?= e(Crm::NEWSLETTER[$r['newsletter']] ?? '') ?></span></td>
  <td class="small"><?= e($r['tags']) ?></td>
  <td class="small muted"><?= e(local_time($r['last_contact_at'] ?: $r['created_at'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php else: ?><div class="empty">Niciun contact găsit. Contactele apar automat din formularele site-ului sau le poți importa dintr-un CSV / Excel.</div><?php endif; ?>
</div>
<?php if ($pg['pages'] > 1): ?><div class="pagination"><?php for ($i = 1; $i <= $pg['pages']; $i++): ?><?php if ($i === $pg['page']): ?><span class="cur"><?= $i ?></span><?php else: ?><a href="?<?= e(http_build_query(array_merge($_GET, ['p' => $i]))) ?>"><?= $i ?></a><?php endif; ?><?php endfor; ?></div><?php endif; ?>
