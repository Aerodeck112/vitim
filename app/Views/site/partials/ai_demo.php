<?php
$fid = 'demo' . substr(md5((string)($_SERVER['REQUEST_URI'] ?? '')), 0, 5);
?>
<div class="cta ai-demo" data-reveal>
  <div class="cta-grid">
    <div>
      <span class="eyebrow">Demo gratuit</span>
      <h2>Testează un agent AI pentru firma ta</h2>
      <p class="muted" style="font-size:1.08rem">Scrie adresa site-ului tău. Pregătim un agent demo care cunoaște serviciile, programul și întrebările frecvente ale firmei tale, ca să vezi cum le-ar răspunde clienților.</p>
      <ul class="checklist">
        <li><?= icon('check-circle') ?><span>Folosim doar informațiile publice de pe site.</span></li>
        <li><?= icon('check-circle') ?><span>Primești linkul de test pe email.</span></li>
        <li><?= icon('check-circle') ?><span>Fără obligații și fără card.</span></li>
      </ul>
    </div>
    <form class="form" id="<?= e($fid) ?>" data-ajax data-form="demo_ai" data-event="generate_lead" action="<?= e(url('/api/demo-ai')) ?>" method="post" novalidate>
      <div class="field"><label for="<?= $fid ?>-site">Site-ul firmei <span class="req">*</span></label><input id="<?= $fid ?>-site" name="site_url" required maxlength="255" placeholder="https://firma.ro" inputmode="url" autocomplete="url"></div>
      <div class="row">
        <div class="field"><label for="<?= $fid ?>-email">Email <span class="req">*</span></label><input id="<?= $fid ?>-email" name="email" type="email" required maxlength="160" autocomplete="email"></div>
        <div class="field"><label for="<?= $fid ?>-phone">Telefon</label><input id="<?= $fid ?>-phone" name="phone" type="tel" maxlength="30" autocomplete="tel" inputmode="tel"></div>
      </div>
      <div class="field"><label for="<?= $fid ?>-name">Nume</label><input id="<?= $fid ?>-name" name="name" maxlength="120" autocomplete="name"></div>
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <?php foreach (['page', 'referrer', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'] as $h): ?><input type="hidden" name="<?= $h ?>" value=""><?php endforeach; ?>
      <label class="check"><input type="checkbox" name="consent" value="1" required> <span><?= str_replace('Politicii de confidențialitate', '<a href="' . e(url('/politica-de-confidentialitate')) . '" target="_blank">Politicii de confidențialitate</a>', e(setting('consent_text'))) ?> <span class="req">*</span></span></label>
      <?php if ($ts = setting('turnstile_site_key')): ?><div class="cf-turnstile" data-sitekey="<?= e($ts) ?>" data-theme="auto"></div><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif; ?>
      <button class="btn btn-primary btn-lg btn-block" type="submit">Creează demo AI <?= icon('sparkles') ?></button>
      <div class="form-msg" role="status" aria-live="polite"></div>
      <p class="form-note" style="margin:0">Așa ar putea răspunde VITIM AI clienților tăi.</p>
    </form>
  </div>
</div>
