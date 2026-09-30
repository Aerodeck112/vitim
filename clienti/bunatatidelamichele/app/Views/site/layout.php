<?php
use App\Core\Settings;
use App\Core\Shop;
use App\Core\Site;

/** @var \App\Core\Seo $seo */
/** @var string $content */
$path = \App\Core\App::$path;
$cur = fn(string $p) => ($path === $p || ($p !== '/' && str_starts_with($path, $p . '/'))) ? ' aria-current="page"' : '';
$cats = Site::categories();
$ga4 = (string)setting('ga4_id');
$gtm = (string)setting('gtm_id');
$gads = (string)setting('google_ads_id');
$pixel = (string)setting('meta_pixel_id');
$clarity = (string)setting('clarity_id');
$hasTags = $ga4 || $gtm || $gads || $pixel || $clarity;
$email = (string)setting('email');
$phone = (string)setting('phone');
?><!doctype html>
<html lang="ro" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?= $seo->head() ?>
<meta name="theme-color" content="#f7f1e9">
<meta name="format-detection" content="telephone=no">
<link rel="preload" href="<?= e(url('/assets/fonts/jost-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<link rel="icon" href="<?= e(url('/assets/img/favicon-32.png')) ?>" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="<?= e(url('/assets/img/apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
<script>document.documentElement.classList.remove('no-js');window.BDM={base:<?= json_encode(base_path()) ?>,currency:<?= json_encode((string)setting('currency_label', 'lei')) ?>};
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',functionality_storage:'granted',security_storage:'granted',wait_for_update:500});
<?php if ($hasTags): ?>
window.bdmLoadTags=function(c){if(window.__tags)return;window.__tags=1;
function ld(u){var j=document.createElement('script');j.async=true;j.src=u;document.head.appendChild(j);}
<?php if ($gtm): ?>(function(w,l,i){w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});ld('https://www.googletagmanager.com/gtm.js?id='+i);})(window,'dataLayer',<?= json_encode($gtm) ?>);<?php endif; ?>
<?php if ($ga4 || $gads): ?>ld('https://www.googletagmanager.com/gtag/js?id=<?= e($ga4 ?: $gads) ?>');gtag('js',new Date());<?php if ($ga4): ?>gtag('config',<?= json_encode($ga4) ?>);<?php endif; ?><?php if ($gads): ?>gtag('config',<?= json_encode($gads) ?>);<?php endif; ?><?php endif; ?>
<?php if ($pixel): ?>if(c.marketing){!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];ld('https://connect.facebook.net/en_US/fbevents.js')}(window,document);fbq('init',<?= json_encode($pixel) ?>);fbq('track','PageView');}<?php endif; ?>
<?php if ($clarity): ?>if(c.analytics){(function(c,l,a,r,i){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};ld('https://www.clarity.ms/tag/'+i);})(window,document,'clarity','script',<?= json_encode($clarity) ?>);}<?php endif; ?>
};
<?php endif; ?>
</script>
<?= setting('custom_head') ?>
</head>
<body>
<?= setting('custom_body') ?>
<a class="skip" href="#main">Sari la conținut</a>
<?php if ($ann = setting('announcement')): ?>
<div class="announce"><?php if ($al = setting('announcement_link')): ?><a href="<?= e(url($al)) ?>"><?= icon('truck') ?><?= e($ann) ?></a><?php else: ?><?= icon('truck') ?><?= e($ann) ?><?php endif; ?></div>
<?php endif; ?>

<header class="site-header" id="top">
  <div class="container nav">
    <button class="icon-btn burger" type="button" data-mnav aria-label="Deschide meniul" aria-controls="mnav" aria-expanded="false"><?= icon('menu') ?></button>
    <a class="logo" href="<?= e(url('/')) ?>" aria-label="<?= e(setting('brand_name')) ?> – prima pagină"><?= Site::logoHtml() ?></a>
    <nav aria-label="Meniu principal">
      <ul class="menu">
        <li><a href="<?= e(url('/')) ?>"<?= $path === '/' ? ' aria-current="page"' : '' ?>>Acasă</a></li>
        <li>
          <a href="<?= e(url('/produse')) ?>"<?= ($path === '/produse' || str_starts_with($path, '/categorie') || str_starts_with($path, '/produs/')) ? ' aria-current="page"' : '' ?>>Produse <?= icon('chevron-down', 'ico chev') ?></a>
          <div class="drop">
            <?php foreach ($cats as $c): ?>
            <a href="<?= e(url('/categorie/' . $c['slug'])) ?>"><?php if ($c['icon']): ?><img src="<?= e(upload_url($c['icon'])) ?>" alt="" width="44" height="44" loading="lazy"><?php endif; ?><div><strong><?= e($c['name']) ?></strong><span><?= (int)$c['product_count'] ?> <?= (int)$c['product_count'] === 1 ? 'produs' : 'produse' ?></span></div></a>
            <?php endforeach; ?>
            <a href="<?= e(url('/produse')) ?>"><div><strong>Toate produsele →</strong></div></a>
          </div>
        </li>
        <li><a href="<?= e(url('/despre-noi')) ?>"<?= $cur('/despre-noi') ?>>Despre noi</a></li>
        <?php if (Site::hasPosts()): ?><li><a href="<?= e(url('/blog')) ?>"<?= $cur('/blog') ?>>Blog</a></li><?php endif; ?>
        <li><a href="<?= e(url('/contact')) ?>"<?= $cur('/contact') ?>>Contact</a></li>
      </ul>
    </nav>
    <div class="nav-tools">
      <button class="icon-btn" type="button" data-search aria-label="Caută produse" aria-expanded="false"><?= icon('search') ?></button>
      <a class="icon-btn hide-m" href="<?= e(url('/urmarire-comanda')) ?>" aria-label="Urmărește comanda" title="Urmărește comanda"><?= icon('package') ?></a>
      <a class="icon-btn" href="<?= e(url('/cos')) ?>" data-cart-open aria-label="Coșul de cumpărături"><?= icon('bag') ?><span class="cart-count" data-cart-count aria-live="polite">0</span></a>
    </div>
  </div>
  <div class="search-bar" id="search-bar">
    <form class="container" action="<?= e(url('/produse')) ?>" method="get" role="search">
      <label class="sr" for="q-top">Caută în magazin</label>
      <input id="q-top" name="q" type="search" placeholder="Caută cafea, cialde, espresoare…" autocomplete="off">
      <button class="btn" type="submit"><?= icon('search') ?> Caută</button>
    </form>
  </div>
</header>

<div class="mnav" id="mnav" aria-hidden="true">
  <div class="shade" data-mnav-close></div>
  <div class="panel" role="dialog" aria-label="Meniu">
    <div class="top"><a href="<?= e(url('/')) ?>"><?= Site::logoHtml() ?></a><button class="icon-btn" type="button" data-mnav-close aria-label="Închide meniul"><?= icon('x') ?></button></div>
    <a class="m" href="<?= e(url('/')) ?>">Acasă</a>
    <a class="m" href="<?= e(url('/produse')) ?>">Toate produsele <?= icon('chevron-right') ?></a>
    <div class="sub"><?php foreach ($cats as $c): ?><a href="<?= e(url('/categorie/' . $c['slug'])) ?>"><?php if ($c['icon']): ?><img src="<?= e(upload_url($c['icon'])) ?>" alt="" width="34" height="34" loading="lazy"><?php endif; ?><?= e($c['name']) ?></a><?php endforeach; ?></div>
    <a class="m" href="<?= e(url('/despre-noi')) ?>">Despre noi</a>
    <?php if (Site::hasPosts()): ?><a class="m" href="<?= e(url('/blog')) ?>">Blog</a><?php endif; ?>
    <a class="m" href="<?= e(url('/contact')) ?>">Contact</a>
    <a class="m" href="<?= e(url('/urmarire-comanda')) ?>">Urmărește comanda</a>
    <div class="foot"><?php if ($phone): ?><p><a href="<?= e(phone_href($phone)) ?>"><?= e($phone) ?></a></p><?php endif; ?><p><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p></div>
  </div>
</div>

<main id="main">
<?= $content ?>
</main>

<footer class="site-footer">
  <div class="container">
    <div class="fgrid">
      <div class="flogo">
        <a href="<?= e(url('/')) ?>"><?= Site::logoHtml(true) ?></a>
        <p><?= e(setting('home_footer_text')) ?></p>
        <?php if ($so = Site::socials()): ?><div class="socials"><?php foreach ($so as $s): ?><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($s['label']) ?>"><?= icon($s['icon']) ?></a><?php endforeach; ?></div><?php endif; ?>
      </div>
      <div>
        <h4>Magazin</h4>
        <ul>
          <?php foreach ($cats as $c): ?><li><a href="<?= e(url('/categorie/' . $c['slug'])) ?>"><?= e($c['name']) ?></a></li><?php endforeach; ?>
          <li><a href="<?= e(url('/produse')) ?>">Toate produsele</a></li>
          <li><a href="<?= e(url('/urmarire-comanda')) ?>">Urmărire comandă</a></li>
        </ul>
      </div>
      <div>
        <h4>Informații</h4>
        <ul>
          <li><a href="<?= e(url('/despre-noi')) ?>">Despre noi</a></li>
          <?php foreach (Site::footerPages('info') as $fp): ?><li><a href="<?= e(url('/' . $fp['slug'])) ?>"><?= e($fp['title']) ?></a></li><?php endforeach; ?>
          <li><a href="<?= e(url('/contact')) ?>">Contact</a></li>
        </ul>
      </div>
      <div>
        <h4>Legal</h4>
        <ul>
          <?php foreach (Site::footerPages('legal') as $fp): ?><li><a href="<?= e(url('/' . $fp['slug'])) ?>"><?= e($fp['title']) ?></a></li><?php endforeach; ?>
          <li><a href="#" data-open-consent>Setări cookies</a></li>
          <li><a href="https://anpc.ro" target="_blank" rel="noopener">ANPC</a></li>
        </ul>
      </div>
      <div>
        <h4>Contact</h4>
        <ul class="fcontact">
          <li><?= icon('mail') ?><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li>
          <?php if ($phone): ?><li><?= icon('phone') ?><a href="<?= e(phone_href($phone)) ?>"><?= e($phone) ?></a></li><?php endif; ?>
          <?php if ($h = setting('hours')): ?><li><?= icon('clock') ?><span><?= e($h) ?></span></li><?php endif; ?>
          <?php if (Site::companyAddress() !== ''): ?><li><?= icon('map') ?><span><?= e(Site::companyAddress()) ?></span></li><?php endif; ?>
        </ul>
      </div>
    </div>
    <div class="ftrust">
      <div class="paylogos" aria-label="Metode de plată acceptate">
        <span class="pl bt">BT <b>iPay</b></span><span class="pl visa">VISA</span><span class="pl mc" title="Mastercard"><i></i><i></i></span><span class="pl maestro" title="Maestro"><i></i><i></i></span><span class="pl secure"><?= icon('lock') ?>3D Secure</span>
      </div>
      <div class="anpc">
        <a href="https://anpc.ro/ce-este-sal/" target="_blank" rel="nofollow noopener">ANPC – SAL<small>Soluționarea alternativă a litigiilor</small></a>
        <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="nofollow noopener">SOL – Comisia Europeană<small>Soluționarea online a litigiilor</small></a>
      </div>
    </div>
    <div class="fbottom">
      <span>© <?= date('Y') ?> <?= e(setting('brand_name')) ?> · <?= e(setting('company_name')) ?><?php if (setting('company_cui')): ?> · CUI <?= e(setting('company_cui')) ?><?php endif; ?><?php if (setting('company_reg')): ?> · <?= e(setting('company_reg')) ?><?php endif; ?></span>
      <span>Realizat de <a href="https://vitim.ro" target="_blank" rel="noopener">VITIM</a></span>
    </div>
  </div>
</footer>

<div class="drawer" id="cart-drawer" aria-hidden="true">
  <div class="shade" data-cart-close></div>
  <aside class="panel" role="dialog" aria-label="Coșul tău" aria-modal="true">
    <div class="dh"><h2>Coșul tău</h2><button class="icon-btn" type="button" data-cart-close aria-label="Închide coșul"><?= icon('x') ?></button></div>
    <div class="db" data-cart-body><div class="empty-cart"><?= icon('bag') ?><p>Se încarcă…</p></div></div>
    <div class="df" data-cart-foot hidden>
      <div class="row-between"><span>Subtotal</span><strong data-cart-subtotal></strong></div>
      <small class="muted">Livrarea și eventualele reduceri se calculează la finalizarea comenzii.</small>
      <a class="btn btn-block" href="<?= e(url('/finalizare')) ?>"><?= icon('lock') ?> Finalizează comanda</a>
      <a class="btn btn-ghost btn-block" href="<?= e(url('/cos')) ?>">Vezi coșul</a>
    </div>
  </aside>
</div>

<?php if (setting('cookie_banner') === '1'): ?>
<div class="consent" id="consent" role="dialog" aria-live="polite" aria-label="Preferințe cookies">
  <h3>Cookies 🍪</h3>
  <p style="margin:0">Folosim cookies necesare pentru coș și comenzi și, doar cu acordul tău, cookies de analiză și marketing. Detalii în <a href="<?= e(url('/politica-cookies')) ?>">Politica de cookies</a>.</p>
  <div class="prefs">
    <label class="check"><input type="checkbox" checked disabled> <span><strong>Necesare</strong> – coș, comandă, securitate</span></label>
    <label class="check"><input type="checkbox" data-c="analytics"> <span><strong>Analiză</strong> – statistici anonime de trafic</span></label>
    <label class="check"><input type="checkbox" data-c="marketing"> <span><strong>Marketing</strong> – măsurarea reclamelor</span></label>
    <button class="btn btn-ghost btn-sm" type="button" data-consent="save">Salvează preferințele</button>
  </div>
  <div class="btns">
    <button class="btn btn-sm" type="button" data-consent="all">Accept toate</button>
    <button class="btn btn-ghost btn-sm" type="button" data-consent="none">Doar necesare</button>
    <button class="btn btn-ghost btn-sm" type="button" data-consent="prefs">Personalizează</button>
  </div>
</div>
<?php endif; ?>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="<?= e(asset('js/site.js')) ?>" defer></script>
</body>
</html>
