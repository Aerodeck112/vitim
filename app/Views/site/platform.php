<?php
use App\Core\View;

/* Statusul fiecărei funcții: 'ok' = disponibil acum, 'req' = la cerere (implementare VITIM), 'dev' = în dezvoltare. */
$badge = fn(string $s): string => match ($s) {
    'ok' => '<span class="st st-ok">Disponibil</span>',
    'req' => '<span class="st st-req">La cerere</span>',
    default => '<span class="st st-dev">În dezvoltare</span>',
};
$channels = [['globe', 'Website', 'ok'], ['whatsapp', 'WhatsApp', 'ok'], ['mail', 'Email', 'ok'], ['message', 'SMS', 'ok'], ['facebook', 'Facebook / Instagram', 'req']];
$pipeline = [
    ['Lead nou', [['Andrei M.', 'Termopane SRL', '3.200 RON', 'Website', 'acum 12 min']]],
    ['Contactat', [['Ioana T.', 'Clinica Nova', '1.800 RON', 'WhatsApp', 'azi, 09:40']]],
    ['Calificat', [['Ion Popescu', 'Popescu Logistic', '4.500 RON', 'Google Ads', 'acum 2 ore']]],
    ['Ofertă', [['Mihai R.', 'Auto Rapid', '6.900 RON', 'Email', 'ieri'], ['Elena D.', 'Dent Plus', '2.100 RON', 'Website', 'acum 3 zile']]],
    ['Câștigat', [['Radu V.', 'Mobila Lux', '5.400 RON', 'Recomandare', 'luni']]],
    ['Pierdut', [['Sorin B.', 'Construct SB', '900 RON', 'Website', 'acum 9 zile']]],
];
$roles = [
    ['headset', 'Reception AI', ['răspunde clienților', 'oferă informații', 'face programări', 'direcționează conversațiile'], 'Disponibil acum: răspunsuri din informațiile firmei, preluarea datelor, transfer către operator. Programările: la cerere.'],
    ['target', 'Sales AI', ['califică lead-uri', 'pregătește informațiile pentru ofertă', 'face follow-up', 'detectează oportunitățile uitate'], 'Disponibil acum: lead-uri în CRM cu rezumat, follow-up prin automatizări. Pregătirea ofertei: la cerere.'],
    ['megaphone', 'Marketing AI', ['segmentează contactele', 'pregătește campanii', 'trimite mesaje', 'solicită review-uri'], 'Disponibil acum: segmente, campanii pe email, SMS și WhatsApp, mesaje automate după comandă.'],
    ['layers', 'Internal AI', ['caută în documentele companiei', 'proceduri și produse', 'prețuri și contracte', 'întrebări frecvente'], 'În dezvoltare: asistent pentru echipa ta, cu acces doar la documentele permise.'],
];
$sources = [
    ['globe', 'Informațiile firmei', 'servicii, prețuri, program, FAQ', 'Sincronizat', 'ok'],
    ['shopping', 'WooCommerce', '153 produse', 'Sincronizat', 'ok'],
    ['file', 'Documente', '32 fișiere', 'Sincronizat', 'ok'],
    ['tag', 'Catalog prețuri', 'actualizat acum 18 zile', 'Necesită verificare', 'warn'],
];
$execute = [
    ['search', 'Identifică clientul', 'Client existent: Popescu Logistic'],
    ['check', 'Verifică datele', 'Produse, stoc și prețuri'],
    ['inbox', 'Creează lead', 'În CRM, etapa „Ofertă”'],
    ['edit', 'Generează oferta', '20 de produse, condiții standard'],
    ['download', 'Generează PDF', 'Ofertă cu antetul firmei'],
    ['send', 'Trimite email', 'Către client, din adresa firmei'],
    ['users', 'Notifică vânzările', 'Rezumat pentru colegul responsabil'],
    ['calendar', 'Programează follow-up', 'Peste 2 zile, dacă nu răspunde'],
];
$industries = [
    ['VITIM AI Dental', ['programări', 'recall', 'confirmări', 'lead-uri', 'review-uri']],
    ['VITIM AI Vet', ['programări', 'pacienți', 'notificări', 'documente', 'follow-up']],
    ['VITIM AI Auto', ['programări service', 'status vehicul', 'reminder', 'ofertare', 'follow-up']],
    ['VITIM AI Optică', ['programări', 'produse', 'disponibilitate', 'recall', 'campanii']],
    ['VITIM AI E-commerce', ['comenzi', 'coș abandonat', 'produse', 'suport', 'review-uri']],
    ['VITIM AI B2B', ['lead-uri', 'ofertare', 'follow-up', 'CRM', 'automatizare']],
];
$recover = [
    ['inbox', 'Lead-uri uitate', 'Cereri la care nu a răspuns nimeni.', 'dev'],
    ['file', 'Oferte fără răspuns', 'Follow-up automat după ofertă.', 'ok'],
    ['shopping', 'Coșuri abandonate', 'Email cu link care reface coșul.', 'ok'],
    ['users', 'Clienți vechi', 'Clienți care riscă să plece, recâștigați prin mesaje.', 'ok'],
    ['calendar', 'Programări ratate', 'Reprogramare propusă automat.', 'req'],
    ['refresh', 'Reînnoiri', 'Abonamente și contracte care expiră.', 'req'],
];
$intOk = [['WordPress', 'modul VITIM'], ['WooCommerce', 'comenzi, produse, coș'], ['Website (orice platformă)', 'cod de instalare'], ['Email', 'SMTP: adresa firmei, inclusiv Gmail și Microsoft 365'], ['WhatsApp Business', 'contul firmei'], ['SMS', 'furnizor românesc']];
$intReq = ['Gmail și Outlook (căsuța de email)', 'Google Calendar', 'Microsoft 365', 'Facebook și Instagram', 'Google Ads', 'GA4', 'SmartBill', 'Oblio', 'CRM existent', 'ERP', 'API custom'];
$log = [
    ['14:32', 'Sales AI a creat lead-ul', 'Popescu Logistic · Mentenanță IT'],
    ['14:33', 'Email trimis', 'Confirmare cerere + întrebări pentru ofertă'],
    ['14:35', 'Oferta a fost deschisă', 'de 2 ori, de pe telefon'],
    ['15:02', 'CRM actualizat', 'Etapa: Ofertă · valoare 4.500 RON'],
    ['17:30', 'Follow-up programat', 'mâine, 10:00'],
];
$ask = ['Câte lead-uri au venit săptămâna asta?', 'Ce clienți trebuie sunați azi?', 'Ce oferte nu au primit răspuns?', 'Care sunt cele mai importante oportunități?', 'Trimite follow-up clienților care nu au răspuns.'];

echo View::partial('site/partials/page_hero', [
    'crumbs' => [['VITIM AI', '/vitim-ai']],
    'eyebrow' => icon('sparkles') . ' VITIM AI · platformă software',
    'title' => 'AI-ul care lucrează cu datele și procesele companiei tale.',
    'lead' => 'Răspunde clienților, califică lead-uri, urmărește oportunități, automatizează procese și execută acțiuni în sistemele companiei tale.',
    'actions' => '<a class="btn btn-primary btn-lg" href="#in-actiune">Vezi VITIM AI în acțiune ' . icon('arrow-right', 'ico ico-move') . '</a> <a class="btn btn-ghost btn-lg" href="#demo">Solicită demo</a>',
]);
?>
<section class="section-sm pf-first">
  <div class="container pf-hero">
    <div class="pf-hero-copy" data-reveal>
      <p class="pf-tagline">Angajatul digital care nu uită niciun client.</p>
      <p class="muted">VITIM AI conectează conversațiile, lead-urile, datele și procesele companiei într-un singur loc. Echipa VITIM îl configurează, îl conectează la sistemele tale și îl administrează.</p>
      <ul class="pf-measure" aria-label="Ce urmărește VITIM AI">
        <?php foreach (['Lead-uri', 'Oferte', 'Vânzări', 'Valoare oportunități', 'Lead-uri recuperate', 'Timp economisit', 'Conversații rezolvate', 'Programări', 'Venit recuperat'] as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
      </ul>
      <p class="pf-legend"><?= $badge('ok') ?> funcționează acum <?= $badge('req') ?> implementăm pentru firma ta <?= $badge('dev') ?> urmează în platformă</p>
    </div>
    <div data-reveal data-delay="100"><?= View::partial('site/partials/ai_dashboard') ?></div>
  </div>
</section>

<section class="section" id="in-actiune">
  <div class="container">
    <div class="section-head" data-reveal><span class="eyebrow">Inbox central</span><h2>Toate conversațiile, într-un singur loc</h2>
      <p>Website, WhatsApp, email și SMS ajung în același inbox. Fiecare conversație are lângă ea profilul clientului și pasul următor recomandat.</p></div>
    <ul class="pf-channels" data-reveal>
      <?php foreach ($channels as [$ic, $l, $st]): ?><li><?= icon($ic) ?><?= e($l) ?><?= $badge($st) ?></li><?php endforeach; ?>
    </ul>
    <div class="inbox" data-reveal role="img" aria-label="Exemplu de inbox VITIM AI, cu date demonstrative">
      <aside class="ib-list">
        <div class="ib-h">Conversații <span>12</span></div>
        <?php foreach ([['whatsapp', 'Ion Popescu', 'Mulțumesc, aștept oferta…', '2 h', true], ['globe', 'Maria C.', 'Aveți program sâmbătă?', '5 min', false], ['mail', 'Auto Rapid', 'Re: Ofertă mentenanță', '1 h', false], ['message', 'Dan P.', 'Confirm programarea', 'ieri', false]] as [$ic, $n, $m, $t, $on]): ?>
        <div class="ib-item<?= $on ? ' on' : '' ?>"><?= icon($ic) ?><div><b><?= e($n) ?></b><span><?= e($m) ?></span></div><small><?= e($t) ?></small></div>
        <?php endforeach; ?>
      </aside>
      <div class="ib-chat">
        <div class="ib-h"><?= icon('whatsapp') ?> Ion Popescu <small>WhatsApp</small></div>
        <div class="ib-msgs">
          <p class="m in">Bună ziua, avem 14 calculatoare și un server. Cât ar costa mentenanța lunară?</p>
          <p class="m ai"><small>VITIM AI</small>Bună ziua! Abonamentele pornesc de la 1.000 RON/lună, iar prețul exact îl stabilim după o evaluare scurtă. Vă pot programa o discuție mâine la 10:00?</p>
          <p class="m in">Da, mulțumesc, aștept și oferta pe email.</p>
          <p class="m sys"><?= icon('check') ?> Lead creat · Ofertă trimisă · Follow-up programat</p>
        </div>
      </div>
      <aside class="ib-profile">
        <div class="ib-h">Profil client</div>
        <dl>
          <div><dt>Sursă</dt><dd>Google Ads</dd></div>
          <div><dt>Interes</dt><dd>Mentenanță IT</dd></div>
          <div><dt>Status</dt><dd><span class="pill-s">Ofertă trimisă</span></dd></div>
          <div><dt>Valoare</dt><dd>4.500 RON</dd></div>
          <div><dt>Ultima interacțiune</dt><dd>acum 2 ore</dd></div>
        </dl>
        <div class="ib-reco"><small><?= icon('sparkles') ?> Recomandare AI</small><p>Clientul a deschis oferta de două ori. Recomand follow-up.</p><span class="ad-btn"><?= icon('send') ?> Trimite follow-up</span></div>
      </aside>
      <span class="ad-demo ib-demo">Date demonstrative</span>
    </div>
  </div>
</section>

<section class="section bg-alt">
  <div class="container">
    <div class="section-head" data-reveal><span class="eyebrow">CRM vizual <?= $badge('ok') ?></span><h2>VITIM AI nu doar răspunde. Urmărește clientul prin procesul de vânzare.</h2>
      <p>Fiecare cerere devine o oportunitate cu valoare, sursă și istoric. Vezi dintr-o privire unde este fiecare client.</p></div>
    <div class="crm" data-reveal role="img" aria-label="Exemplu de pipeline de vânzări, cu date demonstrative">
      <?php foreach ($pipeline as $i => [$stage, $cards]): ?>
      <div class="crm-col<?= $stage === 'Câștigat' ? ' won' : ($stage === 'Pierdut' ? ' lost' : '') ?>">
        <div class="crm-h"><?= e($stage) ?><span><?= count($cards) ?></span></div>
        <?php foreach ($cards as [$n, $co, $v, $src, $last]): ?>
        <div class="crm-card"><b><?= e($n) ?></b><span><?= e($co) ?></span><div class="crm-meta"><strong><?= e($v) ?></strong><small><?= e($src) ?></small></div><small class="crm-last"><?= icon('clock') ?> <?= e($last) ?></small></div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="demo-note">Date demonstrative. Etapele se pot adapta procesului de vânzare al firmei tale.</p>
  </div>
</section>

<section class="section">
  <div class="container pf-split">
    <div data-reveal>
      <span class="eyebrow">Nu doar răspunde</span>
      <h2>Nu doar răspunde. Execută.</h2>
      <p class="muted" style="font-size:1.08rem">Un client cere o ofertă. VITIM AI parcurge singur pașii pe care altfel îi face cineva din firmă, în sistemele pe care le folosiți deja.</p>
      <div class="exec-client"><small>Client</small>„Vreau ofertă pentru 20 de produse.”</div>
      <p class="demo-note" style="text-align:left">Exemplu de flux. Îl construim pentru firma ta <?= $badge('req') ?></p>
    </div>
    <ol class="exec" data-reveal data-delay="100" aria-label="Pașii executați de VITIM AI">
      <?php foreach ($execute as $i => [$ic, $t, $d]): ?>
      <li style="--d:<?= $i ?>"><span class="exec-ic"><?= icon($ic) ?></span><div><b><?= e($t) ?></b><small><?= e($d) ?></small></div><span class="exec-ok"><?= icon('check') ?></span></li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<section class="section bg-alt">
  <div class="container">
    <div class="section-head" data-reveal><span class="eyebrow">Roluri AI</span><h2>Roluri AI pentru compania ta</h2>
      <p>Roluri de lucru, nu personaje. Fiecare are sarcini clare și acces doar la ce are nevoie.</p></div>
    <div class="roles">
      <?php foreach ($roles as $i => [$ic, $name, $tasks, $status]): ?>
      <article class="role" data-reveal data-delay="<?= ($i % 2) * 80 ?>">
        <div class="role-h"><span class="role-ic"><?= icon($ic) ?></span><h3><?= e($name) ?></h3></div>
        <ul><?php foreach ($tasks as $t): ?><li><?= icon('check') ?><?= e($t) ?></li><?php endforeach; ?></ul>
        <p class="role-st"><?= e($status) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container pf-split">
    <div data-reveal>
      <span class="eyebrow">Informațiile companiei</span>
      <h2>AI-ul răspunde folosind informațiile companiei tale</h2>
      <p class="muted" style="font-size:1.08rem"><strong style="color:var(--text)">VITIM AI nu trebuie să ghicească. Lucrează cu informațiile companiei tale.</strong> Când nu știe ceva, nu inventează: preia datele clientului și transferă conversația unui coleg.</p>
      <div class="src-av">
        <p><?= $badge('ok') ?> informațiile firmei aprobate de tine, catalogul WooCommerce, website-ul</p>
        <p><?= $badge('req') ?> PDF, Word, Excel, Google Drive, CRM, ERP, proceduri interne, liste de prețuri, documentație</p>
      </div>
    </div>
    <div class="sources" data-reveal data-delay="100" role="img" aria-label="Exemplu de surse de informații sincronizate, cu date demonstrative">
      <div class="src-h">Surse de informații <span class="ad-demo">Exemplu</span></div>
      <?php foreach ($sources as [$ic, $n, $d, $st, $c]): ?>
      <div class="src-row"><?= icon($ic) ?><div><b><?= e($n) ?></b><small><?= e($d) ?></small></div><span class="src-st <?= $c ?>"><?= e($st) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section bg-alt">
  <div class="container">
    <div class="section-head" data-reveal><span class="eyebrow">Control <?= $badge('dev') ?></span><h2>Tu controlezi ce poate face AI-ul</h2>
      <p>Astăzi, asistentul VITIM AI răspunde, preia date și transferă conversația unui coleg, iar campaniile pleacă doar după aprobarea ta. Nivelurile de autonomie de mai jos sunt în dezvoltare.</p></div>
    <div class="modes" data-reveal>
      <div class="mode"><span class="mode-n">Manual</span><p>AI propune.<br>Omul aprobă.</p></div>
      <div class="mode on"><span class="mode-n">Semi-automat</span><p>AI execută sarcinile cu risc redus.<br>Cere aprobare pentru acțiunile importante.</p></div>
      <div class="mode"><span class="mode-n">Autopilot</span><p>AI execută singur, în limite prestabilite.</p></div>
    </div>
    <div class="rule" data-reveal>
      <div><small>Regulă</small><b>AI poate aplica maximum 5% discount.</b></div>
      <div class="rule-arrow"><?= icon('arrow-right') ?></div>
      <div><small>Peste 5%</small><b>Necesită aprobarea administratorului.</b></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head" data-reveal><span class="eyebrow">Automatizări <?= $badge('ok') ?></span><h2>Automatizări construite din blocuri simple</h2>
      <p>Când se întâmplă ceva, dacă e îndeplinită o condiție, VITIM AI face pașii stabiliți. Fără cod, fără scheme complicate.</p></div>
    <div class="builder" data-reveal role="img" aria-label="Exemplu de automatizare: lead nou de pe website pentru mentenanță IT">
      <div class="bl bl-when"><small>WHEN</small><b>Lead nou de pe website</b></div>
      <div class="bl-line" aria-hidden="true"></div>
      <div class="bl bl-if"><small>IF</small><b>Serviciu = Mentenanță IT</b></div>
      <div class="bl-line" aria-hidden="true"></div>
      <div class="bl bl-then"><small>THEN</small>
        <ol><?php foreach (['creare contact', 'calificare AI', 'email automat', 'notificare', 'creare task', 'follow-up după 24h'] as $s): ?><li><?= icon('arrow-right') ?><?= e($s) ?></li><?php endforeach; ?></ol>
      </div>
    </div>
    <p class="demo-note">Disponibil acum: declanșatoare (formular, cerere, comandă, coș, listă), condiții, emailuri, SMS, WhatsApp, pauze. Pașii personalizați (calificare AI, task-uri) îi implementăm la cerere.</p>
  </div>
</section>

<section class="section bg-alt">
  <div class="container">
    <div class="section-head" data-reveal><span class="eyebrow">Raport zilnic <?= $badge('dev') ?></span><h2>În fiecare dimineață știi ce s-a întâmplat</h2></div>
    <div class="brief-grid">
      <div class="brief" data-reveal role="img" aria-label="Exemplu de raport zilnic VITIM AI, cu date demonstrative">
        <div class="brief-h"><b>VITIM AI · Daily brief</b><span class="ad-demo">Date demonstrative</span></div>
        <p class="brief-k">Ieri</p>
        <ul class="brief-list">
          <?php foreach ([['24', 'lead-uri'], ['6', 'oferte'], ['2', 'vânzări'], ['4', 'conversații necesită intervenție'], ['7', 'programări astăzi'], ['1', 'client nemulțumit detectat']] as [$n, $l]): ?><li><b><?= $n ?></b><?= e($l) ?></li><?php endforeach; ?>
        </ul>
      </div>
      <div class="reco" data-reveal data-delay="100">
        <h3>Ce recomandă VITIM AI astăzi?</h3>
        <ul>
          <?php foreach ([['alert', 'Client nemulțumit', 'Ton negativ în ultima conversație.', 'Escaladează către operator'], ['trending', 'Lead foarte interesat', 'A deschis oferta de 3 ori.', 'Sună azi'], ['star', 'Oportunitate importantă', 'Cea mai mare valoare din pipeline.', 'Prioritizează'], ['message', 'Posibilă reclamație', 'Mesaj despre o problemă la livrare.', 'Escaladează către operator'], ['clock', 'Client care așteaptă prea mult', 'Fără răspuns de 26 de ore.', 'Răspunde acum']] as [$ic, $t, $d, $a]): ?>
          <li><?= icon($ic) ?><div><b><?= e($t) ?></b><small><?= e($d) ?></small></div><span><?= e($a) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head" data-reveal><span class="eyebrow">Oportunități</span><h2>Recuperează oportunitățile care altfel se pierd</h2>
      <p>VITIM AI identifică automat oportunitățile care riscă să fie uitate și pornește pasul următor.</p></div>
    <div class="recover">
      <?php foreach ($recover as $i => [$ic, $t, $d, $st]): ?>
      <div class="rec" data-reveal data-delay="<?= ($i % 3) * 70 ?>"><?= icon($ic) ?><h3><?= e($t) ?></h3><p><?= e($d) ?></p><?= $badge($st) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section bg-alt">
  <div class="container">
    <div class="section-head" data-reveal><span class="eyebrow">Pe industrii</span><h2>VITIM AI adaptat industriei tale</h2>
      <p>Aceeași platformă, configurată pe fluxurile tipice ale domeniului tău.</p></div>
    <div class="inds">
      <?php foreach ($industries as $i => [$n, $items]): ?>
      <div class="ind" data-reveal data-delay="<?= ($i % 3) * 70 ?>"><h3><?= e($n) ?></h3><ul><?php foreach ($items as $it): ?><li><?= e($it) ?></li><?php endforeach; ?></ul></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head" data-reveal><span class="eyebrow">Integrări</span><h2>Conectează sistemele companiei tale</h2>
      <p>Spunem clar ce funcționează acum și ce implementăm pentru firma ta.</p></div>
    <div class="ints" data-reveal>
      <div class="int-col">
        <p class="int-h"><?= $badge('ok') ?></p>
        <ul class="int-ok"><?php foreach ($intOk as [$n, $d]): ?><li><b><?= e($n) ?></b><small><?= e($d) ?></small></li><?php endforeach; ?></ul>
      </div>
      <div class="int-col">
        <p class="int-h"><?= $badge('req') ?> poate fi integrat la cerere</p>
        <ul class="int-req"><?php foreach ($intReq as $n): ?><li><?= e($n) ?></li><?php endforeach; ?></ul>
      </div>
    </div>
  </div>
</section>

<section class="section bg-alt">
  <div class="container pf-split">
    <div data-reveal>
      <span class="eyebrow">Control · transparență · trasabilitate</span>
      <h2>Știi exact ce a făcut AI-ul</h2>
      <p class="muted" style="font-size:1.08rem">Fiecare acțiune apare în istoricul clientului și în jurnalul firmei: cine, ce, când.</p>
      <div class="roles-acc">
        <p><b>Roluri de acces</b></p>
        <p><?= $badge('ok') ?> Proprietar · Administrator · Operator · Doar vizualizare</p>
        <p><?= $badge('req') ?> Manager · Vânzări · Marketing</p>
      </div>
      <ul class="sec-list">
        <?php foreach (['Consimțământ înregistrat, cu dovadă', 'Dezabonare dintr-un click', 'Jurnal de acțiuni (audit log)', 'Roluri și control al accesului', 'Autentificare în doi pași', 'Datele fiecărei firme separate'] as $m): ?><li><?= icon('check-circle') ?><?= e($m) ?></li><?php endforeach; ?>
      </ul>
      <p class="demo-note" style="text-align:left">Funcționalități pentru gestionarea cerințelor GDPR: mecanisme pentru consimțământ, trasabilitate și controlul accesului.</p>
    </div>
    <div class="log" data-reveal data-delay="100" role="img" aria-label="Exemplu de jurnal al acțiunilor VITIM AI, cu date demonstrative">
      <div class="src-h">Jurnal de activitate <span class="ad-demo">Exemplu</span></div>
      <ol><?php foreach ($log as [$t, $a, $d]): ?><li><time><?= $t ?></time><div><b><?= e($a) ?></b><small><?= e($d) ?></small></div></li><?php endforeach; ?></ol>
    </div>
  </div>
</section>

<section class="section">
  <div class="container pf-split">
    <div class="askbox" data-reveal role="img" aria-label="Exemplu: întrebări puse platformei VITIM AI">
      <div class="src-h">Întreabă VITIM AI <span class="ad-demo">Exemplu</span></div>
      <div class="ask-q"><?php foreach ($ask as $q): ?><span><?= e($q) ?></span><?php endforeach; ?></div>
      <div class="ask-chat">
        <p class="m me">Ce oferte nu au primit răspuns?</p>
        <p class="m ai"><small>VITIM AI</small>3 oferte, în valoare totală de 11.500 RON. Cea mai veche: Auto Rapid, trimisă acum 6 zile. Trimit follow-up tuturor?</p>
        <p class="m me">Da.</p>
        <p class="m sys"><?= icon('check') ?> 3 emailuri de follow-up trimise · CRM actualizat</p>
      </div>
    </div>
    <div data-reveal data-delay="100">
      <span class="eyebrow">Întreabă VITIM AI <?= $badge('dev') ?></span>
      <h2>Întrebi în română. Primești răspunsul. Dacă are permisiunea, execută.</h2>
      <p class="muted" style="font-size:1.08rem">Fără rapoarte căutate prin meniuri. Întrebi ce vrei să știi despre clienți, oferte și oportunități, iar VITIM AI răspunde din datele firmei. <strong style="color:var(--text)">Dacă are permisiunea, VITIM AI nu doar răspunde: execută acțiunea.</strong></p>
    </div>
  </div>
</section>

<section class="section bg-alt">
  <div class="container">
    <div class="section-head" data-reveal><span class="eyebrow">Administrat de echipa VITIM</span><h2>Nu primești doar un program. Primești platforma configurată și administrată</h2></div>
    <div class="steps">
      <?php foreach ([['Analizăm', 'Vedem cum ajung clienții la voi, unde se pierd cererile și ce sarcini se repetă.'], ['Configurăm', 'Conectăm site-ul, emailul, WhatsApp-ul și magazinul și pregătim informațiile firmei.'], ['Lansăm', 'Verificăm fiecare mesaj înainte să plece și ajustăm răspunsurile după conversațiile reale.'], ['Administrăm', 'Îmbunătățim automatizările, propunem campanii și raportăm lunar ce a adus platforma.']] as $i => [$t, $d]): ?>
      <div class="step" data-reveal data-delay="<?= $i * 80 ?>"><h3><?= e($t) ?></h3><p><?= e($d) ?></p></div>
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

<section class="section" id="demo"><div class="container"><?= View::partial('site/partials/cta_form', ['title' => 'Solicită demo VITIM AI', 'text' => 'Spune-ne cum ajung acum clienții la voi și ce sarcini se repetă. Îți arătăm VITIM AI pe exemplul firmei tale și îți spunem sincer ce merită automatizat.', 'service' => 'ai-pentru-firme']) ?></div></section>
