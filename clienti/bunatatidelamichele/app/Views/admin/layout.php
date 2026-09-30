<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;

$u = Auth::user();
$path = \App\Core\App::$path;
$on = fn(string $p, bool $exact = false) => ($exact ? $path === $p : ($path === $p || str_starts_with($path, $p . '/'))) ? ' class="on"' : '';
$newOrders = $u ? (int)DB::val("SELECT COUNT(*) FROM orders WHERE status = 'noua'") : 0;
$unread = $u ? (int)DB::val('SELECT COUNT(*) FROM messages WHERE read_at IS NULL AND spam_score < 6') : 0;
$pendingReviews = $u ? (int)DB::val('SELECT COUNT(*) FROM reviews WHERE published = 0') : 0;
$lowStock = $u ? (int)DB::val('SELECT COUNT(*) FROM products WHERE published = 1 AND manage_stock = 1 AND stock <= ?', [(int)setting('low_stock_threshold', '5')]) : 0;
?><!doctype html>
<html lang="ro" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf" content="<?= e(Csrf::token()) ?>">
<meta name="base" content="<?= e(base_path()) ?>">
<title><?= e($title ?? 'Panou') ?> · <?= e(setting('brand_name')) ?></title>
<script>try{var t=localStorage.getItem('admin-theme');if(t)document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<link rel="icon" href="<?= e(url('/assets/img/favicon-32.png')) ?>" type="image/png">
</head>
<body>
<div class="app">
  <aside class="side">
    <div class="brand"><img src="<?= e(url('/assets/img/logo.png')) ?>" alt=""><div>Michele<small>panou magazin</small></div></div>
    <nav>
      <a href="<?= e(url('/admin/dashboard')) ?>"<?= $on('/admin/dashboard') ?>><?= icon('dashboard') ?> Tablou de bord</a>
      <?php if (Auth::can('orders')): ?>
      <div class="grp">Vânzări</div>
      <a href="<?= e(url('/admin/comenzi')) ?>"<?= $on('/admin/comenzi') ?>><?= icon('receipt') ?> Comenzi <?php if ($newOrders): ?><span class="badge b-info"><?= $newOrders ?></span><?php endif; ?></a>
      <a href="<?= e(url('/admin/clienti')) ?>"<?= $on('/admin/clienti') ?>><?= icon('users') ?> Clienți</a>
      <a href="<?= e(url('/admin/c/cupoane')) ?>"<?= $on('/admin/c/cupoane') ?>><?= icon('percent') ?> Coduri de reducere</a>
      <?php endif; ?>
      <?php if (Auth::can('messages')): ?>
      <a href="<?= e(url('/admin/mesaje')) ?>"<?= $on('/admin/mesaje') ?>><?= icon('inbox') ?> Mesaje <?php if ($unread): ?><span class="badge b-err"><?= $unread ?></span><?php endif; ?></a>
      <?php endif; ?>
      <?php if (Auth::can('products')): ?>
      <div class="grp">Catalog</div>
      <a href="<?= e(url('/admin/produse')) ?>"<?= $on('/admin/produse') ?>><?= icon('coffee') ?> Produse <?php if ($lowStock): ?><span class="badge b-warn"><?= $lowStock ?></span><?php endif; ?></a>
      <a href="<?= e(url('/admin/c/categorii')) ?>"<?= $on('/admin/c/categorii') ?>><?= icon('grid') ?> Categorii</a>
      <a href="<?= e(url('/admin/c/recenzii')) ?>"<?= $on('/admin/c/recenzii') ?>><?= icon('star') ?> Recenzii <?php if ($pendingReviews): ?><span class="badge b-warn"><?= $pendingReviews ?></span><?php endif; ?></a>
      <?php endif; ?>
      <?php if (Auth::can('content')): ?>
      <div class="grp">Conținut site</div>
      <a href="<?= e(url('/admin/setari/prima')) ?>"<?= $on('/admin/setari/prima') ?>><?= icon('monitor') ?> Prima pagină</a>
      <a href="<?= e(url('/admin/c/pagini')) ?>"<?= $on('/admin/c/pagini') ?>><?= icon('file') ?> Pagini</a>
      <a href="<?= e(url('/admin/c/articole')) ?>"<?= $on('/admin/c/articole') ?>><?= icon('pen') ?> Blog</a>
      <a href="<?= e(url('/admin/media')) ?>"<?= $on('/admin/media') ?>><?= icon('image') ?> Media</a>
      <?php endif; ?>
      <?php if (Auth::can('seo')): ?>
      <div class="grp">SEO</div>
      <a href="<?= e(url('/admin/seo')) ?>"<?= $on('/admin/seo', true) ?>><?= icon('search') ?> Setări SEO</a>
      <a href="<?= e(url('/admin/seo/audit')) ?>"<?= $on('/admin/seo/audit') ?>><?= icon('check-circle') ?> Audit SEO</a>
      <a href="<?= e(url('/admin/seo/redirectionari')) ?>"<?= $on('/admin/seo/redirectionari') ?>><?= icon('link') ?> Redirecționări</a>
      <?php endif; ?>
      <?php if (Auth::can('settings')): ?>
      <div class="grp">Administrare</div>
      <a href="<?= e(url('/admin/setari/magazin')) ?>"<?= ($path !== '/admin/setari/prima' && str_starts_with($path, '/admin/setari')) ? ' class="on"' : '' ?>><?= icon('settings') ?> Setări</a>
      <a href="<?= e(url('/admin/utilizatori')) ?>"<?= $on('/admin/utilizatori') ?>><?= icon('users') ?> Utilizatori</a>
      <a href="<?= e(url('/admin/sistem')) ?>"<?= $on('/admin/sistem') ?>><?= icon('box') ?> Sistem & backup</a>
      <?php endif; ?>
    </nav>
    <div class="foot">
      <a href="<?= e(url('/')) ?>" target="_blank"><?= icon('external') ?> Vezi magazinul</a>
      <span class="small" style="display:block;padding:8px 12px;color:#7d6a5c">v<?= e(APP_VERSION) ?> · realizat de VITIM</span>
    </div>
  </aside>
  <div class="main">
    <header class="top">
      <button class="btn btn-sm burger-a" type="button" data-side-toggle aria-label="Meniu"><?= icon('menu') ?></button>
      <strong><?= e($title ?? '') ?></strong>
      <span class="spacer"></span>
      <?php if (\App\Core\BtIpay::mode() === 'test' && setting('pay_card_enabled') === '1'): ?><a class="badge b-warn" href="<?= e(url('/admin/setari/plati')) ?>" title="Plățile cu cardul sunt în modul de test">BT iPay: TEST</a><?php endif; ?>
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
