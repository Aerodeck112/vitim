<?php use App\Core\Crm; use App\Core\Csrf; ?>
<div class="page-head"><div><h1>Import contacte</h1><p><a href="<?= e(url('/admin/crm/contacte')) ?>">← Contacte</a></p></div></div>
<?php if ($result): ?><div class="alert alert-ok">Import finalizat: <strong><?= $result['new'] ?></strong> contacte noi, <strong><?= $result['upd'] ?></strong> actualizate, <?= $result['skip'] ?> rânduri ignorate (fără email/telefon).</div><?php endif; ?>
<div class="split">
  <form method="post" enctype="multipart/form-data" class="card f"><?= Csrf::field() ?>
    <div class="fl"><label>Fișier CSV</label><input class="in" type="file" name="file" accept=".csv,text/csv" required><div class="hint">Din Excel: Fișier → Salvare ca → „CSV (delimitat prin virgulă)” sau „CSV UTF-8”.</div></div>
    <div class="row2">
      <div class="fl"><label>Status pentru contactele noi</label><select class="in" name="status"><?php foreach (Crm::STATUSES as $k => $l): ?><option value="<?= $k ?>"<?= $k === 'client' ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="fl"><label>Adaugă eticheta</label><input class="in" name="tag" placeholder="ex: import-2026"></div>
    </div>
    <label class="chk"><input type="checkbox" name="subscribe" value="1"> Abonează-i la newsletter</label>
    <label class="chk small"><input type="checkbox" name="consent_ok" value="1"> Confirm că am acordul documentat al acestor persoane pentru emailuri de marketing (GDPR). Fără acord, importă-i ca neabonați – le poți trimite în continuare notificări de serviciu dacă sunt clienți.</label>
    <div><button class="btn btn-p"><?= icon('upload') ?> Importă</button></div>
  </form>
  <div class="card"><h2>Coloane recunoscute</h2><p class="small muted">Prima linie trebuie să conțină numele coloanelor (ordinea nu contează):</p><code class="code">nume; email; telefon; firma; cui; oras; judet; functie; etichete</code><p class="small muted" style="margin-top:10px">Contactele existente (același email sau telefon) sunt actualizate, nu duplicate.</p></div>
</div>
