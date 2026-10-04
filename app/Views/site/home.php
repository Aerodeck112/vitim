<?php
use App\Core\Settings;
use App\Core\Site;
use App\Core\View;

$groups = Site::servicesByCategory();
$points = Settings::json('home_points');
$why = Settings::json('home_why');
$process = Settings::json('home_process');
$stats = Settings::json('home_stats');
$from = (string)setting('pricing_from', '1.000');

// cele trei direcții: categoriile de servicii existente, grupate (paginile de servicii rămân neschimbate)
$svc = fn(array $cats) => array_merge(...array_map(fn($c) => $groups[$c]['items'] ?? [], $cats));
$directions = [
    [
        'id' => 'it', 'n' => '01', 'icon' => 'server', 'title' => 'IT & securitate',
        'msg' => 'Echipa ta lucrează. Noi ne asigurăm că tehnologia funcționează.',
        'chips' => ['Mentenanță IT', 'Suport remote', 'Intervenții la sediu', 'Calculatoare și servere', 'Rețea', 'Backup', 'Securitate', 'Microsoft 365', 'NAS', 'Recuperare date', 'Infrastructură', 'Consultanță'],
        'items' => $svc(['it', 'securitate']),
        'link' => ['/servicii/mentenanta-it', 'Mentenanță IT pentru firme'],
    ],
    [
        'id' => 'ai', 'n' => '02', 'icon' => 'sparkles', 'title' => 'VITIM AI & automatizări', 'star' => true,
        'msg' => 'Firma ta răspunde, urmărește lead-urile și execută sarcinile repetitive chiar și atunci când oamenii tăi nu sunt la birou.',
        'chips' => ['Agenți AI', 'Chatbot pe datele firmei', 'Email, WhatsApp, SMS', 'CRM și ERP', 'WooCommerce', 'Documente și oferte automate', 'Clasificare lead-uri', 'Follow-up', 'n8n', 'Make', 'API și MCP', 'Integrări custom'],
        'items' => $svc(['ai']),
        'link' => ['/vitim-ai', 'Platforma VITIM AI'],
    ],
    [
        'id' => 'web', 'n' => '03', 'icon' => 'trending', 'title' => 'Web & creștere digitală',
        'msg' => 'Nu vrem doar trafic. Vrem să știm ce produce lead-uri și bani.',
        'chips' => ['Website', 'WordPress', 'WooCommerce', 'Aplicații', 'Module custom', 'SEO', 'Google Ads', 'Meta Ads', 'GA4 și tracking', 'CRO', 'Google Business Profile'],
        'items' => $svc(['marketing']),
        'link' => ['/servicii/site-uri-web', 'Website-uri și magazine online'],
    ],
];
$problems = [
    'Altcineva se ocupă de calculatoare.',
    'Altcineva se ocupă de website.',
    'Altcineva face reclamele.',
    'Nimeni nu răspunde pentru întreg.',
    'Datele sunt împrăștiate în mai multe locuri.',
    'Procesele se fac manual.',
    'Angajații pierd timp cu sarcini repetitive.',
    'Lead-urile se pierd între email, telefon și WhatsApp.',
    'Când apare o problemă, nimeni nu știe cine trebuie sunat.',
];
$oneNumber = [
    ['monitor', 'Calculatorul nu merge?'], ['mail', 'Emailul nu funcționează?'], ['hard-drive', 'Backupul e verificat?'],
    ['server', 'Serverul e lent?'], ['globe', 'Website-ul a căzut?'], ['workflow', 'Vrei o automatizare?'],
    ['bot', 'Vrei AI în firmă?'], ['target', 'De unde vin lead-urile?'],
];
$pillars = [
    ['refresh', 'Continuitate', 'Echipa lucrează fără întreruperi.'],
    ['shield', 'Securitate', 'Date protejate, backup verificat.'],
    ['zap', 'Automatizare', 'Mai puțină muncă repetitivă.'],
    ['eye', 'Control', 'Știi ce se întâmplă și cât costă.'],
    ['trending', 'Creștere', 'Lead-uri și vânzări urmărite.'],
];
?>
<section class="hero">
  <div class="hero-bg" aria-hidden="true"><div class="grid"></div><div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div></div>
  <div class="container hero-inner">
    <div>
      <span class="pill pill-price"><span class="dot"></span><?= e(setting('home_badge')) ?></span>
      <h1><?= strip_tags((string)setting('home_title'), '<span><br><em><strong>') ?></h1>
      <p class="lead"><?= e(setting('home_subtitle')) ?></p>
      <div class="hero-ctas">
        <a class="btn btn-primary btn-lg" href="#contact"><?= e(setting('home_cta_primary')) ?> <?= icon('arrow-right', 'ico ico-move') ?></a>
        <a class="btn btn-ghost btn-lg" href="#cum-lucram"><?= e(setting('home_cta_secondary')) ?></a>
      </div>
      <?php if ($points): ?>
      <ul class="hero-points">
        <?php foreach ($points as $p): ?><li><?= icon('check-circle') ?><?= e($p) ?></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
    <div class="hero-visual">
      <?php if (setting('home_hero_style', 'animatie') === 'foto' && ($heroImg = setting('home_hero_image'))): ?>
      <figure class="hero-photo">
        <img src="<?= e(upload_url((string)$heroImg)) ?>" srcset="<?= e(\App\Core\Uploader::srcset((string)$heroImg)) ?>" sizes="(max-width:1024px) 100vw, 560px" alt="<?= e(\App\Core\DB::val('SELECT alt FROM media WHERE path = ?', [$heroImg]) ?: setting('brand_name')) ?>" width="960" height="1200" fetchpriority="high">
      </figure>
      <div class="status-card">
        <div class="status-head"><span class="dot"></span> Monitorizare activă</div>
        <ul>
          <li><?= icon('check') ?> Backup verificat în această noapte</li>
          <li><?= icon('check') ?> Actualizări de securitate aplicate</li>
          <li><?= icon('check') ?> Tichet rezolvat remote</li>
        </ul>
      </div>
      <div class="float-card fc2"><?= icon('headset') ?><span>Suport remote<br><small class="muted">și la sediul tău</small></span></div>
      <?php else: ?>
      <div class="console" data-console role="figure" aria-label="Exemplu: ce administrează VITIM într-o dimineață obișnuită la o firmă client">
        <div class="console-bar"><i></i><i></i><i></i><span>vitim — exemplu de dimineață la o firmă client</span></div>
        <div class="console-body">
          <div class="ln"><b>08:00</b><span><span class="ok">✓</span> Backup nocturn verificat · servere și PC-uri</span></div>
          <div class="ln"><b>08:02</b><span><span class="ok">✓</span> Actualizări de securitate aplicate</span></div>
          <div class="ln"><b>08:15</b><span><span class="warn">!</span> SSD cu uzură mare la contabilitate → înlocuire planificată</span></div>
          <div class="ln"><b>09:30</b><span><span class="ai">◆</span> VITIM AI: emailuri clasificate, oferte pregătite</span></div>
          <div class="ln"><b>10:05</b><span><span class="ai">◆</span> Lead nou de pe site → calificat → trimis în CRM</span></div>
          <div class="ln"><b>10:40</b><span><span class="ok">✓</span> Tentativă de phishing blocată · utilizator anunțat</span></div>
          <div class="ln"><b>11:12</b><span><span class="ok">✓</span> Imprimanta de la recepție rezolvată remote</span></div>
          <div class="ln"><b>›</b><span>următoarea sarcină <span class="cursor"></span></span></div>
        </div>
        <div class="console-foot">
          <div><b>IT</b><small>administrat</small></div>
          <div><b>Securitate</b><small>verificată</small></div>
          <div><b>AI</b><small>pus la lucru</small></div>
        </div>
      </div>
      <div class="float-card fc1"><?= icon('phone') ?><span>Un singur număr<br><small class="muted">pentru toată tehnologia</small></span></div>
      <div class="float-card fc2"><?= icon('sparkles') ?><span>VITIM AI integrat</span></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($clients): ?>
<section class="logo-wall-wrap" aria-labelledby="clienti-titlu">
  <div class="container">
    <p class="logo-wall-label" id="clienti-titlu">Companii care au lucrat cu VITIM</p>
    <ul class="logo-wall">
      <?php foreach ($clients as $c): ?>
      <li><a href="<?= e(url('/proiecte/' . $c['slug'])) ?>" title="<?= e($c['client']) ?> – studiu de caz">
        <?php if (!empty($c['logo'])): ?><img src="<?= e(upload_url($c['logo'])) ?>" alt="<?= e($c['client']) ?>" loading="lazy" width="160" height="48"><?php else: ?><span><?= e($c['client']) ?></span><?php endif; ?>
      </a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<section class="section" id="un-partener">
  <div class="container split split-top">
    <div data-reveal>
      <span class="eyebrow">Problema</span>
      <h2>Un singur partener pentru tehnologia firmei tale</h2>
      <p class="muted" style="font-size:1.08rem">În multe firme, tehnologia arată așa:</p>
      <ul class="problem-list">
        <?php foreach ($problems as $p): ?><li><?= icon('x') ?><span><?= e($p) ?></span></li><?php endforeach; ?>
      </ul>
      <p class="problem-answer"><strong>VITIM le leagă într-un singur sistem.</strong> Un singur partener care răspunde pentru calculatoare, date, securitate, procese, website și lead-uri.</p>
    </div>
    <div class="hub" data-reveal data-delay="120" role="img" aria-label="Schemă: calculatoarele, rețeaua, backupul, emailul, website-ul, reclamele, CRM-ul și AI-ul firmei, administrate toate de VITIM">
      <div class="hub-ring">
        <?php foreach ([['monitor', 'Calculatoare'], ['wifi', 'Rețea'], ['hard-drive', 'Backup'], ['mail', 'Email & M365'], ['globe', 'Website'], ['megaphone', 'Reclame'], ['kanban', 'CRM & ERP'], ['bot', 'AI']] as $i => [$ic, $label]): ?>
        <span class="hub-node" style="--i:<?= $i ?>"><?= icon($ic) ?><small><?= e($label) ?></small></span>
        <?php endforeach; ?>
        <div class="hub-core"><b>VITIM</b><small>un singur partener</small></div>
      </div>
    </div>
  </div>
</section>

<section class="section bg-alt" id="servicii">
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="eyebrow">Ce administrăm</span>
      <h2>Trei direcții, un singur responsabil</h2>
      <p>Alegi ce ai nevoie acum. Restul se adaugă când firma crește, fără să schimbi furnizorul.</p>
    </div>
    <div class="directions">
      <?php foreach ($directions as $i => $d): ?>
      <article class="direction<?= !empty($d['star']) ? ' star' : '' ?>" id="directie-<?= e($d['id']) ?>" data-reveal data-delay="<?= $i * 80 ?>">
        <div class="direction-head"><div class="icon-tile"><?= icon($d['icon']) ?></div><span class="mono"><?= e($d['n']) ?></span><?php if (!empty($d['star'])): ?><span class="badge-star">Produs propriu</span><?php endif; ?></div>
        <h3><?= e($d['title']) ?></h3>
        <p class="direction-msg"><?= e($d['msg']) ?></p>
        <ul class="chips-static"><?php foreach ($d['chips'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul>
        <?php if ($d['items']): ?>
        <nav class="direction-links" aria-label="Servicii <?= e($d['title']) ?>">
          <?php foreach ($d['items'] as $s): ?><a href="<?= e(url('/servicii/' . $s['slug'])) ?>"><?= e($s['title']) ?> <?= icon('arrow-up-right') ?></a><?php endforeach; ?>
        </nav>
        <?php endif; ?>
        <a class="btn <?= !empty($d['star']) ? 'btn-primary' : 'btn-ghost' ?> direction-cta" href="<?= e(url($d['link'][0])) ?>"><?= e($d['link'][1]) ?> <?= icon('arrow-right', 'ico ico-move') ?></a>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" id="un-numar">
  <div class="container">
    <div class="one-number" data-reveal>
      <div class="one-number-head">
        <span class="eyebrow">Ideea centrală</span>
        <h2>Un singur număr pentru tehnologia firmei tale</h2>
        <p class="muted" style="font-size:1.08rem">Când apare o problemă tehnică, nu mai cauți cine se ocupă. <strong style="color:var(--text)">Suni VITIM.</strong></p>
        <a class="one-number-phone" href="<?= e(phone_href((string)setting('phone'))) ?>" data-loc="un-numar"><?= icon('phone') ?><span><?= e(setting('phone')) ?></span></a>
      </div>
      <ul class="qa-list">
        <?php foreach ($oneNumber as [$ic, $q]): ?><li><?= icon($ic) ?><span><?= e($q) ?></span><b>VITIM</b></li><?php endforeach; ?>
      </ul>
    </div>
    <ul class="pillars" data-reveal>
      <?php foreach ($pillars as [$ic, $t, $txt]): ?><li><?= icon($ic) ?><strong><?= e($t) ?></strong><span><?= e($txt) ?></span></li><?php endforeach; ?>
    </ul>
  </div>
</section>

<?php if ($projects): ?>
<section class="section bg-alt" id="proiecte">
  <div class="container">
    <div class="svc-group-head" data-reveal>
      <div><span class="eyebrow">Proiecte reale. Companii reale. Soluții reale.</span><h2 style="margin:0">Ce am construit pentru clienții noștri</h2></div>
      <a class="btn btn-ghost" href="<?= e(url('/proiecte')) ?>">Toate proiectele <?= icon('arrow-right', 'ico ico-move') ?></a>
    </div>
    <div class="cases">
      <?php foreach ($projects as $i => $p): ?>
      <?= View::partial('site/partials/case_card', ['p' => $p, 'i' => $i]) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section" id="cum-lucram">
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="eyebrow">Cum lucrăm</span>
      <h2>De la prima discuție la tehnologie administrată lunar</h2>
      <p>Fără proiecte care încep și nu se mai termină. Preluăm, punem în ordine, apoi administrăm.</p>
    </div>
    <div class="steps">
      <?php foreach ($process as $i => $p): ?>
      <div class="step" data-reveal data-delay="<?= $i * 90 ?>"><h3><?= e($p['title'] ?? '') ?></h3><p><?= e($p['text'] ?? '') ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?= View::partial('site/partials/pricing', ['from' => $from]) ?>

<?php if ($stats): ?>
<section class="section-sm">
  <div class="container">
    <div class="stats" data-reveal>
      <?php foreach ($stats as $st): ?><div class="stat"><b><?= e($st['value']) ?></b><span><?= e($st['label']) ?></span></div><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container split">
    <div data-reveal>
      <span class="eyebrow">VITIM AI</span>
      <h2>AI-ul care lucrează cu datele și procesele companiei tale</h2>
      <p class="muted" style="font-size:1.1rem">VITIM AI este platforma noastră proprie. O conectăm la emailul, CRM-ul, magazinul și documentele firmei tale, ca munca repetitivă să se facă singură, iar echipa să se ocupe de clienți.</p>
      <ul class="checklist">
        <li><?= icon('check-circle') ?><span><strong>Răspunde clienților</strong> pe site, email sau WhatsApp, din informațiile firmei.</span></li>
        <li><?= icon('check-circle') ?><span><strong>Califică lead-urile</strong> și le trece în CRM, cu rezumat.</span></li>
        <li><?= icon('check-circle') ?><span><strong>Pregătește oferte, documente și follow-up</strong> în stilul firmei.</span></li>
        <li><?= icon('check-circle') ?><span><strong>Sub controlul tău:</strong> datele rămân ale firmei, cu acces pe roluri.</span></li>
      </ul>
      <div class="hero-ctas">
        <a class="btn btn-primary" href="<?= e(url('/vitim-ai')) ?>">Descoperă VITIM AI <?= icon('arrow-right', 'ico ico-move') ?></a>
        <a class="btn btn-ghost" href="<?= e(url('/servicii/automatizari')) ?>">Automatizare procese</a>
      </div>
    </div>
    <div class="flow" data-reveal data-delay="120" aria-label="Exemplu de flux automatizat cu VITIM AI">
      <div class="flow-node"><div class="icon-tile"><?= icon('mail') ?></div><div><small>01 · declanșator</small><strong>Cerere nouă de ofertă pe email</strong></div><span class="status">live</span></div>
      <div class="flow-node"><div class="icon-tile"><?= icon('brain') ?></div><div><small>02 · VITIM AI</small><strong>Înțelege cererea și extrage datele</strong></div></div>
      <div class="flow-node"><div class="icon-tile"><?= icon('database') ?></div><div><small>03 · integrare</small><strong>Verifică stocul și prețurile în ERP</strong></div></div>
      <div class="flow-node"><div class="icon-tile"><?= icon('file') ?></div><div><small>04 · rezultat</small><strong>Ofertă PDF pregătită și trimisă spre aprobare</strong></div></div>
      <div class="flow-node"><div class="icon-tile"><?= icon('kanban') ?></div><div><small>05 · CRM</small><strong>Oportunitate creată, follow-up programat</strong></div><span class="status">automat</span></div>
    </div>
  </div>
</section>

<section class="section-sm" id="demo-ai">
  <div class="container">
    <?= View::partial('site/partials/ai_demo') ?>
  </div>
</section>

<section class="section bg-alt why">
  <div class="container">
    <div class="section-head center" data-reveal>
      <span class="eyebrow">De ce VITIM</span>
      <h2>Om, nu robot</h2>
      <p>Automatizăm munca repetitivă. Relația cu tine rămâne între oameni.</p>
    </div>
    <div class="grid-3 varied">
      <?php foreach ($why as $i => $w): ?>
      <div class="card" data-reveal data-delay="<?= ($i % 3) * 80 ?>">
        <div class="icon-tile"><?= icon($w['icon'] ?? 'check') ?></div>
        <h3><?= e($w['title'] ?? '') ?></h3>
        <p><?= e($w['text'] ?? '') ?></p>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="center" style="margin:32px 0 0"><a class="link" href="<?= e(url('/despre-noi')) ?>">Cunoaște echipa VITIM <?= icon('arrow-right') ?></a></p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="eyebrow">Zone deservite</span>
      <h2>Servicii IT în Mureș, Bistrița-Năsăud și Alba</h2>
      <p>Intervenții la sediu în cele trei județe. Pentru tot ce se poate rezolva de la distanță, lucrăm remote cu firme din toată România.</p>
    </div>
    <?= View::partial('site/partials/zones') ?>
  </div>
</section>

<?php if ($testimonials): ?>
<section class="section bg-alt">
  <div class="container">
    <div class="section-head center" data-reveal><span class="eyebrow">Clienți</span><h2>Ce spun cei care lucrează cu noi</h2></div>
    <?= View::partial('site/partials/testimonials', ['items' => $testimonials]) ?>
  </div>
</section>
<?php endif; ?>

<?php if ($posts): ?>
<section class="section">
  <div class="container">
    <div class="svc-group-head" data-reveal>
      <div><span class="eyebrow">Blog</span><h2 style="margin:0">Ghiduri practice</h2></div>
      <a class="btn btn-ghost" href="<?= e(url('/blog')) ?>">Toate articolele <?= icon('arrow-right', 'ico ico-move') ?></a>
    </div>
    <div class="posts"><?php foreach ($posts as $p): ?><?= View::partial('site/partials/post_card', ['p' => $p]) ?><?php endforeach; ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($faq): ?>
<section class="section bg-alt">
  <div class="container">
    <div class="section-head center" data-reveal><span class="eyebrow">Întrebări frecvente</span><h2>Răspunsuri pe scurt</h2></div>
    <?= View::partial('site/partials/faq', ['faq' => $faq]) ?>
  </div>
</section>
<?php endif; ?>

<section class="section" id="contact">
  <div class="container">
    <?= View::partial('site/partials/cta_form', ['title' => setting('home_cta_title'), 'text' => setting('home_cta_text'), 'final' => true]) ?>
  </div>
</section>
