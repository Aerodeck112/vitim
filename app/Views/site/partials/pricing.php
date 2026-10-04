<?php
/** Abonamente: preț de pornire + mini configurator care pregătește cererea de evaluare din formularul de contact. */
$from = $from ?? (string)setting('pricing_from', '1.000');
$includes = ['Suport remote și intervenții la sediu', 'Monitorizare și actualizări', 'Backup verificat', 'Securitate și conturi administrate', 'Raport lunar cu tot ce am făcut', 'Un singur contact pentru toată tehnologia'];
$services = ['IT', 'Backup', 'Securitate', 'AI & automatizări', 'Website', 'Marketing'];
?>
<section class="section bg-alt" id="abonamente">
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="eyebrow">Abonamente</span>
      <h2>Abonamente VITIM de la <?= e($from) ?> RON/lună</h2>
      <p><?= e(setting('pricing_note')) ?></p>
    </div>
    <div class="pricing">
      <div class="price-card" data-reveal>
        <div class="price-top"><span class="price-name">Start</span><span class="tag">pentru companii</span></div>
        <p class="price"><small>de la</small> <b><?= e($from) ?></b> <span>RON/lună</span></p>
        <p class="muted" style="margin:0 0 4px">Construim abonamentul în funcție de infrastructura companiei.</p>
        <ul class="checklist">
          <?php foreach ($includes as $it): ?><li><?= icon('check-circle') ?><span><?= e($it) ?></span></li><?php endforeach; ?>
        </ul>
        <a class="btn btn-primary btn-lg btn-block" href="#contact" data-pricing-cta>Solicită evaluarea infrastructurii <?= icon('arrow-right', 'ico ico-move') ?></a>
        <p class="form-note" style="margin:12px 0 0">Prețul exact îl primești după evaluare, înainte să semnezi ceva.</p>
      </div>
      <form class="configurator" data-configurator data-reveal data-delay="100" aria-labelledby="cfg-title">
        <h3 id="cfg-title">Spune-ne cum arată firma ta</h3>
        <p class="muted" style="margin:-2px 0 18px;font-size:15px">Trei întrebări. Le trimitem împreună cu cererea ta, ca evaluarea să pornească de la date reale.</p>
        <div class="row">
          <div class="field"><label for="cfg-pc">Câte calculatoare aveți?</label><select id="cfg-pc" name="pc"><option value="">Alege</option><option>1–5</option><option>6–15</option><option>16–30</option><option>31–60</option><option>Peste 60</option></select></div>
          <div class="field"><label for="cfg-loc">Câte locații?</label><select id="cfg-loc" name="loc"><option value="">Alege</option><option>O locație</option><option>2–3 locații</option><option>Peste 3 locații</option></select></div>
        </div>
        <fieldset class="field" style="border:0;padding:0;margin:0"><legend class="legend">De ce servicii ai nevoie?</legend>
          <div class="chips"><?php foreach ($services as $s): ?><label><input type="checkbox" name="svc" value="<?= e($s) ?>"><span><?= e($s) ?></span></label><?php endforeach; ?></div>
        </fieldset>
        <button class="btn btn-ghost btn-lg btn-block" type="submit" style="margin-top:18px">Primește o evaluare <?= icon('arrow-right', 'ico ico-move') ?></button>
      </form>
    </div>
  </div>
</section>
