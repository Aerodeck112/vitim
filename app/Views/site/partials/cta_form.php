<?php
use App\Core\Settings;
use App\Core\Site;

$groups = Site::servicesByCategory();
$selected = $service ?? '';
$budgetsMonthly = Settings::json('form_budgets_monthly');
$budgetsProject = Settings::json('form_budgets_project');
$phone = (string)setting('phone');
$fid = 'f' . substr(md5((string)($title ?? '') . $selected), 0, 6);
$counties = Site::counties();
$needs = ['Mentenanță IT', 'Backup', 'Securitate', 'AI & automatizări', 'Website', 'Marketing'];
?>
<div class="cta" data-reveal>
  <div class="cta-grid">
    <div>
      <span class="eyebrow">Contact</span>
      <h2><?= e($title ?? 'Hai să vorbim') ?></h2>
      <p class="muted" style="font-size:1.08rem"><?= e($text ?? '') ?></p>
      <?php if (!empty($final)): ?>
      <div class="hero-ctas" style="margin:22px 0 0">
        <a class="btn btn-primary" href="#<?= e($fid) ?>" data-focus-form>Solicită o evaluare <?= icon('arrow-right', 'ico ico-move') ?></a>
        <a class="btn btn-ghost" href="<?= e(phone_href($phone)) ?>" data-loc="cta-final"><?= icon('phone') ?> Discută cu VITIM</a>
      </div>
      <?php endif; ?>
      <ul class="contact-lines">
        <li><a href="<?= e(phone_href($phone)) ?>" data-loc="cta"><?= icon('phone') ?><span><small>Sună-ne direct</small><?= e($phone) ?></span></a></li>
        <?php if ($wa = setting('whatsapp')): ?><li><a href="<?= e(whatsapp_href((string)$wa, 'Bună! Aș dori să discutăm despre firma mea.')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?><span><small>Scrie-ne pe WhatsApp</small>Răspundem rapid</span></a></li><?php endif; ?>
        <li><a href="mailto:<?= e(setting('email')) ?>"><?= icon('mail') ?><span><small>Email</small><?= e(setting('email')) ?></span></a></li>
        <li><span><?= icon('clock') ?><span><small>Program</small><?= e(setting('hours')) ?></span></span></li>
      </ul>
    </div>
    <form class="form" id="<?= e($fid) ?>" data-ajax data-form="contact" action="<?= e(url('/api/contact')) ?>" method="post" novalidate>
      <div class="field"><label for="<?= $fid ?>-name">Nume <span class="req">*</span></label><input id="<?= $fid ?>-name" name="name" required maxlength="120" autocomplete="name"></div>
      <div class="field"><label for="<?= $fid ?>-contact">Telefon sau email <span class="req">*</span></label><input id="<?= $fid ?>-contact" name="contact" required maxlength="160" placeholder="07xx xxx xxx sau nume@firma.ro"></div>
      <div class="field"><label for="<?= $fid ?>-msg">Cu ce te putem ajuta? <span class="req">*</span></label><textarea id="<?= $fid ?>-msg" name="message" required maxlength="5000" rows="4" placeholder="Ex: avem 12 calculatoare și un server, vrem pe cineva care să se ocupe de tot, plus automatizarea ofertelor…"></textarea></div>

      <details class="form-more"<?= $selected !== '' ? ' open' : '' ?>>
        <summary><?= icon('plus') ?> Adaugă detalii despre firmă <span class="muted">(opțional, ne ajută la evaluare)</span></summary>
        <div class="form-more-body">
          <div class="row">
            <div class="field"><label for="<?= $fid ?>-company">Firmă</label><input id="<?= $fid ?>-company" name="company" maxlength="160" autocomplete="organization"></div>
            <div class="field"><label for="<?= $fid ?>-county">Județ</label>
              <select id="<?= $fid ?>-county" name="county">
                <option value="">Selectează</option>
                <?php foreach ($counties as $c): ?><option<?= ($county ?? '') === $c['name'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                <option>Alt județ (remote)</option>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="field"><label for="<?= $fid ?>-emp">Număr de angajați</label><select id="<?= $fid ?>-emp" name="employees"><option value="">Selectează</option><option>1–5</option><option>6–15</option><option>16–50</option><option>51–200</option><option>Peste 200</option></select></div>
            <div class="field"><label for="<?= $fid ?>-pc">Număr de calculatoare</label><select id="<?= $fid ?>-pc" name="computers"><option value="">Selectează</option><option>1–5</option><option>6–15</option><option>16–30</option><option>31–60</option><option>Peste 60</option></select></div>
          </div>
          <fieldset class="field" style="border:0;padding:0;margin:0"><legend class="legend">Servicii de interes</legend>
            <div class="chips"><?php foreach ($needs as $n): ?><label><input type="checkbox" name="needs[]" value="<?= e($n) ?>"><span><?= e($n) ?></span></label><?php endforeach; ?></div>
          </fieldset>
          <div class="field"><label for="<?= $fid ?>-service">Un serviciu anume</label>
            <select id="<?= $fid ?>-service" name="service">
              <option value="">Nu știu încă / mai multe</option>
              <?php foreach ($groups as $g): ?><optgroup label="<?= e($g['cat']['short']) ?>"><?php foreach ($g['items'] as $s): ?><option value="<?= e($s['slug']) ?>"<?= $selected === $s['slug'] ? ' selected' : '' ?>><?= e($s['title']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
            </select>
          </div>
          <fieldset class="field budget-box" style="border:0;padding:0;margin:0"><legend class="legend">Ce fel de colaborare cauți?</legend>
            <div class="chips"><?php foreach (['abonament' => 'Abonament lunar', 'proiect' => 'Proiect (o singură dată)', 'nu-stiu' => 'Nu știu încă'] as $v => $l): ?><label><input type="radio" name="budget_type" value="<?= $v ?>" data-budget-type><span><?= e($l) ?></span></label><?php endforeach; ?></div>
          </fieldset>
          <?php if ($budgetsMonthly): ?>
          <fieldset class="field budget-opt" data-budget-for="abonament" style="border:0;padding:0;margin:0"><legend class="legend">Buget lunar</legend>
            <div class="chips"><?php foreach ($budgetsMonthly as $b): ?><label><input type="radio" name="budget" value="<?= e($b) ?>"><span><?= e($b) ?></span></label><?php endforeach; ?></div>
          </fieldset>
          <?php endif; ?>
          <?php if ($budgetsProject): ?>
          <fieldset class="field budget-opt" data-budget-for="proiect" style="border:0;padding:0;margin:0"><legend class="legend">Buget proiect</legend>
            <div class="chips"><?php foreach ($budgetsProject as $b): ?><label><input type="radio" name="budget" value="<?= e($b) ?>"><span><?= e($b) ?></span></label><?php endforeach; ?></div>
          </fieldset>
          <?php endif; ?>
          <label class="check"><input type="checkbox" name="newsletter" value="1"> <span><?= e(setting('newsletter_consent_text')) ?> <span class="muted">(dacă ai lăsat un email)</span></span></label>
        </div>
      </details>

      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <?php foreach (['page', 'referrer', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'] as $h): ?><input type="hidden" name="<?= $h ?>" value=""><?php endforeach; ?>
      <label class="check"><input type="checkbox" name="consent" value="1" required> <span><?= str_replace('Politicii de confidențialitate', '<a href="' . e(url('/politica-de-confidentialitate')) . '" target="_blank">Politicii de confidențialitate</a>', e(setting('consent_text'))) ?> <span class="req">*</span></span></label>
      <?php if ($ts = setting('turnstile_site_key')): ?><div class="cf-turnstile" data-sitekey="<?= e($ts) ?>" data-theme="auto"></div><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif; ?>
      <button class="btn btn-primary btn-lg btn-block" type="submit">Vreau să discutăm <?= icon('send') ?></button>
      <div class="form-msg" role="status" aria-live="polite"></div>
      <p class="form-note" style="margin:0">Te sunăm sau îți scriem de regulă în aceeași zi lucrătoare. Datele tale nu sunt transmise terților.</p>
    </form>
  </div>
</div>
