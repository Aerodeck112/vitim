<?php use App\Core\Csrf; ?>
<form method="post" action="<?= e(url('/admin/crm/activitate')) ?>" class="f" style="gap:10px">
  <?= Csrf::field() ?>
  <input type="hidden" name="contact_id" value="<?= (int)($contactId ?? 0) ?>">
  <input type="hidden" name="deal_id" value="<?= (int)($dealId ?? 0) ?>">
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <select class="in" name="type" style="max-width:170px" onchange="var f=this.form;f.querySelector('.due').style.display=this.value==='task'?'block':'none';f.querySelector('.mail').style.display=this.value==='email'?'block':'none'">
      <option value="note">📝 Notă</option><option value="call">📞 Apel</option><option value="email">✉️ Email</option><option value="meeting">📅 Întâlnire</option><option value="task">✅ Sarcină</option>
    </select>
    <div class="due" style="display:none"><input class="in" type="datetime-local" name="due_at" title="Termen"></div>
  </div>
  <div class="mail" style="display:none"><?php if (!empty($email)): ?><input class="in" name="subject" placeholder="Subiect email" style="margin-bottom:8px"><label class="chk small"><input type="checkbox" name="send" value="1" checked> Trimite emailul acum către <?= e($email) ?></label><?php else: ?><span class="small muted">Contactul nu are email – se va salva doar ca notă.</span><?php endif; ?></div>
  <textarea class="in" name="body" rows="3" placeholder="Ce s-a discutat, ce urmează…" required></textarea>
  <div><button class="btn btn-p btn-sm"><?= icon('plus') ?> Adaugă</button></div>
</form>
