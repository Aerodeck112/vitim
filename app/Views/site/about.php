<?php
use App\Core\View;

$vendors = [
    ['server', 'Firma de IT'],
    ['hard-drive', 'Furnizorul de backup'],
    ['code', 'Dezvoltatorul website-ului'],
    ['megaphone', 'Agenția de marketing'],
    ['kanban', 'Furnizorul de CRM'],
    ['workflow', 'Automatizările'],
    ['bot', 'Soluțiile AI'],
];
$steps = [
    ['search', 'Analizăm', 'Înțelegem infrastructura, procesele și problemele companiei: ce echipamente aveți, unde sunt datele, cum ajung cererile clienților și unde se pierde timp.'],
    ['wrench', 'Construim', 'Implementăm soluțiile necesare: infrastructură, securitate, backup, website, automatizări, VITIM AI sau software dedicat.'],
    ['plug', 'Integrăm', 'Conectăm sistemele între ele, astfel încât informațiile să circule singure: de pe site în CRM, din CRM în email, din magazin în rapoarte.'],
    ['eye', 'Administrăm', 'Monitorizăm și menținem serviciile implementate: actualizări, backup verificat, securitate, suport și intervenții.'],
    ['trending', 'Optimizăm', 'Identificăm ce poate fi simplificat, automatizat sau îmbunătățit și vă propunem pașii următori în raportul lunar.'],
];
$principles = [
    ['phone', 'Un singur punct de contact', 'Suni sau scrii la VITIM pentru orice ține de tehnologie. Echipa VITIM preia, rezolvă sau coordonează.'],
    ['key', 'Acces controlat și documentat', 'Lucrăm în infrastructura și datele tale cu acces pe roluri, autentificare în doi pași și jurnal al acțiunilor.'],
    ['shield', 'Prevenim, nu doar reparăm', 'Monitorizare, actualizări și backup verificat, ca problemele să fie oprite înainte să ajungă la echipa ta.'],
    ['file', 'Raport lunar', 'Știi ce am făcut, ce am prevenit, ce urmează și cât costă. Fără costuri ascunse.'],
    ['message', 'Vorbim pe înțeles', 'Îți explicăm opțiunile fără jargon și îți spunem sincer ce nu merită făcut.'],
    ['map', 'Aproape de tine', 'Din Târgu Mureș: intervenții la sediu în Mureș, Bistrița-Năsăud și Alba și suport remote în toată țara.'],
];
echo View::partial('site/partials/page_hero', [
    'crumbs' => [['Despre noi', '/despre-noi']],
    'eyebrow' => icon('layers') . ' Despre VITIM',
    'title' => 'Tehnologia companiei tale, administrată ca un singur sistem.',
    'lead' => 'VITIM ajută companiile să își administreze infrastructura IT, securitatea, automatizările, inteligența artificială și prezența digitală printr-un singur partener tehnologic.',
    'actions' => '<a class="btn btn-primary btn-lg" href="' . e(url('/contact')) . '">Solicită o evaluare ' . icon('arrow-right', 'ico ico-move') . '</a> <a class="btn btn-ghost btn-lg" href="' . e(url('/proiecte')) . '">Vezi proiectele</a>',
]);
?>
<section class="section">
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="eyebrow">De ce existăm</span>
      <h2>O companie nu ar trebui să coordoneze singură șapte furnizori</h2>
      <p>Când fiecare se ocupă de bucata lui, nimeni nu răspunde pentru întreg. Informațiile se pierd între sisteme, iar tu devii coordonatorul tuturor.</p>
    </div>
    <div class="eco" data-reveal>
      <div class="eco-side eco-before">
        <p class="eco-label">Fără un partener</p>
        <ul class="eco-vendors">
          <?php foreach ($vendors as [$ic, $label]): ?><li><?= icon($ic) ?><span><?= e($label) ?></span><small>contact separat</small></li><?php endforeach; ?>
        </ul>
        <p class="eco-foot"><?= icon('alert') ?> Tu coordonezi, tu cauți cine răspunde.</p>
      </div>
      <div class="eco-arrow" aria-hidden="true"><?= icon('arrow-right') ?></div>
      <div class="eco-side eco-after">
        <p class="eco-label">Cu VITIM</p>
        <div class="eco-hub">
          <div class="eco-core"><?= \App\Core\Site::wordmarkSvg('eco-mark') ?><small>un singur ecosistem</small></div>
          <ul>
            <?php foreach (['Infrastructură IT', 'Backup', 'Website', 'Marketing', 'CRM', 'Automatizări', 'VITIM AI'] as $l): ?><li><?= icon('check') ?><?= e($l) ?></li><?php endforeach; ?>
          </ul>
        </div>
        <p class="eco-foot ok"><?= icon('check-circle') ?> Un singur partener răspunde pentru întreg.</p>
      </div>
    </div>
    <p class="eco-msg" data-reveal><strong>VITIM conectează toate aceste componente într-un singur ecosistem.</strong></p>
  </div>
</section>

<section class="section bg-alt" id="cum-lucram">
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="eyebrow">Cum lucrăm</span>
      <h2>Analizăm, construim, integrăm, administrăm, optimizăm</h2>
    </div>
    <ol class="steps5">
      <?php foreach ($steps as $i => [$ic, $t, $txt]): ?>
      <li data-reveal data-delay="<?= $i * 70 ?>"><span class="steps5-n mono">0<?= $i + 1 ?></span><span class="steps5-ic"><?= icon($ic) ?></span><h3><?= e($t) ?></h3><p><?= e($txt) ?></p></li>
      <?php endforeach; ?>
    </ol>
    <blockquote class="statement" data-reveal>Nu livrăm doar servicii separate. Construim un sistem tehnologic care trebuie să funcționeze împreună.</blockquote>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="eyebrow">Ce administrează echipa VITIM</span>
      <h2>Trei direcții, un singur responsabil</h2>
    </div>
    <div class="about-dirs">
      <a class="about-dir" href="<?= e(url('/servicii/mentenanta-it')) ?>" data-reveal><?= icon('server') ?><h3>IT & securitate</h3><p>Calculatoare, servere, rețea, backup, Microsoft 365, securitate, suport remote și intervenții la sediu.</p><span class="more">Mentenanță IT <?= icon('arrow-up-right') ?></span></a>
      <a class="about-dir star" href="<?= e(url('/vitim-ai')) ?>" data-reveal data-delay="70"><?= icon('sparkles') ?><h3>VITIM AI & automatizări</h3><p>Platforma noastră: conversații, lead-uri, oferte și procese, conectate și urmărite într-un singur loc.</p><span class="more">Platforma VITIM AI <?= icon('arrow-up-right') ?></span></a>
      <a class="about-dir" href="<?= e(url('/servicii/site-uri-web')) ?>" data-reveal data-delay="140"><?= icon('trending') ?><h3>Web & creștere digitală</h3><p>Website-uri, magazine online, aplicații, SEO, Google Ads, Meta Ads și măsurarea a ceea ce aduce clienți.</p><span class="more">Website-uri și magazine <?= icon('arrow-up-right') ?></span></a>
    </div>
  </div>
</section>

<section class="section bg-alt">
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="eyebrow">Cum ne asumăm responsabilitatea</span>
      <h2>Înainte să ne dai acces la infrastructura și datele companiei, vrei să știi cum lucrăm</h2>
    </div>
    <div class="principles">
      <?php foreach ($principles as $i => [$ic, $t, $txt]): ?>
      <div class="principle" data-reveal data-delay="<?= ($i % 3) * 70 ?>"><?= icon($ic) ?><div><h3><?= e($t) ?></h3><p><?= e($txt) ?></p></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if (!empty($projects)): ?>
<section class="section">
  <div class="container">
    <div class="svc-group-head" data-reveal>
      <div><span class="eyebrow">Judecă-ne după proiecte</span><h2 style="margin:0">Ce am construit și ce administrăm</h2></div>
      <a class="btn btn-ghost" href="<?= e(url('/proiecte')) ?>">Toate proiectele <?= icon('arrow-right', 'ico ico-move') ?></a>
    </div>
    <div class="about-shots">
      <?php foreach ($projects as $p): ?>
      <a class="about-shot" href="<?= e(url('/proiecte/' . $p['slug'])) ?>" data-reveal>
        <?= View::partial('site/partials/showcase', ['p' => $p, 'size' => 'sm']) ?>
        <span><b><?= e($p['client']) ?></b><?= e($p['tag'] ?? '') ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (trim(strip_tags($body)) !== ''): ?>
<section class="section-sm"><div class="container" style="max-width:880px"><article class="prose" data-reveal><?= $body ?></article></div></section>
<?php endif; ?>

<?php if ($testimonials): ?>
<section class="section"><div class="container"><div class="section-head" data-reveal><span class="eyebrow">Clienți</span><h2>Ce spun clienții</h2></div><?= View::partial('site/partials/testimonials', ['items' => $testimonials]) ?></div></section>
<?php endif; ?>
<section class="section"><div class="container"><?= View::partial('site/partials/cta_form', ['title' => 'Vrei un singur partener pentru tehnologia companiei tale?', 'text' => 'Spune-ne cum lucrează firma ta acum. Echipa VITIM îți arată ce putem administra, securiza și automatiza.']) ?></div></section>
