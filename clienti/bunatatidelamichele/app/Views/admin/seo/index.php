<?php
use App\Core\Csrf;
use App\Core\Settings;
use App\Core\View;
?>
<div class="page-head"><div><h1>Setări SEO</h1><p>Totul pentru Google, Bing și asistenții AI – într-un singur loc.</p></div>
<div class="actions"><a class="btn" href="<?= e(url('/admin/seo/audit')) ?>"><?= icon('check-circle') ?> Rulează auditul</a></div></div>

<div class="grid g3" style="margin-bottom:16px">
  <div class="card"><h3><?= icon('file') ?> Sitemap XML</h3><p class="small muted">Generat automat, cu imagini și date de actualizare. Trimite-l în Google Search Console → Sitemaps.</p><code class="code" id="sm"><?= e(abs_url('/sitemap.xml')) ?></code><div class="actions" style="margin-top:8px"><button class="btn btn-sm" type="button" data-copy="#sm">Copiază</button><a class="btn btn-sm" href="<?= e(url('/sitemap.xml')) ?>" target="_blank">Deschide</a></div></div>
  <div class="card"><h3><?= icon('bot') ?> robots.txt & llms.txt</h3><p class="small muted">robots.txt permite explicit motoarele AI (ChatGPT, Claude, Perplexity, Gemini). llms.txt le oferă un rezumat curat al firmei.</p><div class="actions"><a class="btn btn-sm" href="<?= e(url('/robots.txt')) ?>" target="_blank">robots.txt</a><a class="btn btn-sm" href="<?= e(url('/llms.txt')) ?>" target="_blank">llms.txt</a></div></div>
  <div class="card"><h3><?= icon('zap') ?> IndexNow</h3><p class="small muted">Anunță instant Bing și alte motoare când publici ceva. Se face automat la fiecare salvare; aici poți retrimite tot site-ul.</p><form method="post" action="<?= e(url('/admin/seo/indexnow')) ?>"><?= Csrf::field() ?><button class="btn btn-sm"><?= icon('send') ?> Trimite toate adresele</button></form></div>
</div>

<form method="post" action="<?= e(url('/admin/seo')) ?>" class="card f">
  <?= Csrf::field() ?>
  <h2>Prima pagină</h2>
  <div class="serp" data-serp data-title="#t1" data-desc="#d1"><div class="u"><?= e(parse_url(abs_url('/'), PHP_URL_HOST)) ?></div><div class="t"></div><div class="d"></div></div>
  <div class="fl"><label for="t1"><?= e($fields['seo_home_title']) ?></label><input class="in" id="t1" name="seo_home_title" value="<?= e(Settings::get('seo_home_title')) ?>" data-count="65"></div>
  <div class="fl"><label for="d1"><?= e($fields['seo_home_description']) ?></label><textarea class="in" id="d1" name="seo_home_description" rows="3" data-count="160"><?= e(Settings::get('seo_home_description')) ?></textarea></div>
  <div class="row2">
    <div class="fl"><label><?= e($fields['seo_title_suffix']) ?></label><input class="in" name="seo_title_suffix" value="<?= e(Settings::get('seo_title_suffix')) ?>"><div class="hint">Adăugat la titlurile paginilor dacă încape în 65 de caractere.</div></div>
    <?= View::partial('admin/partials/field', ['f' => ['name' => 'seo_default_og', 'label' => $fields['seo_default_og'], 'type' => 'image', 'hint' => 'Dacă lipsește, se generează automat imagini cu titlul paginii.'], 'val' => Settings::get('seo_default_og')]) ?>
  </div>
  <h2 style="margin-top:10px">Verificări & indexare</h2>
  <div class="row2">
    <div class="fl"><label><?= e($fields['seo_google_verification']) ?></label><input class="in mono" name="seo_google_verification" value="<?= e(Settings::get('seo_google_verification')) ?>"><div class="hint">Poți lipi direct eticheta &lt;meta name="google-site-verification"…&gt;.</div></div>
    <div class="fl"><label><?= e($fields['seo_bing_verification']) ?></label><input class="in mono" name="seo_bing_verification" value="<?= e(Settings::get('seo_bing_verification')) ?>"></div>
  </div>
  <div class="fl"><label><?= e($fields['seo_indexnow_key']) ?></label><input class="in mono" name="seo_indexnow_key" value="<?= e(Settings::get('seo_indexnow_key')) ?>"><div class="hint">Fișierul de verificare este publicat automat la <?= e(abs_url('/' . Settings::get('seo_indexnow_key') . '.txt')) ?></div></div>
  <h2 style="margin-top:10px">AI & roboți</h2>
  <div class="fl"><label><?= e($fields['seo_llms_intro']) ?></label><textarea class="in" name="seo_llms_intro" rows="3"><?= e(Settings::get('seo_llms_intro')) ?></textarea><div class="hint">O descriere factuală, la persoana a treia. Asistenții AI o folosesc ca să te recomande corect.</div></div>
  <div class="fl"><label><?= e($fields['seo_robots_extra']) ?></label><textarea class="in mono" name="seo_robots_extra" rows="3"><?= e(Settings::get('seo_robots_extra')) ?></textarea></div>
  <label class="chk" style="color:var(--err)"><input type="checkbox" name="seo_noindex_site" value="1"<?= Settings::get('seo_noindex_site') === '1' ? ' checked' : '' ?>> <?= e($fields['seo_noindex_site']) ?> (doar în timpul dezvoltării!)</label>
  <div class="sticky-save"><button class="btn btn-p"><?= icon('check') ?> Salvează</button></div>
</form>

<div class="card">
  <h2>Ce face automat site-ul pentru SEO</h2>
  <ul class="muted" style="columns:2;gap:30px;margin:0;padding-left:18px">
    <li>Date structurate schema.org: Product + Offer (preț, stoc, livrare, retur), OnlineStore, ItemList, FAQPage, BreadcrumbList, WebSite cu căutare</li>
    <li>Feed Google Merchant Center (Google Shopping și listări gratuite): <a href="<?= e(url('/feed/google-merchant.xml')) ?>" target="_blank">/feed/google-merchant.xml</a></li>
    <li>Sitemap XML cu imaginile produselor + index de sitemap-uri</li>
    <li>Adresele produselor de pe vechiul site WordPress (/produs/…) au rămas identice; restul sunt redirecționate 301</li>
    <li>URL-uri curate, canonice, fără slash final (redirect 301 automat)</li>
    <li>Imagini Open Graph generate automat pentru produse</li>
    <li>Imagini WebP responsive, lazy-loading, dimensiuni fixe (CLS zero)</li>
    <li>Cache de pagini: timp de răspuns de câțiva milisecunde</li>
    <li>Redirecționare 301 automată când schimbi adresa unui produs sau îl ștergi</li>
    <li>Coș, finalizare și comenzi excluse din index (noindex + robots.txt)</li>
    <li>IndexNow la fiecare modificare de produs</li>
    <li>robots.txt și llms.txt optimizate pentru căutarea AI</li>
    <li>Consent Mode v2 pentru Google Analytics, Ads și Meta Pixel</li>
  </ul>
</div>
