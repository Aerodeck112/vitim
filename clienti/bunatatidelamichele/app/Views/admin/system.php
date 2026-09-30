<?php use App\Core\Csrf; ?>
<div class="page-head"><div><h1>Sistem & actualizări</h1><p>Versiunea instalată: <strong>v<?= e(APP_VERSION) ?></strong></p></div><div class="actions"><a class="btn" href="<?= e(url('/admin/sistem/jurnal')) ?>"><?= icon('file') ?> Jurnal erori</a></div></div>
<?php if ($pending && $pending !== ['*']): ?><div class="alert alert-warn">Există migrări nerulate: <?= e(implode(', ', $pending)) ?>. Se vor rula automat la următoarea vizită pe site.</div><?php endif; ?>

<div class="grid g2">
  <div class="card">
    <h2><?= icon('upload') ?> Actualizare din arhivă .zip</h2>
    <p class="small muted">Încarcă arhiva versiunii noi (ex. <code>bunatatidelamichele-1.1.0.zip</code>). Înainte de actualizare se face automat backup la cod și baza de date. Datele tale (produse, comenzi, imagini, setări) nu sunt modificate.</p>
    <form method="post" action="<?= e(url('/admin/sistem/actualizare')) ?>" enctype="multipart/form-data" class="f" data-confirm="Pornești actualizarea? Durează câteva secunde."><?= Csrf::field() ?>
      <input class="in" type="file" name="package" accept=".zip" required>
      <label class="chk small"><input type="checkbox" name="downgrade" value="1"> Permite revenirea la o versiune mai veche</label>
      <div><button class="btn btn-p"><?= icon('upload') ?> Actualizează</button></div>
    </form>
  </div>
  <div class="card">
    <h2><?= icon('clock') ?> Cron (sarcini automate)</h2>
    <p class="small muted">Trimite campaniile programate, continuă trimiterile mari și face curățenie. În cPanel → <em>Cron Jobs</em> adaugă, la fiecare 5 minute (<code>*/5 * * * *</code>), comanda:</p>
    <code class="code" id="cron-cmd">wget -q -O /dev/null "<?= e($cronUrl) ?>"</code>
    <div class="actions" style="margin-top:8px"><button class="btn btn-sm" type="button" data-copy="#cron-cmd">Copiază comanda</button><a class="btn btn-sm" href="<?= e($cronUrl) ?>" target="_blank">Rulează acum</a></div>
    <p class="small" style="margin:10px 0 0">Ultima rulare: <?= $cronLast ? '<span class="badge ' . (strtotime($cronLast . ' UTC') > time() - 900 ? 'b-ok' : 'b-warn') . '">' . e(local_time($cronLast)) . '</span>' : '<span class="badge b-warn">niciodată</span>' ?></p>
  </div>
</div>

<div class="card">
  <div class="card-h"><h2><?= icon('database') ?> Backup-uri</h2>
    <form method="post" action="<?= e(url('/admin/sistem/backup')) ?>" class="actions"><?= Csrf::field() ?>
      <button class="btn btn-sm" name="what" value="db">Bază de date</button><button class="btn btn-sm" name="what" value="uploads">Imagini</button><button class="btn btn-sm" name="what" value="code">Cod</button><button class="btn btn-sm btn-p" name="what" value="all">Backup complet</button>
    </form>
  </div>
  <p class="small muted">Recomandare: descarcă lunar un backup complet și păstrează-l în afara serverului. Poți folosi și backup-urile automate din cPanel (JetBackup), dacă hostingul le oferă.</p>
  <?php if ($backups): ?>
  <table class="t"><thead><tr><th>Fișier</th><th>Mărime</th><th>Data</th><th class="r"></th></tr></thead><tbody>
  <?php foreach ($backups as $b): ?>
  <tr><td class="mono small"><?= e($b['name']) ?></td><td class="small"><?= round($b['size'] / 1048576, 2) ?> MB</td><td class="small muted"><?= date('d.m.Y H:i', $b['time']) ?></td>
  <td class="r" style="white-space:nowrap"><a class="btn btn-xs" href="<?= e(url('/admin/sistem/backup/' . $b['name'])) ?>"><?= icon('download') ?> Descarcă</a>
  <?php if (str_starts_with($b['name'], 'cod-')): ?><form method="post" action="<?= e(url('/admin/sistem/restaurare/' . $b['name'])) ?>" style="display:inline" data-confirm="Restaurezi codul din acest backup? (baza de date nu se modifică)"><?= Csrf::field() ?><button class="btn btn-xs">Restaurează</button></form><?php endif; ?>
  <form method="post" action="<?= e(url('/admin/sistem/backup/' . $b['name'] . '/sterge')) ?>" style="display:inline" data-confirm="Ștergi backup-ul?"><?= Csrf::field() ?><button class="btn btn-xs btn-d"><?= icon('trash') ?></button></form></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  <?php else: ?><p class="muted small">Niciun backup încă.</p><?php endif; ?>
</div>

<div class="grid g2">
  <div class="card"><h2>Informații server</h2><dl class="dl"><?php foreach ($info as $k => $v): ?><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd><?php endforeach; ?></dl></div>
  <div class="card"><h2>Ce e nou</h2><?php if ($changelog): ?><pre style="white-space:pre-wrap;font:13px/1.6 var(--font);margin:0;max-height:360px;overflow:auto"><?= e($changelog) ?></pre><?php else: ?><p class="muted small">—</p><?php endif; ?></div>
</div>
