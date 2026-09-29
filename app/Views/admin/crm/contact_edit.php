<?php use App\Core\Crm; use App\Core\Csrf; ?>
<div class="page-head"><div><h1><?= e($title) ?></h1><p><a href="<?= e(url(!empty($c['id']) ? '/admin/crm/contacte/' . $c['id'] : '/admin/crm/contacte')) ?>">← Înapoi</a></p></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-err"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" class="card f" style="max-width:900px"><?= Csrf::field() ?>
  <div class="row3">
    <div class="fl"><label>Nume *</label><input class="in" name="name" value="<?= e($c['name'] ?? '') ?>" required></div>
    <div class="fl"><label>Email</label><input class="in" type="email" name="email" value="<?= e($c['email'] ?? '') ?>"></div>
    <div class="fl"><label>Telefon</label><input class="in" name="phone" value="<?= e($c['phone'] ?? '') ?>"></div>
  </div>
  <div class="row3">
    <div class="fl"><label>Firmă</label><input class="in" name="company" value="<?= e($c['company'] ?? '') ?>"></div>
    <div class="fl"><label>CUI</label><input class="in" name="cui" value="<?= e($c['cui'] ?? '') ?>"></div>
    <div class="fl"><label>Funcție</label><input class="in" name="position" value="<?= e($c['position'] ?? '') ?>"></div>
  </div>
  <div class="row3">
    <div class="fl"><label>Oraș</label><input class="in" name="city" value="<?= e($c['city'] ?? '') ?>"></div>
    <div class="fl"><label>Județ</label><input class="in" name="county" value="<?= e($c['county'] ?? '') ?>" list="counties"><datalist id="counties"><?php foreach (\App\Core\Site::counties() as $co): ?><option value="<?= e($co['name']) ?>"><?php endforeach; ?></datalist></div>
    <div class="fl"><label>Website</label><input class="in" name="website" value="<?= e($c['website'] ?? '') ?>"></div>
  </div>
  <div class="fl"><label>Adresă</label><input class="in" name="address" value="<?= e($c['address'] ?? '') ?>"></div>
  <div class="row3">
    <div class="fl"><label>Status</label><select class="in" name="status"><?php foreach (Crm::STATUSES as $k => $l): ?><option value="<?= $k ?>"<?= ($c['status'] ?? 'lead') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="fl"><label>Responsabil</label><select class="in" name="owner_id"><option value="">—</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"<?= (int)($c['owner_id'] ?? 0) === (int)$u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></div>
    <div class="fl"><label>Etichete</label><input class="in" name="tags" value="<?= e($c['tags'] ?? '') ?>" placeholder="ex: abonament, mures, cabinet"><div class="hint">Separate prin virgulă. Folosite pentru segmentarea campaniilor.</div></div>
  </div>
  <div class="fl"><label>Note interne</label><textarea class="in" name="notes" rows="4"><?= e($c['notes'] ?? '') ?></textarea></div>
  <div class="card" style="background:var(--panel-2)">
    <div class="fl"><label>Newsletter</label><select class="in" name="newsletter" style="max-width:320px"><?php foreach (Crm::NEWSLETTER as $k => $l): if ($k === 'pending') continue; ?><option value="<?= $k ?>"<?= ($c['newsletter'] ?? 'none') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <label class="chk small" style="margin-top:8px"><input type="checkbox" name="consent_ok" value="1"> Confirm că persoana și-a dat acordul să primească emailuri de marketing (obligatoriu pentru abonare manuală – GDPR).</label>
  </div>
  <div><button class="btn btn-p"><?= icon('check') ?> Salvează</button></div>
</form>
