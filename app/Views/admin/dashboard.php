<?php
use App\Core\Crm;

$max = max(1, max($days));
$done = count(array_filter($setup, fn($s) => $s[1]));
$fmt = fn($v) => number_format((float)$v, 0, ',', '.');
?>
<div class="page-head">
  <div><h1>Salut, <?= e(explode(' ', (string)(\App\Core\Auth::user()['name'] ?? ''))[0]) ?> 👋</h1><p>Iată ce se întâmplă cu afacerea ta online.</p></div>
  <div class="actions">
    <a class="btn" href="<?= e(url('/admin/crm/contacte/nou')) ?>"><?= icon('plus') ?> Contact</a>
    <a class="btn" href="<?= e(url('/admin/c/articole/nou')) ?>"><?= icon('pen') ?> Articol</a>
    <a class="btn btn-p" href="<?= e(url('/admin/email/nou')) ?>"><?= icon('send') ?> Campanie nouă</a>
  </div>
</div>

<?php if ($done < count($setup)): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-h"><h2>Configurare inițială · <?= $done ?>/<?= count($setup) ?></h2><div class="bar" style="width:200px"><i style="width:<?= round($done * 100 / count($setup)) ?>%"></i></div></div>
  <div class="grid g4">
    <?php foreach ($setup as [$label, $ok, $link]): ?>
    <a href="<?= e(url($link)) ?>" class="chk" style="color:var(--text);text-decoration:none;padding:10px 12px;border:1px solid var(--border);border-radius:10px;<?= $ok ? 'opacity:.55' : '' ?>"><?= $ok ? '<span class="badge b-ok">✓</span>' : '<span class="badge b-warn">!</span>' ?> <span><?= e($label) ?></span></a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="grid g4">
  <div class="card kpi"><small><?= icon('inbox') ?> Lead-uri noi (7 zile)</small><b><?= $kpi['leads7'] ?></b><span class="small muted"><?= $kpi['leads30'] ?> în ultimele 30 de zile</span></div>
  <div class="card kpi"><small><?= icon('kanban') ?> Oportunități deschise</small><b><?= $kpi['open'] ?></b><span class="small muted"><?= $fmt($kpi['openValue']) ?> lei valoare estimată</span></div>
  <div class="card kpi"><small><?= icon('award') ?> Câștigat luna aceasta</small><b><?= $fmt($kpi['wonMonth']) ?> <span style="font-size:15px">lei</span></b><span class="small muted"><?= $kpi['wonCount'] ?> oportunități câștigate</span></div>
  <div class="card kpi"><small><?= icon('mail') ?> Abonați newsletter</small><b><?= $kpi['subs'] ?></b><span class="small muted"><?= $kpi['contacts'] ?> contacte · <?= $kpi['clients'] ?> clienți</span></div>
</div>

<div class="split" style="margin-top:16px">
  <div>
    <div class="card">
      <div class="card-h"><h2>Lead-uri pe zi (30 de zile)</h2><a class="small" href="<?= e(url('/admin/crm')) ?>">Vezi pipeline →</a></div>
      <div class="chart"><?php foreach ($days as $day => $n): ?><div style="height:<?= max(2, round($n * 100 / $max)) ?>%" data-t="<?= e(date('d.m', strtotime($day))) ?>: <?= $n ?>"></div><?php endforeach; ?></div>
      <div class="chart-x"><span><?= e(date('d.m', strtotime(array_key_first($days)))) ?></span><span>azi</span></div>
    </div>
    <div class="card">
      <div class="card-h"><h2>Cereri recente</h2><a class="small" href="<?= e(url('/admin/formulare')) ?>">Toate formularele →</a></div>
      <?php if ($recent): ?>
      <table class="t"><thead><tr><th>Contact</th><th>Cerere</th><th>Sursă</th><th>Etapă</th><th>Primit</th></tr></thead><tbody>
      <?php foreach ($recent as $r): $st = $stages[$r['stage']] ?? null; ?>
        <tr>
          <td><a class="row-title" href="<?= e(url('/admin/crm/contacte/' . $r['contact_id'])) ?>"><?= e($r['cname']) ?></a><div class="small muted"><?= e($r['company'] ?: $r['phone']) ?></div></td>
          <td><a href="<?= e(url('/admin/crm/oportunitati/' . $r['id'])) ?>"><?= e($r['title']) ?></a></td>
          <td><span class="badge"><?= e(Crm::sourceLabel($r['source'])) ?></span></td>
          <td><?php if ($st): ?><span class="badge" style="background:<?= e($st['color']) ?>22;color:<?= e($st['color']) ?>"><?= e($st['label']) ?></span><?php endif; ?></td>
          <td class="small muted"><?= e(local_time($r['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
      <?php else: ?><p class="empty">Încă nu ai cereri. Când cineva completează formularul de pe site, apare aici – și primești email.</p><?php endif; ?>
    </div>
  </div>
  <div>
    <div class="card">
      <div class="card-h"><h2>Surse lead-uri (30 zile)</h2></div>
      <?php $tot = max(1, array_sum(array_column($sources, 'n'))); foreach ($sources as $s): ?>
      <div style="margin-bottom:10px"><div style="display:flex;justify-content:space-between" class="small"><span><?= e(Crm::sourceLabel($s['s'])) ?></span><b><?= (int)$s['n'] ?></b></div><div class="bar"><i style="width:<?= round($s['n'] * 100 / $tot) ?>%"></i></div></div>
      <?php endforeach; if (!$sources): ?><p class="muted small">Fără date încă.</p><?php endif; ?>
    </div>
    <div class="card">
      <div class="card-h"><h2>Servicii cerute</h2></div>
      <?php foreach ($services as $s): ?><div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border)" class="small"><span><?= e($s['s']) ?></span><b><?= (int)$s['n'] ?></b></div><?php endforeach; if (!$services): ?><p class="muted small">Fără date încă.</p><?php endif; ?>
    </div>
    <div class="card">
      <div class="card-h"><h2>Sarcini</h2><a class="small" href="<?= e(url('/admin/crm/sarcini')) ?>">Toate →</a></div>
      <?php foreach ($tasks as $t): $late = $t['due_at'] && strtotime($t['due_at'] . ' UTC') < time(); ?>
      <div style="padding:8px 0;border-bottom:1px solid var(--border)"><div><?= e(excerpt((string)$t['body'], 80)) ?></div><div class="small <?= $late ? '' : 'muted' ?>" style="<?= $late ? 'color:var(--err)' : '' ?>"><?= $t['due_at'] ? e(local_time($t['due_at'])) : 'fără termen' ?><?= $t['cname'] ? ' · ' . e($t['cname']) : '' ?></div></div>
      <?php endforeach; if (!$tasks): ?><p class="muted small">Nicio sarcină deschisă. 🎉</p><?php endif; ?>
    </div>
  </div>
</div>
