<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\Site;

$u = Auth::user();
$path = \App\Core\App::$path;
$on = fn(string $p, bool $exact = false) => ($exact ? $path === $p : ($path === $p || str_starts_with($path, $p . '/') || str_starts_with($path, $p . '?'))) ? ' class="on"' : '';
$newLeads = $u ? (int)DB::val("SELECT COUNT(*) FROM deals WHERE stage = 'nou'") : 0;
$unread = $u ? (int)DB::val('SELECT COUNT(*) FROM submissions WHERE read_at IS NULL AND spam_score < 5') : 0;
$tasksDue = $u ? (int)DB::val("SELECT COUNT(*) FROM activities WHERE type = 'task' AND done = 0 AND due_at IS NOT NULL AND due_at <= ?", [DB::now()]) : 0;
?><!doctype html>
<html lang="ro" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf" content="<?= e(Csrf::token()) ?>">
<meta name="base" content="<?= e(base_path()) ?>">
<title><?= e($title ?? 'Panou') ?> · <?= e(setting('brand_name')) ?> Admin</title>
<script>try{var t=localStorage.getItem('admin-theme');if(t)document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<link rel="icon" href="<?= e(url('/assets/img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body>
<div class="app">
  <aside class="side">
    <div class="brand"><?= Site::markSvg('mk') ?><div><?= e(setting('brand_name')) ?><small>panou de control</small></div></div>
    <nav>
      <a href="<?= e(url('/admin/dashboard')) ?>"<?= $on('/admin/dashboard') ?>><?= icon('dashboard') ?> Tablou de bord</a>
      <?php if (Auth::can('crm')): ?>
      <div class="grp">CRM</div>
      <a href="<?= e(url('/admin/crm')) ?>"<?= $on('/admin/crm', true) ?>><?= icon('kanban') ?> Oportunități <?php if ($newLeads): ?><span class="badge b-info"><?= $newLeads ?></span><?php endif; ?></a>
      <a href="<?= e(url('/admin/crm/contacte')) ?>"<?= $on('/admin/crm/contacte') ?>><?= icon('users') ?> Contacte</a>
      <a href="<?= e(url('/admin/crm/sarcini')) ?>"<?= $on('/admin/crm/sarcini') ?>><?= icon('check-circle') ?> Sarcini <?php if ($tasksDue): ?><span class="badge b-warn"><?= $tasksDue ?></span><?php endif; ?></a>
      <a href="<?= e(url('/admin/asistent')) ?>"<?= $on('/admin/asistent') ?>><?= icon('bot') ?> Asistent AI</a>
      <a href="<?= e(url('/admin/formulare')) ?>"<?= $on('/admin/formulare') ?>><?= icon('inbox') ?> Formulare <?php if ($unread): ?><span class="badge b-err"><?= $unread ?></span><?php endif; ?></a>
      <?php endif; ?>
      <?php if (Auth::can('email')): ?>
      <div class="grp">Email marketing</div>
      <a href="<?= e(url('/admin/email')) ?>"<?= $on('/admin/email') ?>><?= icon('send') ?> Campanii</a>
      <a href="<?= e(url('/admin/abonati')) ?>"<?= $on('/admin/abonati') ?>><?= icon('mail') ?> Abonați</a>
      <?php endif; ?>
      <?php if (Auth::can('content')): ?>
      <div class="grp">Conținut site</div>
      <a href="<?= e(url('/admin/c/servicii')) ?>"<?= $on('/admin/c/servicii') ?>><?= icon('layers') ?> Servicii</a>
      <a href="<?= e(url('/admin/c/zone')) ?>"<?= $on('/admin/c/zone') ?>><?= icon('map') ?> Zone</a>
      <a href="<?= e(url('/admin/c/articole')) ?>"<?= $on('/admin/c/articole') ?>><?= icon('pen') ?> Blog</a>
      <a href="<?= e(url('/admin/c/pagini')) ?>"<?= $on('/admin/c/pagini') ?>><?= icon('file') ?> Pagini</a>
      <a href="<?= e(url('/admin/c/proiecte')) ?>"<?= $on('/admin/c/proiecte') ?>><?= icon('award') ?> Proiecte</a>
      <a href="<?= e(url('/admin/c/testimoniale')) ?>"<?= $on('/admin/c/testimoniale') ?>><?= icon('quote') ?> Testimoniale</a>
      <a href="<?= e(url('/admin/media')) ?>"<?= $on('/admin/media') ?>><?= icon('image') ?> Media</a>
      <a href="<?= e(url('/admin/setari/prima')) ?>"<?= $on('/admin/setari/prima') ?>><?= icon('monitor') ?> Prima pagină</a>
      <?php endif; ?>
      <?php if (Auth::can('seo')): ?>
      <div class="grp">SEO</div>
      <a href="<?= e(url('/admin/seo')) ?>"<?= $on('/admin/seo', true) ?>><?= icon('search') ?> Setări SEO</a>
      <a href="<?= e(url('/admin/seo/audit')) ?>"<?= $on('/admin/seo/audit') ?>><?= icon('check-circle') ?> Audit SEO</a>
      <a href="<?= e(url('/admin/seo/redirectionari')) ?>"<?= $on('/admin/seo/redirectionari') ?>><?= icon('link') ?> Redirecționări</a>
      <?php endif; ?>
      <?php if (Auth::can('settings')): ?>
      <div class="grp">Administrare</div>
      <a href="<?= e(url('/admin/setari/firma')) ?>"<?= ($path !== '/admin/setari/prima' && str_starts_with($path, '/admin/setari')) ? ' class="on"' : '' ?>><?= icon('settings') ?> Setări</a>
      <a href="<?= e(url('/admin/utilizatori')) ?>"<?= $on('/admin/utilizatori') ?>><?= icon('users') ?> Utilizatori</a>
      <a href="<?= e(url('/admin/sistem')) ?>"<?= $on('/admin/sistem') ?>><?= icon('box') ?> Sistem & actualizări</a>
      <?php endif; ?>
    </nav>
    <div class="foot">
      <a href="<?= e(url('/')) ?>" target="_blank"><?= icon('external') ?> Vezi site-ul</a>
      <span class="small" style="display:block;padding:8px 12px;color:#5b667d">v<?= e(APP_VERSION) ?></span>
    </div>
  </aside>
  <div class="main">
    <header class="top">
      <button class="btn btn-sm burger-a" type="button" data-side-toggle aria-label="Meniu"><?= icon('menu') ?></button>
      <strong><?= e($title ?? '') ?></strong>
      <span class="spacer"></span>
      <button class="btn btn-sm btn-ghost" type="button" data-theme-toggle title="Temă luminoasă / întunecată"><?= icon('moon') ?></button>
      <?php if ($u): ?>
      <a class="btn btn-sm btn-ghost" href="<?= e(url('/admin/cont')) ?>"><?= icon('users') ?> <?= e($u['name']) ?></a>
      <form method="post" action="<?= e(url('/admin/logout')) ?>"><?= Csrf::field() ?><button class="btn btn-sm" type="submit"><?= icon('logout') ?> Ieșire</button></form>
      <?php endif; ?>
    </header>
    <main class="content">
      <?php foreach (flashes() as $f): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<dialog id="media-dialog">
  <div class="dh"><strong>Bibliotecă media</strong><div class="actions"><label class="btn btn-sm btn-p"><?= icon('upload') ?> Încarcă<input type="file" accept="image/*" multiple hidden></label><span class="up-status small muted"></span><button class="btn btn-sm" type="button" data-close>Închide</button></div></div>
  <div class="db"><div class="media-grid"></div></div>
</dialog>
<script src="<?= e(asset('js/vendor/qrcode.js')) ?>" defer></script>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
