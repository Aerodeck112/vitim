<?php
use App\Core\Settings;
use App\Core\Site;
use App\Core\View;

$groups = Site::servicesByCategory();
$points = Settings::json('home_points');
$trust = Settings::json('home_trust');
$why = Settings::json('home_why');
$process = Settings::json('home_process');
$stats = Settings::json('home_stats');
$cats = Site::categories();
$all = Site::services();
?>
<section class="hero">
  <div class="hero-bg" aria-hidden="true"><div class="grid"></div><div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div></div>
  <div class="container hero-inner">
    <div>
      <span class="pill"><span class="dot"></span><?= e(setting('home_badge')) ?></span>
      <h1><?= strip_tags((string)setting('home_title'), '<span><br><em><strong>') ?></h1>
      <p class="lead"><?= e(setting('home_subtitle')) ?></p>
      <div class="hero-ctas">
        <a class="btn btn-primary btn-lg" href="<?= e(url('/contact')) ?>"><?= e(setting('home_cta_primary')) ?> <?= icon('arrow-right', 'ico ico-move') ?></a>
        <a class="btn btn-ghost btn-lg" href="#servicii"><?= e(setting('home_cta_secondary')) ?></a>
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
      <div class="console" data-console role="figure" aria-label="Exemplu: agentul VITIM monitorizează infrastructura unei firme">
        <div class="console-bar"><i></i><i></i><i></i><span>vitim-agent — exemplu de zi obișnuită</span></div>
        <div class="console-body">
          <div class="ln"><b>08:00</b><span><span class="ok">✓</span> Backup nocturn verificat · 3 servere · 0 erori</span></div>
          <div class="ln"><b>08:02</b><span><span class="ok">✓</span> Actualizări de securitate aplicate pe 24 PC-uri</span></div>
          <div class="ln"><b>08:15</b><span><span class="warn">!</span> Disc SSD „CONTA-02” – uzură 87% → înlocuire planificată</span></div>
          <div class="ln"><b>09:30</b><span><span class="ai">◆</span> Agent AI: 6 emailuri clasificate, 2 oferte generate</span></div>
          <div class="ln"><b>10:05</b><span><span class="ai">◆</span> Lead nou din Google Ads → calificat → trimis în CRM</span></div>
          <div class="ln"><b>10:40</b><span><span class="ok">✓</span> Tentativă phishing blocată · utilizator notificat</span></div>
          <div class="ln"><b>11:12</b><span><span class="ok">✓</span> Tichet #1482 rezolvat remote în 7 min</span></div>
          <div class="ln"><b>›</b><span>așteaptă următoarea sarcină <span class="cursor"></span></span></div>
        </div>
        <div class="console-foot">
          <div><b>Backup</b><small>verificat, nu presupus</small></div>
          <div><b>Patch-uri</b><small>aplicate la timp</small></div>
          <div><b>AI</b><small>integrat în fluxuri</small></div>
        </div>
      </div>
      <div class="float-card fc1"><?= icon('shield') ?><span>Securitate activă<br><small class="muted">protecție + backup</small></span></div>
      <div class="float-card fc2"><?= icon('sparkles') ?><span>Agent AI integrat</span></div>
    
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($trust): ?>
<div class="container">
  <p class="marquee-label">Tehnologii cu care lucrăm zilnic</p>
  <div class="marquee" role="img" aria-label="Tehnologii: <?= e(implode(', ', $trust)) ?>">
    <div class="marquee-track" aria-hidden="true">
      <?php for ($k = 0; $k < 2; $k++): foreach ($trust as $t): ?><span><?= e($t) ?></span><?php endforeach; endfor; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<section class="section" id="servicii">
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="eyebrow">Servicii</span>
      <h2>Tot ce ține de tehnologie, la un singur partener</h2>
      <p>De la calculatorul care nu mai pornește până la agentul AI care îți răspunde clienților. Alege ce te interesează:</p>
    </div>
    <div class="cat-tabs" role="tablist" data-cat-tabs="#bento">
      <button type="button" role="tab" aria-selected="true" data-cat="all">Toate</button>
      <?php foreach ($groups as $k => $g): ?><button type="button" role="tab" aria-selected="false" data-cat="<?= e($k) ?>"><?= e($g['cat']['short']) ?></button><?php endforeach; ?>
    </div>
    <div class="bento" id="bento">
      <?php
      // câte carduri late sunt necesare ca grila de 12 coloane să se umple complet (fără carduri orfane)
      $needWide = (3 - count($all) % 3) % 3 ?: 3;
      $n = 0;
      foreach ($all as $s): $n++;
        $wide = $s['featured'] && $needWide > 0;
        if ($wide) {
            $needWide--;
        }
        $cls = trim(($wide ? 'wide ' : '') . ($s['featured'] ? 'feature' : ''));
      ?>
      <article class="card <?= $cls ?><?= $wide && $s['image'] ? ' has-img' : '' ?>" data-cat="<?= e($s['category']) ?>" data-reveal data-delay="<?= min(($n % 3) * 70, 210) ?>">
        <?php if ($wide && $s['image']): ?><div class="card-img"><img src="<?= e(upload_url($s['image'])) ?>" srcset="<?= e(\App\Core\Uploader::srcset($s['image'])) ?>" sizes="(max-width:720px) 100vw, 400px" alt="" loading="lazy" width="960" height="540"></div><div><?php endif; ?>
        <div class="icon-tile"><?= icon($s['icon'] ?: 'sparkles') ?></div>
        <h3><?= e($s['title']) ?></h3>
        <p><?= e($s['excerpt']) ?></p>
        <div class="tags">
          <span class="tag"><?= e($cats[$s['category']]['short'] ?? '') ?></span>
          <?php if ($s['onsite']): ?><span class="tag">Remote + on-site</span><?php endif; ?>
        </div>
        <span class="more">Detalii <?= icon('arrow-up-right') ?></span>
        <?php if ($wide && $s['image']): ?></div><?php endif; ?>
        <a class="card-link" href="<?= e(url('/servicii/' . $s['slug'])) ?>" aria-label="<?= e($s['title']) ?>"></a>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($stats): ?>
<section class="section-sm">
  <div class="container">
    <div class="stats" data-reveal>
      <?php foreach ($stats as $st): ?><div class="stat"><b><?= e($st['value']) ?></b><span><?= e($st['label']) ?></span></div><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section bg-alt why">
  <div class="container">
    <div class="section-head center" data-reveal>
      <span class="eyebrow">De ce VITIM</span>
      <h2>Partenerul tehnic pe care te poți baza</h2>
      <p>Nu vindem ore de lucru, ci liniște: echipamente care merg, date în siguranță și un flux constant de clienți.</p>
    </div>
    <div class="grid-3">
      <?php foreach ($why as $i => $w): ?>
      <div class="card" data-reveal data-delay="<?= ($i % 3) * 80 ?>">
        <div class="icon-tile"><?= icon($w['icon'] ?? 'check') ?></div>
        <h3><?= e($w['title'] ?? '') ?></h3>
        <p><?= e($w['text'] ?? '') ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container split">
    <div data-reveal>
      <span class="eyebrow">AI pentru afaceri</span>
      <h2>Agenți AI care lucrează pentru tine, nu doar un chatbot</h2>
      <p class="muted" style="font-size:1.1rem">Construim și integrăm agenți AI conectați la emailul, CRM-ul, documentele și aplicațiile tale. Ei preiau munca repetitivă, iar echipa ta se ocupă de ce contează.</p>
      <ul class="checklist">
        <li><?= icon('check-circle') ?><span><strong>Răspund clienților 24/7</strong> din documentele și ofertele firmei tale.</span></li>
        <li><?= icon('check-circle') ?><span><strong>Califică lead-urile</strong> și le trec automat în CRM, cu rezumat.</span></li>
        <li><?= icon('check-circle') ?><span><strong>Generează oferte, rapoarte și emailuri</strong> în stilul firmei.</span></li>
        <li><?= icon('check-circle') ?><span><strong>Securizat și sub control:</strong> datele rămân la tine, cu acces pe roluri.</span></li>
      </ul>
      <div class="hero-ctas">
        <a class="btn btn-primary" href="<?= e(url('/servicii/agenti-ai-software-personalizat')) ?>">Descoperă agenții AI <?= icon('arrow-right', 'ico ico-move') ?></a>
        <a class="btn btn-ghost" href="<?= e(url('/servicii/automatizari')) ?>">Automatizări</a>
      </div>
    </div>
    <div class="flow" data-reveal data-delay="120" aria-label="Exemplu de flux automatizat">
      <div class="flow-node"><div class="icon-tile"><?= icon('mail') ?></div><div><small>01 · declanșator</small><strong>Cerere nouă de ofertă pe email</strong></div><span class="status">live</span></div>
      <div class="flow-node"><div class="icon-tile"><?= icon('brain') ?></div><div><small>02 · agent AI</small><strong>Înțelege cererea și extrage datele</strong></div></div>
      <div class="flow-node"><div class="icon-tile"><?= icon('database') ?></div><div><small>03 · integrare</small><strong>Verifică stocul și prețurile în ERP</strong></div></div>
      <div class="flow-node"><div class="icon-tile"><?= icon('file') ?></div><div><small>04 · rezultat</small><strong>Ofertă PDF generată + trimisă spre aprobare</strong></div></div>
      <div class="flow-node"><div class="icon-tile"><?= icon('kanban') ?></div><div><small>05 · CRM</small><strong>Oportunitate creată, follow-up programat</strong></div><span class="status">~2 min</span></div>
    </div>
  </div>
</section>

<section class="section bg-alt">
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="eyebrow">Cum lucrăm</span>
      <h2>Simplu, clar, fără surprize</h2>
    </div>
    <div class="steps">
      <?php foreach ($process as $i => $p): ?>
      <div class="step" data-reveal data-delay="<?= $i * 90 ?>"><h3><?= e($p['title'] ?? '') ?></h3><p><?= e($p['text'] ?? '') ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="eyebrow">Zone deservite</span>
      <h2>La tine la sediu în Mureș, Bistrița-Năsăud și Alba</h2>
      <p>Pentru tot ce se poate rezolva de la distanță, lucrăm remote cu clienți din toată România.</p>
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
    <?= View::partial('site/partials/cta_form', ['title' => setting('home_cta_title'), 'text' => setting('home_cta_text')]) ?>
  </div>
</section>
