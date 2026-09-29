<?php
use App\Core\Settings;
use App\Core\Site;

/** @var \App\Core\Seo $seo */
/** @var string $content */
$groups = Site::servicesByCategory();
$counties = Site::counties();
$phone = (string)setting('phone');
$wa = (string)setting('whatsapp');
$path = \App\Core\App::$path;
$cur = fn(string $p) => ($path === $p || ($p !== '/' && str_starts_with($path, $p . '/'))) ? ' aria-current="page"' : '';
$themeDefault = setting('theme_default', 'dark');
$ga4 = (string)setting('ga4_id');
$gtm = (string)setting('gtm_id');
$gads = (string)setting('google_ads_id');
$pixel = (string)setting('meta_pixel_id');
$clarity = (string)setting('clarity_id');
$hasTags = $ga4 || $gtm || $gads || $pixel || $clarity;
?><!doctype html>
<html lang="ro" data-theme="<?= e($themeDefault) ?>" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<script>(function(){try{var t=localStorage.getItem('theme');if(t)document.documentElement.setAttribute('data-theme',t)}catch(e){}})();</script>
<?= $seo->head() ?>
<meta name="theme-color" content="<?= $themeDefault === 'light' ? '#f7f8fc' : '#05070d' ?>">
<meta name="format-detection" content="telephone=no">
<link rel="preload" href="<?= e(url('/assets/fonts/Geist-Variable.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<link rel="icon" href="<?= e(url('/assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="icon" href="<?= e(url('/assets/img/favicon-32.png')) ?>" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="<?= e(url('/assets/img/apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
<link rel="alternate" type="application/rss+xml" title="Blog <?= e(setting('brand_name')) ?>" href="<?= e(url('/feed.xml')) ?>">
<script>window.VITIM={base:<?= json_encode(base_path()) ?>};
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',functionality_storage:'granted',security_storage:'granted',wait_for_update:500});
<?php if ($hasTags): ?>
window.vitimLoadTags=function(c){if(window.__tags)return;window.__tags=1;
function ld(u){var j=document.createElement('script');j.async=true;j.src=u;document.head.appendChild(j);}
<?php if ($gtm): ?>(function(w,l,i){w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});ld('https://www.googletagmanager.com/gtm.js?id='+i);})(window,'dataLayer',<?= json_encode($gtm) ?>);<?php endif; ?>
<?php if ($ga4 || $gads): ?>ld('https://www.googletagmanager.com/gtag/js?id=<?= e($ga4 ?: $gads) ?>');gtag('js',new Date());<?php if ($ga4): ?>gtag('config',<?= json_encode($ga4) ?>);<?php endif; ?><?php if ($gads): ?>gtag('config',<?= json_encode($gads) ?>);<?php endif; ?><?php endif; ?>
<?php if ($pixel): ?>if(c.marketing){!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];ld('https://connect.facebook.net/en_US/fbevents.js')}(window,document);fbq('init',<?= json_encode($pixel) ?>);fbq('track','PageView');}<?php endif; ?>
<?php if ($clarity): ?>if(c.analytics){(function(c,l,a,r,i){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};ld('https://www.clarity.ms/tag/'+i);})(window,document,'clarity','script',<?= json_encode($clarity) ?>);}<?php endif; ?>
<?php if ($gads && setting('google_ads_lead_label')): ?>window.addEventListener('vitim:lead',function(){gtag('event','conversion',{send_to:<?= json_encode($gads . '/' . setting('google_ads_lead_label')) ?>});});<?php endif; ?>
};
<?php endif; ?>
</script>
<?= setting('custom_head') ?>
</head>
<body>
<?= setting('custom_body') ?>
<a class="skip" href="#main">Sari la conținut</a>
<?php if ($ann = setting('announcement')): ?>
<div class="announce"><?php if ($al = setting('announcement_link')): ?><a href="<?= e(url($al)) ?>"><?= e($ann) ?></a><?php else: ?><?= e($ann) ?><?php endif; ?></div>
<?php endif; ?>

<header class="site-header">
  <div class="container nav">
    <a class="logo" href="<?= e(url('/')) ?>"><?= Site::logoHtml() ?></a>
    <nav aria-label="Meniu principal">
      <ul class="menu">
        <li>
          <button type="button" data-mega aria-expanded="false" aria-haspopup="true"<?= $cur('/servicii') ?>>Servicii <?= icon('chevron-down', 'ico chev') ?></button>
          <div class="mega" role="menu">
            <?php foreach ($groups as $gk => $g): ?>
            <div class="mega-col">
              <p class="mega-h"><?= e($g['cat']['short']) ?></p>
              <?php foreach ($g['items'] as $s): ?>
              <a href="<?= e(url('/servicii/' . $s['slug'])) ?>" role="menuitem"><?= icon($s['icon'] ?: 'sparkles') ?><div><strong><?= e($s['title']) ?></strong><span><?= e($s['tagline']) ?></span></div></a>
              <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            <div class="mega-foot"><span>Nu știi exact de ce ai nevoie? Îți recomandăm noi soluția potrivită, gratuit.</span><a class="btn btn-primary btn-sm" href="<?= e(url('/contact')) ?>">Discută cu un specialist <?= icon('arrow-right', 'ico ico-move') ?></a></div>
          </div>
        </li>
        <li><a href="<?= e(url('/zone')) ?>"<?= $cur('/zone') ?>>Zone</a></li>
        <?php if (Site::hasProjects()): ?><li><a href="<?= e(url('/proiecte')) ?>"<?= $cur('/proiecte') ?>>Proiecte</a></li><?php endif; ?>
        <li><a href="<?= e(url('/blog')) ?>"<?= $cur('/blog') ?>>Blog</a></li>
        <li><a href="<?= e(url('/despre-noi')) ?>"<?= $cur('/despre-noi') ?>>Despre noi</a></li>
        <li><a href="<?= e(url('/contact')) ?>"<?= $cur('/contact') ?>>Contact</a></li>
      </ul>
    </nav>
    <div class="nav-actions">
      <a class="phone-link" href="<?= e(phone_href($phone)) ?>" data-loc="header"><?= icon('phone') ?><span><?= e($phone) ?></span></a>
      <button class="icon-btn" type="button" data-theme-toggle aria-label="Schimbă tema"><?= icon('sun', 'ico i-sun') ?><?= icon('moon', 'ico i-moon') ?></button>
      <a class="btn btn-primary btn-sm btn-cta" href="<?= e(url('/contact')) ?>">Cere ofertă</a>
      <button class="icon-btn burger" type="button" data-drawer-open aria-label="Deschide meniul" aria-controls="drawer"><?= icon('menu') ?></button>
    </div>
  </div>
</header>

<div class="drawer" id="drawer" aria-label="Meniu mobil">
  <button class="icon-btn close" type="button" data-drawer-close aria-label="Închide meniul"><?= icon('x') ?></button>
  <a href="<?= e(url('/')) ?>">Acasă</a>
  <details>
    <summary>Servicii <?= icon('chevron-down') ?></summary>
    <?php foreach ($groups as $g): ?>
      <div class="drawer-h"><?= e($g['cat']['short']) ?></div>
      <?php foreach ($g['items'] as $s): ?><a href="<?= e(url('/servicii/' . $s['slug'])) ?>"><?= e($s['title']) ?></a><?php endforeach; ?>
    <?php endforeach; ?>
  </details>
  <a href="<?= e(url('/zone')) ?>">Zone deservite</a>
  <?php if (Site::hasProjects()): ?><a href="<?= e(url('/proiecte')) ?>">Proiecte</a><?php endif; ?>
  <a href="<?= e(url('/blog')) ?>">Blog</a>
  <a href="<?= e(url('/despre-noi')) ?>">Despre noi</a>
  <a href="<?= e(url('/contact')) ?>">Contact</a>
  <a class="btn btn-primary btn-lg btn-block" href="<?= e(url('/contact')) ?>">Cere o ofertă gratuită</a>
  <a class="btn btn-ghost btn-lg btn-block" href="<?= e(phone_href($phone)) ?>" data-loc="drawer"><?= icon('phone') ?> <?= e($phone) ?></a>
</div>

<main id="main">
<?= $content ?>
</main>

<footer class="site-footer">
  <div class="container">
    <div class="foot-grid">
      <div class="foot-about">
        <a class="logo" href="<?= e(url('/')) ?>"><?= Site::logoHtml() ?></a>
        <p><?= e(setting('brand_tagline')) ?>. Remote în toată România, on-site în <?= e(implode(', ', array_map(fn($c) => $c['name'], $counties))) ?>.</p>
        <ul class="contact-lines" style="margin:0 0 20px">
          <li><a href="<?= e(phone_href($phone)) ?>" data-loc="footer"><?= icon('phone') ?><span><small>Telefon</small><?= e($phone) ?></span></a></li>
          <li><a href="mailto:<?= e(setting('email')) ?>"><?= icon('mail') ?><span><small>Email</small><?= e(setting('email')) ?></span></a></li>
        </ul>
        <?php if ($socials = Site::socials()): ?>
        <div class="socials"><?php foreach ($socials as $so): ?><a href="<?= e($so['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($so['label']) ?>"><?= icon($so['icon']) ?></a><?php endforeach; ?></div>
        <?php endif; ?>
      </div>
      <?php
      // coloane footer: IT + Securitate | Marketing | AI
      $cols = [];
      foreach ($groups as $gk => $g) {
          $ci = match ($gk) { 'it', 'securitate' => 0, 'marketing' => 1, default => 2 };
          $cols[$ci]['title'] ??= $g['cat']['short'];
          $cols[$ci]['items'] = array_merge($cols[$ci]['items'] ?? [], $g['items']);
      }
      ksort($cols);
      foreach (array_slice($cols, 0, 3) as $col): ?>
      <div>
        <h2 class="fh"><?= e($col['title']) ?></h2>
        <ul><?php foreach ($col['items'] as $s): ?><li><a href="<?= e(url('/servicii/' . $s['slug'])) ?>"><?= e($s['title']) ?></a></li><?php endforeach; ?></ul>
      </div>
      <?php endforeach; ?>
      <div class="nl-wrap">
        <h2 class="fh">Newsletter</h2>
        <p class="muted" style="font-size:14.5px;margin:0 0 12px">Ghiduri practice de IT, securitate și AI pentru firme. O dată pe lună, fără spam.</p>
        <form class="nl-form-wrap" data-ajax data-form="newsletter" data-event="newsletter_signup" action="<?= e(url('/api/newsletter')) ?>" method="post">
          <div class="nl-form">
            <label class="sr-only" for="nl-email">Email</label>
            <input id="nl-email" type="email" name="email" placeholder="email@firma.ro" required autocomplete="email">
            <button class="btn btn-primary" type="submit" aria-label="Abonează-mă"><?= icon('send') ?></button>
          </div>
          <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
          <input type="hidden" name="page" value="">
          <label class="check"><input type="checkbox" name="consent" value="1" required> <span><?= e(setting('newsletter_consent_text')) ?></span></label>
          <div class="form-msg" role="status" aria-live="polite" style="margin-top:10px"></div>
        </form>
        <h2 class="fh" style="margin-top:28px">Zone</h2>
        <ul><?php foreach ($counties as $c): ?><li><a href="<?= e(url('/zone/' . $c['slug'])) ?>">Județul <?= e($c['name']) ?></a></li><?php endforeach; ?></ul>
      </div>
    </div>
    <div class="legal-badges">
      <a href="https://anpc.ro/ce-este-sal/" target="_blank" rel="noopener nofollow">ANPC – SAL</a>
      <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noopener nofollow">Soluționarea online a litigiilor</a>
    </div>
    <div class="foot-bottom">
      <span>© <?= date('Y') ?> <?= e(setting('company_name')) ?><?php if (setting('company_cui')): ?> · CUI <?= e(setting('company_cui')) ?><?php endif; ?><?php if (setting('company_reg')): ?> · <?= e(setting('company_reg')) ?><?php endif; ?></span>
      <nav aria-label="Legal">
        <?php foreach (Site::footerPages() as $fp): ?><a href="<?= e(url('/' . $fp['slug'])) ?>"><?= e($fp['title']) ?></a><?php endforeach; ?>
        <a href="#" data-open-consent>Setări cookies</a>
        <a href="<?= e(url('/sitemap.xml')) ?>">Sitemap</a>
      </nav>
    </div>
    <div class="big-brand" aria-hidden="true"><?= e(setting('brand_name')) ?></div>
  </div>
</footer>

<nav class="mobile-bar" aria-label="Acțiuni rapide">
  <a href="<?= e(phone_href($phone)) ?>" data-loc="mobile-bar"><?= icon('phone') ?> Sună</a>
  <?php if ($wa): ?><a href="<?= e(whatsapp_href($wa, 'Bună! Aș dori mai multe informații despre serviciile VITIM.')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?> WhatsApp</a><?php else: ?><a href="mailto:<?= e(setting('email')) ?>"><?= icon('mail') ?> Email</a><?php endif; ?>
  <a class="primary" href="<?= e(url('/contact')) ?>">Cere ofertă <?= icon('arrow-right') ?></a>
</nav>

<?php if (setting('cookie_banner') === '1'): ?>
<div class="consent" id="consent" role="dialog" aria-live="polite" aria-label="Preferințe cookies">
  <h3>Folosim cookies</h3>
  <p style="margin:0">Folosim cookies necesare pentru funcționarea site-ului și, cu acordul tău, cookies de analiză și marketing ca să îmbunătățim site-ul și reclamele. Detalii în <a href="<?= e(url('/politica-cookies')) ?>" style="text-decoration:underline">Politica de cookies</a>.</p>
  <div class="prefs">
    <label><input type="checkbox" checked disabled> <span><b>Necesare</b>Funcționarea de bază a site-ului. Mereu active.</span></label>
    <label><input type="checkbox" id="c-an"> <span><b>Analiză</b>Statistici anonime de vizitare (Google Analytics, Clarity).</span></label>
    <label><input type="checkbox" id="c-mk"> <span><b>Marketing</b>Măsurarea reclamelor Google și Meta.</span></label>
    <button class="btn btn-ghost btn-sm" type="button" data-consent="save">Salvează preferințele</button>
  </div>
  <div class="acts">
    <button class="btn btn-primary btn-sm" type="button" data-consent="all">Accept toate</button>
    <button class="btn btn-ghost btn-sm" type="button" data-consent="none">Doar necesare</button>
    <button class="btn btn-ghost btn-sm" type="button" data-consent="prefs">Personalizează</button>
  </div>
</div>
<?php endif; ?>

<?php if (\App\Core\Assistant::enabled()): $sugg = \App\Core\Settings::json('ai_suggestions'); ?>
<div class="chat" id="chat" data-chat hidden>
  <div class="chat-head">
    <span class="chat-av"><?= Site::markSvg('mk') ?></span>
    <div><strong><?= e(setting('ai_name')) ?></strong><small><span class="dot"></span> răspunde imediat, 24/7</small></div>
    <button class="icon-btn" type="button" data-chat-close aria-label="Închide chatul"><?= icon('x') ?></button>
  </div>
  <div class="chat-body" data-chat-body aria-live="polite">
    <div class="msg bot"><?= e(setting('ai_greeting')) ?></div>
    <?php if ($sugg): ?><div class="chat-sugg" data-chat-sugg><?php foreach ($sugg as $q): ?><button type="button"><?= e($q) ?></button><?php endforeach; ?></div><?php endif; ?>
  </div>
  <form class="chat-form" data-chat-form>
    <label class="sr-only" for="chat-in">Mesaj</label>
    <textarea id="chat-in" rows="1" maxlength="1500" placeholder="Scrie întrebarea ta…" required></textarea>
    <button class="btn btn-primary" type="submit" aria-label="Trimite"><?= icon('send') ?></button>
  </form>
  <p class="chat-note">Asistent virtual. Poate greși – pentru decizii importante vorbește cu un coleg. <a href="<?= e(url('/politica-de-confidentialitate')) ?>" target="_blank">Confidențialitate</a></p>
</div>
<button class="chat-fab" type="button" data-chat-open aria-controls="chat"><?= icon('message') ?><span>Întreabă-ne</span></button>
<?php endif; ?>
<script src="<?= e(asset('js/site.js')) ?>" defer></script>
</body>
</html>
