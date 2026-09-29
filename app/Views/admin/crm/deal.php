<?php
use App\Core\Crm;
use App\Core\Csrf;
use App\Core\View;

$utm = json_list($deal['utm'] ?? '{}');
?>
<div class="page-head">
  <div><h1><?= e($deal['title'] ?? 'Oportunitate nouă') ?></h1><p><a href="<?= e(url('/admin/crm')) ?>">← Pipeline</a><?php if ($contact): ?> · <a href="<?= e(url('/admin/crm/contacte/' . $contact['id'])) ?>"><?= e($contact['name']) ?></a><?php endif; ?></p></div>
  <?php if (!empty($deal['id'])): ?><form method="post" action="<?= e(url('/admin/crm/oportunitati/' . $deal['id'] . '/sterge')) ?>" data-confirm="Ștergi oportunitatea?"><?= Csrf::field() ?><button class="btn btn-d btn-sm"><?= icon('trash') ?> Șterge</button></form><?php endif; ?>
</div>
<?php foreach ($errors as $er): ?><div class="alert alert-err"><?= e($er) ?></div><?php endforeach; ?>
<div class="split">
  <div>
    <form method="post" class="card f"><?= Csrf::field() ?>
      <div class="fl"><label>Titlu</label><input class="in" name="title" value="<?= e($deal['title'] ?? '') ?>" required placeholder="ex: Abonament mentenanță 15 PC"></div>
      <div class="row2">
        <div class="fl"><label>Contact</label>
          <select class="in" name="contact_id"><option value="">— contact nou —</option><?php foreach ($contacts as $co): ?><option value="<?= $co['id'] ?>"<?= (int)($contact['id'] ?? 0) === (int)$co['id'] ? ' selected' : '' ?>><?= e($co['name'] . ($co['company'] ? ' (' . $co['company'] . ')' : '')) ?></option><?php endforeach; ?></select>
        </div>
        <div class="fl"><label>Serviciu</label><input class="in" name="service" value="<?= e($deal['service'] ?? '') ?>" list="svcs"><datalist id="svcs"><?php foreach ($services as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist></div>
      </div>
      <?php if (!$contact): ?><div class="row3"><div class="fl"><label>Nume contact nou</label><input class="in" name="new_contact"></div><div class="fl"><label>Email</label><input class="in" name="new_email" type="email"></div><div class="fl"><label>Telefon</label><input class="in" name="new_phone"></div></div><?php endif; ?>
      <div class="row3">
        <div class="fl"><label>Etapă</label><select class="in" name="stage"><?php foreach ($stages as $k => $st): ?><option value="<?= e($k) ?>"<?= ($deal['stage'] ?? 'nou') === $k ? ' selected' : '' ?>><?= e($st['label']) ?></option><?php endforeach; ?></select></div>
        <div class="fl"><label>Valoare estimată (lei)</label><input class="in" name="value" value="<?= e((string)(float)($deal['value'] ?? 0)) ?>"></div>
        <div class="fl"><label>Închidere estimată</label><input class="in" type="date" name="expected_close" value="<?= !empty($deal['expected_close']) ? e(local_time($deal['expected_close'], 'Y-m-d')) : '' ?>"></div>
      </div>
      <div class="fl"><label>Responsabil</label><select class="in" name="owner_id" style="max-width:300px"><option value="">—</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"<?= (int)($deal['owner_id'] ?? 0) === (int)$u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></div>
      <div class="fl"><label>Detalii / mesajul clientului</label><textarea class="in" name="message" rows="6"><?= e($deal['message'] ?? '') ?></textarea></div>
      <div><button class="btn btn-p"><?= icon('check') ?> Salvează</button></div>
    </form>
    <?php if (!empty($deal['id'])): ?>
    <div class="card"><h2>Adaugă activitate</h2><?= View::partial('admin/crm/_activity_form', ['contactId' => $deal['contact_id'], 'dealId' => $deal['id'], 'email' => $contact['email'] ?? '']) ?></div>
    <div class="card"><h2>Istoric</h2><?= View::partial('admin/crm/_timeline', ['acts' => $acts]) ?></div>
    <?php endif; ?>
  </div>
  <div>
    <?php if ($contact): ?>
    <div class="card"><h2>Contact</h2><dl class="dl"><dt>Nume</dt><dd><a href="<?= e(url('/admin/crm/contacte/' . $contact['id'])) ?>"><?= e($contact['name']) ?></a></dd><dt>Telefon</dt><dd><?= $contact['phone'] ? '<a href="' . e(phone_href((string)$contact['phone'])) . '">' . e($contact['phone']) . '</a>' : '—' ?></dd><dt>Email</dt><dd><?= e($contact['email'] ?: '—') ?></dd><dt>Firmă</dt><dd><?= e($contact['company'] ?: '—') ?></dd></dl></div>
    <?php endif; ?>
    <?php if (!empty($deal['id'])): ?>
    <div class="card"><h2>Proveniență</h2><dl class="dl"><dt>Sursă</dt><dd><?= e(Crm::sourceLabel($deal['source'])) ?></dd><?php foreach ($utm as $k => $v): ?><dt><?= e($k) ?></dt><dd class="small"><?= e($v) ?></dd><?php endforeach; ?><dt>Creată</dt><dd><?= e(local_time($deal['created_at'])) ?></dd><?php if ($deal['closed_at']): ?><dt>Închisă</dt><dd><?= e(local_time($deal['closed_at'])) ?></dd><?php endif; ?></dl></div>
    <?php endif; ?>
  </div>
</div>
