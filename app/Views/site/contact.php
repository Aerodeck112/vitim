<?php
use App\Core\View;

$addr = trim(implode(', ', array_filter([setting('company_address'), setting('company_city'), setting('company_county') ? 'jud. ' . setting('company_county') : ''])), ', ');
echo View::partial('site/partials/page_hero', [
    'crumbs' => [['Contact', '/contact']],
    'eyebrow' => icon('message') . ' Răspundem în aceeași zi lucrătoare',
    'title' => 'Hai să discutăm',
    'lead' => $page['subtitle'] ?: 'Suport IT, o urgență, un abonament pentru firmă sau o idee de automatizare cu AI? Lasă-ne un telefon sau un email și te contactăm noi. Prima discuție și evaluarea sunt gratuite.',
]);
?>
<section class="section-sm">
  <div class="container">
    <div class="grid-4" style="margin-bottom:40px">
      <a class="card" href="<?= e(phone_href((string)setting('phone'))) ?>" data-loc="contact-card"><div class="icon-tile"><?= icon('phone') ?></div><h2 class="card-t">Telefon</h2><p><?= e(setting('phone')) ?></p></a>
      <a class="card" href="mailto:<?= e(setting('email')) ?>"><div class="icon-tile"><?= icon('mail') ?></div><h2 class="card-t">Email</h2><p><?= e(setting('email')) ?></p></a>
      <?php if ($wa = setting('whatsapp')): ?><a class="card" href="<?= e(whatsapp_href((string)$wa)) ?>" target="_blank" rel="noopener"><div class="icon-tile"><?= icon('whatsapp') ?></div><h2 class="card-t">WhatsApp</h2><p>Mesaj rapid, răspuns rapid</p></a><?php endif; ?>
      <div class="card"><div class="icon-tile"><?= icon('clock') ?></div><h2 class="card-t">Program</h2><p><?= e(setting('hours')) ?></p></div>
    </div>
    <?php if (trim(strip_tags($body)) !== ''): ?><div class="prose" style="max-width:860px;margin-bottom:40px"><?= $body ?></div><?php endif; ?>
    <?= View::partial('site/partials/cta_form', ['title' => 'Trimite-ne un mesaj', 'text' => 'Trei câmpuri și am pornit. Abonamente pentru companii de la ' . setting('pricing_from', '1.000') . ' RON/lună. ' . setting('support_note'), 'service' => $service]) ?>
    <?php if ($addr): ?>
    <div class="remote-banner" style="margin-top:24px"><?= icon('map') ?><span><strong style="color:var(--text)"><?= e(setting('company_name')) ?></strong> · <?= e($addr) ?><?php if ($m = setting('google_maps_url')): ?> · <a class="link" href="<?= e($m) ?>" target="_blank" rel="noopener">Vezi pe hartă</a><?php endif; ?></span></div>
    <?php endif; ?>
  </div>
</section>
