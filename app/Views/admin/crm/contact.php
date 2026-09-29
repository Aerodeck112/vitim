<?php
use App\Core\Crm;
use App\Core\Csrf;
use App\Core\View;

$fmt = fn($v) => number_format((float)$v, 0, ',', '.');
?>
<div class="page-head">
  <div><h1><?= e($c['name']) ?></h1><p><?= e(trim(($c['position'] ? $c['position'] . ' · ' : '') . ($c['company'] ?? ''))) ?> <span class="badge <?= $c['status'] === 'client' ? 'b-ok' : 'b-vio' ?>"><?= e(Crm::STATUSES[$c['status']] ?? '') ?></span></p></div>
  <div class="actions">
    <?php if ($c['phone']): ?><a class="btn" href="<?= e(phone_href((string)$c['phone'])) ?>"><?= icon('phone') ?> Sună</a><a class="btn" href="<?= e(whatsapp_href((string)$c['phone'])) ?>" target="_blank"><?= icon('whatsapp') ?> WhatsApp</a><?php endif; ?>
    <?php if ($c['email']): ?><a class="btn" href="mailto:<?= e($c['email']) ?>"><?= icon('mail') ?> Email</a><?php endif; ?>
    <a class="btn" href="<?= e(url('/admin/crm/contacte/' . $c['id'] . '/editare')) ?>"><?= icon('edit') ?> Editează</a>
    <a class="btn btn-p" href="<?= e(url('/admin/crm/oportunitati/nou?contact=' . $c['id'])) ?>"><?= icon('plus') ?> Oportunitate</a>
  </div>
</div>
<div class="split">
  <div>
    <div class="card"><h2>Adaugă activitate</h2><?= View::partial('admin/crm/_activity_form', ['contactId' => $c['id'], 'email' => $c['email']]) ?></div>
    <div class="card"><h2>Istoric</h2><?= View::partial('admin/crm/_timeline', ['acts' => $acts]) ?></div>
  </div>
  <div>
    <div class="card">
      <h2>Detalii</h2>
      <dl class="dl">
        <dt>Email</dt><dd><?= e($c['email'] ?: '—') ?></dd>
        <dt>Telefon</dt><dd><?= e($c['phone'] ?: '—') ?></dd>
        <?php if ($c['company']): ?><dt>Firmă</dt><dd><?= e($c['company']) ?><?= $c['cui'] ? ' · CUI ' . e($c['cui']) : '' ?></dd><?php endif; ?>
        <?php if ($c['city'] || $c['county']): ?><dt>Localitate</dt><dd><?= e(trim($c['city'] . ', ' . $c['county'], ', ')) ?></dd><?php endif; ?>
        <?php if ($c['address']): ?><dt>Adresă</dt><dd><?= e($c['address']) ?></dd><?php endif; ?>
        <?php if ($c['website']): ?><dt>Website</dt><dd><a href="<?= e($c['website']) ?>" target="_blank" rel="noopener"><?= e($c['website']) ?></a></dd><?php endif; ?>
        <dt>Sursă</dt><dd><?= e(Crm::sourceLabel($c['source'])) ?></dd>
        <dt>Etichete</dt><dd><?= e($c['tags'] ?: '—') ?></dd>
        <dt>Newsletter</dt><dd><span class="badge <?= $c['newsletter'] === 'subscribed' ? 'b-ok' : '' ?>"><?= e(Crm::NEWSLETTER[$c['newsletter']] ?? '') ?></span><?= $c['newsletter_consent_at'] ? '<div class="small muted">acord: ' . e(local_time($c['newsletter_consent_at'])) . ' (' . e($c['newsletter_consent_ip']) . ')</div>' : '' ?></dd>
        <dt>Creat</dt><dd><?= e(local_time($c['created_at'])) ?></dd>
      </dl>
      <?php if (trim((string)$c['notes']) !== ''): ?><h3 style="margin-top:14px">Note</h3><p class="b" style="white-space:pre-wrap"><?= e($c['notes']) ?></p><?php endif; ?>
    </div>
    <div class="card">
      <h2>Oportunități</h2>
      <?php foreach ($deals as $d): $st = $stages[$d['stage']] ?? null; ?>
      <a href="<?= e(url('/admin/crm/oportunitati/' . $d['id'])) ?>" style="display:block;padding:9px 0;border-bottom:1px solid var(--border);color:var(--text);text-decoration:none"><strong><?= e($d['title']) ?></strong><div class="small muted"><?php if ($st): ?><span class="dot" style="background:<?= e($st['color']) ?>"></span> <?= e($st['label']) ?><?php endif; ?><?= $d['value'] > 0 ? ' · ' . $fmt($d['value']) . ' lei' : '' ?> · <?= e(local_time($d['created_at'], 'd.m.Y')) ?></div></a>
      <?php endforeach; if (!$deals): ?><p class="small muted">Nicio oportunitate.</p><?php endif; ?>
    </div>
    <?php if ($camps): ?>
    <div class="card"><h2>Campanii email</h2>
      <?php foreach ($camps as $r): ?><div class="small" style="padding:6px 0;border-bottom:1px solid var(--border)"><?= e($r['cname']) ?> · <?= $r['clicked_at'] ? '<span class="badge b-ok">click</span>' : ($r['opened_at'] ? '<span class="badge b-info">deschis</span>' : '<span class="badge">' . e($r['status']) . '</span>') ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/admin/crm/contacte/' . $c['id'] . '/sterge')) ?>" data-confirm="Ștergi DEFINITIV contactul, istoricul și oportunitățile lui? (dreptul de a fi uitat – GDPR)"><?= Csrf::field() ?><button class="btn btn-d btn-sm"><?= icon('trash') ?> Șterge contactul (GDPR)</button></form>
  </div>
</div>
