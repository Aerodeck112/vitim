<?php
use App\Core\Settings;
use App\Core\Site;

$groups = Site::servicesByCategory();
$selected = $service ?? '';
$budgets = Settings::json('form_budgets');
$phone = (string)setting('phone');
$fid = 'f' . substr(md5((string)($title ?? '') . $selected), 0, 6);
$counties = Site::counties();
?>
<div class="cta" data-reveal>
  <div class="cta-grid">
    <div>
      <span class="eyebrow">Contact</span>
      <h2><?= e($title ?? 'Hai să vorbim') ?></h2>
      <p class="muted" style="font-size:1.08rem"><?= e($text ?? '') ?></p>
      <ul class="contact-lines">
        <li><a href="<?= e(phone_href($phone)) ?>" data-loc="cta"><?= icon('phone') ?><span><small>Sună-ne direct</small><?= e($phone) ?></span></a></li>
        <?php if ($wa = setting('whatsapp')): ?><li><a href="<?= e(whatsapp_href((string)$wa, 'Bună! Aș dori o ofertă.')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?><span><small>Scrie-ne pe WhatsApp</small>Răspundem rapid</span></a></li><?php endif; ?>
        <li><a href="mailto:<?= e(setting('email')) ?>"><?= icon('mail') ?><span><small>Email</small><?= e(setting('email')) ?></span></a></li>
        <li><span><?= icon('clock') ?><span><small>Program</small><?= e(setting('hours')) ?></span></span></li>
      </ul>
    </div>
    <form class="form" id="<?= e($fid) ?>" data-ajax data-form="contact" action="<?= e(url('/api/contact')) ?>" method="post" novalidate>
      <div class="row">
        <div class="field"><label for="<?= $fid ?>-name">Nume și prenume <span class="req">*</span></label><input id="<?= $fid ?>-name" name="name" required maxlength="120" autocomplete="name"></div>
        <div class="field"><label for="<?= $fid ?>-company">Firmă</label><input id="<?= $fid ?>-company" name="company" maxlength="160" autocomplete="organization"></div>
      </div>
      <div class="row">
        <div class="field"><label for="<?= $fid ?>-phone">Telefon <span class="req">*</span></label><input id="<?= $fid ?>-phone" name="phone" type="tel" required maxlength="30" autocomplete="tel" inputmode="tel"></div>
        <div class="field"><label for="<?= $fid ?>-email">Email <span class="req">*</span></label><input id="<?= $fid ?>-email" name="email" type="email" required maxlength="160" autocomplete="email"></div>
      </div>
      <div class="row">
        <div class="field"><label for="<?= $fid ?>-service">Serviciul dorit</label>
          <select id="<?= $fid ?>-service" name="service">
            <option value="">Nu știu încă / mai multe</option>
            <?php foreach ($groups as $g): ?><optgroup label="<?= e($g['cat']['short']) ?>"><?php foreach ($g['items'] as $s): ?><option value="<?= e($s['slug']) ?>"<?= $selected === $s['slug'] ? ' selected' : '' ?>><?= e($s['title']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label for="<?= $fid ?>-county">Județ</label>
          <select id="<?= $fid ?>-county" name="county">
            <option value="">Selectează</option>
            <?php foreach ($counties as $c): ?><option<?= ($county ?? '') === $c['name'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            <option>Alt județ (remote)</option>
          </select>
        </div>
      </div>
      <?php if ($budgets): ?>
      <fieldset class="field" style="border:0;padding:0;margin:0"><legend style="font-size:13.5px;font-weight:500;color:var(--text-2);margin-bottom:8px">Buget estimat</legend>
        <div class="chips"><?php foreach ($budgets as $b): ?><label><input type="radio" name="budget" value="<?= e($b) ?>"><span><?= e($b) ?></span></label><?php endforeach; ?></div>
      </fieldset>
      <?php endif; ?>
      <div class="field"><label for="<?= $fid ?>-msg">Cu ce te putem ajuta? <span class="req">*</span></label><textarea id="<?= $fid ?>-msg" name="message" required maxlength="5000" placeholder="Ex: avem 12 calculatoare și un server, vrem abonament de mentenanță și backup…"></textarea></div>
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <?php foreach (['page', 'referrer', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'] as $h): ?><input type="hidden" name="<?= $h ?>" value=""><?php endforeach; ?>
      <label class="check"><input type="checkbox" name="consent" value="1" required> <span><?= str_replace('Politicii de confidențialitate', '<a href="' . e(url('/politica-de-confidentialitate')) . '" target="_blank">Politicii de confidențialitate</a>', e(setting('consent_text'))) ?> <span class="req">*</span></span></label>
      <label class="check"><input type="checkbox" name="newsletter" value="1"> <span><?= e(setting('newsletter_consent_text')) ?></span></label>
      <?php if ($ts = setting('turnstile_site_key')): ?><div class="cf-turnstile" data-sitekey="<?= e($ts) ?>" data-theme="auto"></div><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif; ?>
      <button class="btn btn-primary btn-lg btn-block" type="submit">Trimite solicitarea <?= icon('send') ?></button>
      <div class="form-msg" role="status" aria-live="polite"></div>
      <p class="form-note" style="margin:0">Răspundem de regulă în aceeași zi lucrătoare. Datele tale nu sunt transmise terților.</p>
    </form>
  </div>
</div>
