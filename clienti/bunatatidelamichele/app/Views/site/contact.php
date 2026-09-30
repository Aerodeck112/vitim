<?php use App\Core\View; $email = (string)setting('email'); $phone = (string)setting('phone'); ?>
<section class="page-hero"><div class="container"><?= View::partial('site/partials/crumbs', ['seo' => $seo]) ?><span class="kicker">Contact</span><h1>Suntem aici pentru tine</h1><p>Întrebări despre cafea, comenzi sau livrare? Scrie-ne – îți răspundem în cel mai scurt timp.</p></div></section>
<section class="section-sm">
  <div class="container split" style="align-items:start">
    <div>
      <div class="card" style="margin-bottom:20px">
        <ul class="checks" style="margin:0">
          <li><?= icon('mail') ?><div><strong>Email</strong><br><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></div></li>
          <?php if ($phone): ?><li><?= icon('phone') ?><div><strong>Telefon</strong><br><a href="<?= e(phone_href($phone)) ?>"><?= e($phone) ?></a></div></li><?php endif; ?>
          <?php if ($h = setting('hours')): ?><li><?= icon('clock') ?><div><strong>Program</strong><br><?= e($h) ?></div></li><?php endif; ?>
          <?php if (\App\Core\Site::companyAddress() !== ''): ?><li><?= icon('map') ?><div><strong>Sediu</strong><br><?= e(\App\Core\Site::companyAddress()) ?></div></li><?php endif; ?>
          <li><?= icon('package') ?><div><strong>Ai deja o comandă?</strong><br><a href="<?= e(url('/urmarire-comanda')) ?>">Verifică starea comenzii</a></div></li>
        </ul>
      </div>
      <h2 style="font-size:28px">Întrebări frecvente</h2>
      <?= View::partial('site/partials/faq', ['faq' => $faq, 'openFirst' => true]) ?>
    </div>
    <form class="card" data-ajax="<?= e(url('/api/contact')) ?>" style="display:grid;gap:16px">
      <div><h2 style="font-size:26px;margin-bottom:4px">Ai nevoie de asistență?</h2><p class="muted" style="margin:0">Completează formularul și îți răspundem în cel mai scurt timp posibil.</p></div>
      <input type="hidden" name="_t" value=""><input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div class="field"><label for="f-n">Numele tău <span class="req">*</span></label><input id="f-n" name="name" required autocomplete="name"></div>
      <div class="row"><div class="field"><label for="f-e">Adresa ta de email <span class="req">*</span></label><input id="f-e" name="email" type="email" required autocomplete="email"></div><div class="field"><label for="f-p">Telefon (opțional)</label><input id="f-p" name="phone" type="tel" autocomplete="tel"></div></div>
      <div class="field"><label for="f-s">Subiect</label><input id="f-s" name="subject"></div>
      <div class="field"><label for="f-m">Mesajul tău <span class="req">*</span></label><textarea id="f-m" name="message" required></textarea></div>
      <label class="check"><input type="checkbox" name="consent" value="1" required> <span><?= e(setting('contact_consent_text')) ?> <a href="<?= e(url('/politica-de-confidentialitate')) ?>" target="_blank">Detalii</a>.</span></label>
      <?php if ($tk = setting('turnstile_site_key')): ?><div class="cf-turnstile" data-sitekey="<?= e($tk) ?>"></div><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif; ?>
      <div class="form-msg" role="status"></div>
      <div><button class="btn" type="submit"><?= icon('send') ?> Trimite mesajul</button></div>
    </form>
  </div>
</section>
