<?php
use App\Core\View;

$modules = [
    ['bot', 'Asistent AI pe site, 24/7', 'Răspunde vizitatorilor în română, din informațiile firmei tale: servicii, prețuri orientative, program, zone. Preia datele celor interesați și îți trimite cererea gata rezumată.'],
    ['message', 'Chat live cu echipa ta', 'Vezi conversațiile în timp real și preiei discuția dintr-un click când vrei să răspunzi tu. Clientul vede prenumele colegului, nu un robot.'],
    ['inbox', 'Contacte și cereri într-un singur loc', 'Fiecare client are fișa lui: conversații, cereri de ofertă, emailuri primite și deschise, comenzi, acorduri. Nimic nu se mai pierde în telefon sau în email.'],
    ['send', 'Campanii pe email, SMS și WhatsApp', 'Trimiți oferte și noutăți din adresa firmei, din contul tău de SMS și de WhatsApp Business, doar către cei care și-au dat acordul, cu previzualizare și test înainte.'],
    ['workflow', 'Automatizări care vând singure', 'Bun venit pentru abonații noi, reamintire după o cerere de ofertă, coș abandonat, mulțumire după comandă, recâștigarea clienților inactivi, urări de ziua lor.'],
    ['edit', 'Emailuri frumoase, fără designer', 'Editor vizual cu blocuri: logo, imagini, butoane, produse din magazin. Brandul firmei se aplică automat, iar emailul arată bine și pe telefon.'],
    ['target', 'Formulare care strâng abonați', 'Popup, casetă în colț, bară sau formular în pagină, cu cod de reducere și dublă confirmare. Abonații intră direct în listă și primesc emailul de bun venit.'],
    ['shopping', 'Magazinul online, conectat', 'Pentru WooCommerce: coș abandonat cu link care reface coșul, produsele văzute, comenzile și venitul adus de fiecare email, direct în panou.'],
    ['chart', 'Rezultate în lei, nu doar în clickuri', 'Vezi câți bani aduce marketingul, ce campanii merg, la ce oră deschid clienții emailurile și care clienți riscă să plece.'],
    ['shield', 'Site îngrijit și în siguranță', 'Monitorizare, actualizări, backup verificat, probleme de securitate și SEO rezolvate de la distanță, plus un raport lunar cu tot ce am făcut.'],
    ['lock', 'Conform cu legislația din România', 'Banner de cookie-uri cu registru de consimțăminte, acord de marketing înregistrat cu dovadă, dezabonare dintr-un click, datele firmei în fiecare email.'],
    ['users', 'Echipa ta, cu roluri', 'Proprietar, administrator, operator: fiecare vede și face doar ce trebuie. Acces sigur, cu autentificare în doi pași.'],
];
$managed = [
    ['Pornim împreună', 'Discutăm despre firma ta, clienți și obiective. Facem un audit gratuit al site-ului și îți propunem ce merită activat mai întâi.'],
    ['Configurăm totul', 'Conectăm site-ul, învățăm asistentul despre firma ta, legăm emailul, SMS-ul și WhatsApp-ul, importăm contactele cu acord și pregătim primele automatizări.'],
    ['Lansăm și urmărim', 'Verificăm fiecare mesaj înainte să plece, urmărim primele conversații și ajustăm răspunsurile asistentului după întrebările reale ale clienților.'],
    ['Administrăm lunar', 'Îngrijim site-ul, propunem campanii, îmbunătățim automatizările și îți trimitem raportul lunar: ce s-a făcut, ce a adus, ce urmează.'],
];
$unique = [
    ['Totul într-un singur loc', 'În loc de un chatbot, un CRM, un program de newsletter, un plugin de cookie-uri și o firmă de mentenanță, ai o singură platformă și un singur partener.'],
    ['Făcută pentru firmele din România', 'Interfață și asistent în română, SMS prin furnizori români, texte legale gândite pentru GDPR, Legea 506/2004 și ANPC.'],
    ['Fără taxe pe contacte', 'Trimiți din conturile firmei tale și plătești doar ce trimiți. Platformele străine cer abonamente care cresc odată cu lista ta de clienți.'],
    ['Oameni, nu doar software', 'Echipa VITIM configurează, verifică și administrează. Ai un om pe care îl suni, nu un tichet într-o coadă.'],
];
echo View::partial('site/partials/page_hero', [
    'crumbs' => [['VITIM AI', '/vitim-ai']],
    'eyebrow' => icon('sparkles') . ' Platforma VITIM AI',
    'title' => 'Firma ta răspunde, vinde și își păstrează clienții, chiar și când nu ești la birou',
    'lead' => 'VITIM AI adună într-un singur panou asistentul AI de pe site, conversațiile și cererile clienților, campaniile pe email, SMS și WhatsApp, automatizările și îngrijirea site-ului. Noi o configurăm și o administrăm, tu vezi rezultatele.',
    'actions' => '<a class="btn btn-primary btn-lg" href="#demo-ai">Testează pe site-ul tău ' . icon('arrow-right', 'ico ico-move') . '</a> <a class="btn btn-ghost btn-lg" href="' . e(url('/contact')) . '">Programează o discuție</a>',
]);
?>
<section class="section-sm">
  <div class="container split">
    <div data-reveal>
      <span class="eyebrow">De ce contează</span>
      <h2>Clienții tăi scriu seara, în weekend și când ești ocupat</h2>
      <p class="muted" style="font-size:1.08rem">O cerere la care nu răspunzi în câteva minute merge, de cele mai multe ori, la concurență. Un client care a cumpărat o dată uită de tine dacă nu-i mai scrii. VITIM AI rezolvă exact aceste două lucruri: <strong>răspunde imediat</strong> și <strong>ține legătura</strong> cu clienții, automat, în numele firmei tale.</p>
      <ul class="checklist">
        <li><?= icon('check-circle') ?><span><strong>Nicio cerere pierdută:</strong> fiecare conversație și fiecare formular ajung în același inbox, cu datele clientului.</span></li>
        <li><?= icon('check-circle') ?><span><strong>Mai multe vânzări din aceiași vizitatori:</strong> abonați noi, coșuri recuperate, clienți care revin.</span></li>
        <li><?= icon('check-circle') ?><span><strong>Timp câștigat:</strong> întrebările repetitive primesc răspuns singure, iar echipa se ocupă de clienții gata să cumpere.</span></li>
      </ul>
    </div>
    <div class="flow" data-reveal data-delay="120" aria-label="Exemplu: drumul unui client cu VITIM AI">
      <div class="flow-node"><div class="icon-tile"><?= icon('globe') ?></div><div><small>21:40 · pe site</small><strong>Un vizitator întreabă de preț și termen</strong></div><span class="status">live</span></div>
      <div class="flow-node"><div class="icon-tile"><?= icon('bot') ?></div><div><small>21:40 · asistent AI</small><strong>Răspunde din informațiile firmei și cere datele de contact</strong></div></div>
      <div class="flow-node"><div class="icon-tile"><?= icon('inbox') ?></div><div><small>21:41 · panou</small><strong>Cerere nouă, cu rezumat, la tine pe email</strong></div></div>
      <div class="flow-node"><div class="icon-tile"><?= icon('mail') ?></div><div><small>a doua zi · automatizare</small><strong>Email de follow-up, apoi SMS dacă nu a răspuns</strong></div></div>
      <div class="flow-node"><div class="icon-tile"><?= icon('handshake') ?></div><div><small>rezultat</small><strong>Client nou, păstrat cu oferte și noutăți</strong></div><span class="status">câștigat</span></div>
    </div>
  </div>
</section>

<section class="section bg-alt">
  <div class="container">
    <div class="section-head" data-reveal><span class="eyebrow">Ce primești</span><h2>Tot ce are nevoie o firmă ca să comunice cu clienții, într-o singură platformă</h2>
      <p>Activezi doar ce îți trebuie acum și adaugi restul când firma crește.</p></div>
    <div class="grid-3 varied">
      <?php foreach ($modules as $i => [$ic, $title, $text]): ?>
      <div class="card" data-reveal data-delay="<?= ($i % 3) * 70 ?>"><div class="icon-tile"><?= icon($ic) ?></div><h3><?= e($title) ?></h3><p><?= e($text) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head" data-reveal><span class="eyebrow">Cum gestionăm</span><h2>Nu îți dăm doar un program. Îl configurăm și îl administrăm pentru tine</h2>
      <p>VITIM AI vine cu echipa VITIM în spate. Tu îți vezi de clienți, noi ne ocupăm de tehnologie.</p></div>
    <div class="steps">
      <?php foreach ($managed as $i => [$title, $text]): ?>
      <div class="step" data-reveal data-delay="<?= $i * 90 ?>"><h3><?= e($title) ?></h3><p><?= e($text) ?></p></div>
      <?php endforeach; ?>
    </div>
    <div class="split" style="margin-top:clamp(40px,6vw,72px)">
      <div data-reveal>
        <h3 style="font-size:1.35rem">Ce faci tu</h3>
        <ul class="checklist">
          <li><?= icon('check-circle') ?><span>Citești cererile și răspunzi clienților (din panou, de pe calculator sau telefon).</span></li>
          <li><?= icon('check-circle') ?><span>Aprobi campaniile înainte să plece. Nimic nu se trimite fără acordul tău.</span></li>
          <li><?= icon('check-circle') ?><span>Ne spui ce e nou în firmă: produse, prețuri, oferte, program.</span></li>
        </ul>
      </div>
      <div data-reveal data-delay="120">
        <h3 style="font-size:1.35rem">Ce facem noi</h3>
        <ul class="checklist">
          <li><?= icon('check-circle') ?><span>Ținem asistentul la zi și îi corectăm răspunsurile după conversațiile reale.</span></li>
          <li><?= icon('check-circle') ?><span>Construim automatizările, emailurile și formularele, cu brandul firmei tale.</span></li>
          <li><?= icon('check-circle') ?><span>Îngrijim site-ul (actualizări, securitate, backup, SEO) și îți trimitem raportul lunar.</span></li>
        </ul>
      </div>
    </div>
  </div>
</section>

<section class="section bg-alt">
  <div class="container">
    <div class="section-head" data-reveal><span class="eyebrow">De ce VITIM AI</span><h2>Un produs construit de noi, pentru firmele de aici</h2></div>
    <div class="grid-2" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px">
      <?php foreach ($unique as $i => [$title, $text]): ?>
      <div class="card" data-reveal data-delay="<?= ($i % 2) * 70 ?>"><h3 style="margin-top:0"><?= e($title) ?></h3><p><?= e($text) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section-sm" id="demo-ai">
  <div class="container">
    <?= View::partial('site/partials/ai_demo') ?>
  </div>
</section>

<section class="section">
  <div class="container" style="max-width:880px">
    <div class="section-head" data-reveal><span class="eyebrow">Întrebări frecvente</span><h2>Ce ne întreabă firmele despre VITIM AI</h2></div>
    <?= View::partial('site/partials/faq', ['faq' => $faq]) ?>
  </div>
</section>

<section class="section"><div class="container"><?= View::partial('site/partials/cta_form', ['title' => 'Vrei să vezi VITIM AI pe firma ta?', 'text' => 'Spune-ne ce faci și ce site ai. Îți arătăm o demonstrație pe exemplul firmei tale și îți spunem sincer ce merită activat.']) ?></div></section>
