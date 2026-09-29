<?php
use App\Core\Csrf;
use App\Core\Newsletter;

$sent = max(1, (int)$c['sent']);
$pct = fn($a) => round($a * 100 / $sent, 1) . '%';
?>
<div class="page-head"><div><h1><?= e($c['name']) ?></h1><p><a href="<?= e(url('/admin/email')) ?>">← Campanii</a> · „<?= e($c['subject']) ?>” · <span class="badge"><?= e(Newsletter::STATUSES[$c['status']] ?? '') ?></span></p></div>
<div class="actions"><a class="btn" href="<?= e(url('/admin/email/' . $c['id'] . '/previzualizare')) ?>" target="_blank"><?= icon('eye') ?> Vezi emailul</a>
<?php if (in_array($c['status'], ['sending', 'scheduled', 'paused'], true)): ?><form method="post" action="<?= e(url('/admin/email/' . $c['id'] . '/pauza')) ?>"><?= Csrf::field() ?><button class="btn"><?= $c['status'] === 'paused' ? 'Reia trimiterea' : 'Pauză' ?></button></form><?php endif; ?></div></div>

<?php if ($c['status'] === 'sending'): ?>
<div class="card" data-sender data-url="<?= e(url('/admin/email/' . $c['id'] . '/lot')) ?>" data-autostart="<?= isset($_GET['start']) ? '1' : '0' ?>">
  <div class="card-h"><h2>Trimitere în curs</h2><button class="btn btn-p btn-sm" type="button" data-sender-start>Trimite din browser</button></div>
  <div class="bar"><i style="width:<?= $c['total'] ? round($c['sent'] * 100 / $c['total']) : 0 ?>%"></i></div>
  <p class="small muted" data-sender-status style="margin:8px 0 0"><?= (int)$c['sent'] ?> din <?= (int)$c['total'] ?> trimise. Lasă pagina deschisă pentru a trimite din browser, sau cron-ul continuă automat în fundal.</p>
</div>
<?php elseif ($c['status'] === 'scheduled'): ?>
<div class="alert alert-info">Programată pentru <?= e(local_time($c['scheduled_at'])) ?> – <?= (int)$c['total'] ?> destinatari. Pornește automat prin cron.</div>
<?php endif; ?>

<div class="grid g4">
  <div class="card kpi"><small>Trimise</small><b><?= (int)$c['sent'] ?></b><span class="small muted">din <?= (int)$c['total'] ?><?= $c['failed'] ? ' · ' . (int)$c['failed'] . ' eșuate' : '' ?></span></div>
  <div class="card kpi"><small>Deschideri</small><b><?= $pct((int)$c['opens']) ?></b><span class="small muted"><?= (int)$c['opens'] ?> persoane</span></div>
  <div class="card kpi"><small>Click-uri</small><b><?= $pct((int)$c['clicks']) ?></b><span class="small muted"><?= (int)$c['clicks'] ?> persoane</span></div>
  <div class="card kpi"><small>Dezabonări</small><b><?= (int)$c['unsubs'] ?></b><span class="small muted"><?= $pct((int)$c['unsubs']) ?></span></div>
</div>
<p class="small muted">Rata de deschidere este orientativă: unele aplicații (ex. Apple Mail) încarcă automat imaginile, altele le blochează.</p>

<?php if ($links): ?>
<div class="card"><h2>Linkuri accesate</h2><table class="t"><thead><tr><th>Link</th><th class="r">Click-uri</th></tr></thead><tbody><?php foreach ($links as $l): ?><tr><td class="small" style="word-break:break-all"><?= e($l['url']) ?></td><td class="r"><?= (int)$l['clicks'] ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?>

<div class="card">
  <div class="card-h"><h2>Destinatari</h2><div class="actions"><?php foreach (['' => 'Toți', 'opened' => 'Au deschis', 'clicked' => 'Au dat click', 'failed' => 'Eșuate'] as $k => $l): ?><a class="btn btn-xs<?= $filter === $k ? ' btn-p' : '' ?>" href="?f=<?= $k ?>"><?= $l ?></a><?php endforeach; ?></div></div>
  <table class="t"><thead><tr><th>Email</th><th>Status</th><th>Deschis</th><th>Click</th></tr></thead><tbody>
  <?php foreach ($rcpts as $r): ?><tr><td><?php if ($r['contact_id']): ?><a href="<?= e(url('/admin/crm/contacte/' . $r['contact_id'])) ?>"><?= e($r['email']) ?></a><?php else: ?><?= e($r['email']) ?><?php endif; ?><div class="small muted"><?= e($r['name']) ?></div></td>
  <td><span class="badge <?= $r['status'] === 'sent' ? 'b-ok' : ($r['status'] === 'failed' ? 'b-err' : '') ?>" title="<?= e($r['error']) ?>"><?= e(['queued' => 'în coadă', 'sent' => 'trimis', 'failed' => 'eșuat'][$r['status']] ?? $r['status']) ?></span></td>
  <td class="small"><?= $r['opened_at'] ? e(local_time($r['opened_at'])) . ' (' . (int)$r['open_count'] . '×)' : '—' ?></td><td class="small"><?= $r['clicked_at'] ? e(local_time($r['clicked_at'])) . ' (' . (int)$r['click_count'] . '×)' : '—' ?></td></tr><?php endforeach; ?>
  </tbody></table>
</div>
