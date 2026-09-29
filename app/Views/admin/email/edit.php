<?php
use App\Core\Crm;
use App\Core\Csrf;
use App\Core\Newsletter;

$a = $audience + ['segment' => 'subscribers', 'statuses' => [], 'counties' => [], 'tags' => []];
$id = $c['id'] ?? null;
?>
<div class="page-head"><div><h1><?= e($title) ?></h1><p><a href="<?= e(url('/admin/email')) ?>">← Campanii</a></p></div>
<?php if ($id): ?><div class="actions"><a class="btn" href="<?= e(url('/admin/email/' . $id . '/previzualizare')) ?>" target="_blank"><?= icon('eye') ?> Previzualizare</a></div><?php endif; ?></div>
<?php foreach ($errors as $er): ?><div class="alert alert-err"><?= e($er) ?></div><?php endforeach; ?>
<?php if (!$id && $posts): ?><div class="alert alert-info">Sfat: poți porni campania de la un articol – <?php foreach (array_slice($posts, 0, 3) as $p): ?><a href="?post=<?= (int)$p['id'] ?>">„<?= e($p['title']) ?>”</a> · <?php endforeach; ?></div><?php endif; ?>
<form method="post" class="split" id="camp-form"><?= Csrf::field() ?>
  <div>
    <div class="card f">
      <div class="row2">
        <div class="fl"><label>Nume intern</label><input class="in" name="name" value="<?= e($c['name'] ?? '') ?>" placeholder="ex: Newsletter octombrie"></div>
        <div class="fl"><label>Tip</label><select class="in" name="kind"><?php foreach (Newsletter::KINDS as $k => $l): ?><option value="<?= $k ?>"<?= ($c['kind'] ?? 'newsletter') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="fl"><label>Subiect</label><input class="in" name="subject" value="<?= e($c['subject'] ?? '') ?>" required data-count="60"><div class="hint">Scurt și concret. Poți folosi {{prenume}}.</div></div>
      <div class="fl"><label>Text de previzualizare (preheader)</label><input class="in" name="preheader" value="<?= e($c['preheader'] ?? '') ?>" data-count="100"><div class="hint">Apare lângă subiect în inbox.</div></div>
      <div class="fl"><label>Conținut</label><textarea name="body" data-rte><?= e($c['body'] ?? '') ?></textarea><div class="hint">Variabile: {{prenume}}, {{nume}}, {{firma}}, {{email}}. Antetul cu logo, datele firmei și linkul de dezabonare se adaugă automat.</div></div>
    </div>
  </div>
  <div style="position:sticky;top:76px">
    <div class="card f" data-audience>
      <h2 style="margin:0">Audiență · <span data-audience-count>…</span> destinatari</h2>
      <div class="fl"><label>Segment</label>
        <label class="chk small"><input type="radio" name="segment" value="subscribers"<?= $a['segment'] === 'subscribers' ? ' checked' : '' ?>> Abonați newsletter (cu acord)</label>
        <label class="chk small"><input type="radio" name="segment" value="clients"<?= $a['segment'] === 'clients' ? ' checked' : '' ?>> Clienți (doar pentru notificări)</label>
        <label class="chk small"><input type="radio" name="segment" value="all_contacts"<?= $a['segment'] === 'all_contacts' ? ' checked' : '' ?>> Toate contactele (doar pentru notificări)</label>
        <div class="hint">Pentru tipul „Newsletter” se folosesc mereu doar abonații confirmați.</div>
      </div>
      <div class="fl"><label>Doar statusurile</label><?php foreach (Crm::STATUSES as $k => $l): ?><label class="chk small"><input type="checkbox" name="statuses[]" value="<?= $k ?>"<?= in_array($k, $a['statuses'], true) ? ' checked' : '' ?>> <?= e($l) ?></label><?php endforeach; ?></div>
      <?php if ($counties): ?><div class="fl"><label>Doar județele</label><?php foreach ($counties as $co): ?><label class="chk small"><input type="checkbox" name="counties[]" value="<?= e($co) ?>"<?= in_array($co, $a['counties'], true) ? ' checked' : '' ?>> <?= e($co) ?></label><?php endforeach; ?></div><?php endif; ?>
      <div class="fl"><label>Cu etichetele (oricare)</label><input class="in" name="tags" value="<?= e(implode(', ', $a['tags'])) ?>" placeholder="ex: abonament, cabinet"></div>
      <button class="btn btn-p" type="submit"><?= icon('check') ?> Salvează ciorna</button>
    </div>
  </div>
</form>
<?php if ($id): ?>
<div class="grid g2" style="margin-top:16px">
  <form method="post" action="<?= e(url('/admin/email/' . $id . '/test')) ?>" class="card f"><?= Csrf::field() ?>
    <h2 style="margin:0"><?= icon('mail') ?> Trimite un test</h2>
    <div class="fl"><label>Către</label><input class="in" type="email" name="to" value="<?= e(\App\Core\Auth::user()['email'] ?? '') ?>"></div>
    <div><button class="btn">Trimite test</button></div>
  </form>
  <form method="post" action="<?= e(url('/admin/email/' . $id . '/lanseaza')) ?>" class="card f" data-confirm="Ai salvat ultimele modificări? Campania va fi trimisă către toată audiența selectată. Continui?"><?= Csrf::field() ?>
    <h2 style="margin:0"><?= icon('send') ?> Trimite campania</h2>
    <div class="fl"><label>Programează (opțional)</label><input class="in" type="datetime-local" name="scheduled_at" value="<?= !empty($c['scheduled_at']) ? e(local_time($c['scheduled_at'], 'Y-m-d\TH:i')) : '' ?>"><div class="hint">Gol = trimite acum. Programarea necesită cron activ.</div></div>
    <div><button class="btn btn-p"><?= icon('send') ?> Trimite / programează</button></div>
  </form>
</div>
<?php endif; ?>
