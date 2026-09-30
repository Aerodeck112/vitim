<?php
declare(strict_types=1);

/** @var \App\Core\Router $router */

use App\Controllers\Site\HomeController;
use App\Controllers\Site\ShopController;
use App\Controllers\Site\CartController;
use App\Controllers\Site\CheckoutController;
use App\Controllers\Site\PaymentController;
use App\Controllers\Site\PageController;
use App\Controllers\Site\FormController;
use App\Controllers\Site\SeoController;
use App\Controllers\Admin;

// ---------- Magazin ----------
$router->get('/', [HomeController::class, 'index']);
$router->get('/produse', [ShopController::class, 'index']);
$router->get('/categorie/{slug:[a-z0-9-]+}', [ShopController::class, 'category']);
$router->get('/produs/{slug:[a-z0-9-]+}', [ShopController::class, 'product']);

$router->get('/cos', [CartController::class, 'show']);
$router->post('/cos/adauga', [CartController::class, 'add']);
$router->post('/cos/actualizeaza', [CartController::class, 'update']);
$router->post('/cos/cupon', [CartController::class, 'coupon']);
$router->get('/api/cos', [CartController::class, 'summary']);

$router->get('/finalizare', [CheckoutController::class, 'form']);
$router->post('/finalizare', [CheckoutController::class, 'place']);
$router->get('/comanda/{token:[A-Za-z0-9_-]+}', [CheckoutController::class, 'order']);
$router->post('/comanda/{token:[A-Za-z0-9_-]+}/plateste', [CheckoutController::class, 'pay']);
$router->any('/urmarire-comanda', [CheckoutController::class, 'track']);

$router->get('/plata/bt/retur', [PaymentController::class, 'btReturn']);
$router->any('/plata/bt/callback', [PaymentController::class, 'btCallback']);

// ---------- Pagini ----------
$router->get('/despre-noi', [PageController::class, 'about']);
$router->get('/contact', [PageController::class, 'contact']);
$router->get('/blog', [PageController::class, 'blog']);
$router->get('/blog/{slug:[a-z0-9-]+}', [PageController::class, 'post']);
$router->get('/api/form-token', [FormController::class, 'token']);
$router->post('/api/contact', [FormController::class, 'contact']);
$router->post('/api/recenzie', [FormController::class, 'review']);

// ---------- SEO ----------
$router->get('/sitemap.xml', [SeoController::class, 'sitemapIndex']);
$router->get('/sitemap-{type:pagini|produse|categorii|blog}.xml', [SeoController::class, 'sitemap']);
$router->get('/sitemap.xsl', [SeoController::class, 'xsl']);
$router->get('/robots.txt', [SeoController::class, 'robots']);
$router->get('/llms.txt', [SeoController::class, 'llms']);
$router->get('/manifest.webmanifest', [SeoController::class, 'manifest']);
$router->get('/feed/google-merchant.xml', [SeoController::class, 'merchant']);
$router->get('/og/{type:[a-z]+}/{slug:[a-z0-9-]+}.png', [SeoController::class, 'og']);
$router->get('/{key:[a-f0-9]+}.txt', [SeoController::class, 'indexNowKey']);
$router->get('/cron', [SeoController::class, 'cron']);

// ---------- Panou de control ----------
$router->any('/admin', [Admin\AuthController::class, 'root']);
$router->any('/admin/login', [Admin\AuthController::class, 'login']);
$router->any('/admin/2fa', [Admin\AuthController::class, 'twofa']);
$router->post('/admin/logout', [Admin\AuthController::class, 'logout']);
$router->any('/admin/parola-uitata', [Admin\AuthController::class, 'forgot']);
$router->any('/admin/resetare/{token:[A-Za-z0-9_-]+}', [Admin\AuthController::class, 'reset']);
$router->get('/admin/dashboard', [Admin\DashboardController::class, 'index']);

// comenzi
$router->get('/admin/comenzi', [Admin\OrderController::class, 'index']);
$router->get('/admin/comenzi/export', [Admin\OrderController::class, 'export']);
$router->get('/admin/comenzi/{id:\d+}', [Admin\OrderController::class, 'show']);
$router->get('/admin/comenzi/{id:\d+}/tipareste', [Admin\OrderController::class, 'printout']);
$router->post('/admin/comenzi/{id:\d+}/stare', [Admin\OrderController::class, 'status']);
$router->post('/admin/comenzi/{id:\d+}/livrare', [Admin\OrderController::class, 'shipping']);
$router->post('/admin/comenzi/{id:\d+}/nota', [Admin\OrderController::class, 'note']);
$router->post('/admin/comenzi/{id:\d+}/client', [Admin\OrderController::class, 'customer']);
$router->post('/admin/comenzi/{id:\d+}/plata/{op:verifica|incaseaza|anuleaza|ramburseaza|marcheaza}', [Admin\OrderController::class, 'payment']);
$router->post('/admin/comenzi/{id:\d+}/email', [Admin\OrderController::class, 'resend']);
$router->get('/admin/clienti', [Admin\OrderController::class, 'customers']);

// produse
$router->get('/admin/produse', [Admin\ProductController::class, 'index']);
$router->any('/admin/produse/nou', [Admin\ProductController::class, 'edit']);
$router->any('/admin/produse/{id:\d+}', [Admin\ProductController::class, 'edit']);
$router->post('/admin/produse/{id:\d+}/sterge', [Admin\ProductController::class, 'delete']);
$router->post('/admin/produse/{id:\d+}/duplica', [Admin\ProductController::class, 'duplicate']);
$router->post('/admin/produse/rapid', [Admin\ProductController::class, 'quick']);

// conținut (CRUD generic: categorii, pagini, cupoane, articole, recenzii)
$router->get('/admin/c/{res:[a-z]+}', [Admin\ContentController::class, 'index']);
$router->any('/admin/c/{res:[a-z]+}/nou', [Admin\ContentController::class, 'edit']);
$router->any('/admin/c/{res:[a-z]+}/{id:\d+}', [Admin\ContentController::class, 'edit']);
$router->post('/admin/c/{res:[a-z]+}/{id:\d+}/sterge', [Admin\ContentController::class, 'delete']);

$router->get('/admin/mesaje', [Admin\MessageController::class, 'index']);
$router->get('/admin/mesaje/{id:\d+}', [Admin\MessageController::class, 'show']);
$router->post('/admin/mesaje/{id:\d+}/sterge', [Admin\MessageController::class, 'delete']);

$router->get('/admin/media', [Admin\MediaController::class, 'index']);
$router->post('/admin/media/upload', [Admin\MediaController::class, 'upload']);
$router->post('/admin/media/{id:\d+}', [Admin\MediaController::class, 'update']);
$router->post('/admin/media/{id:\d+}/sterge', [Admin\MediaController::class, 'delete']);
$router->get('/admin/media/json', [Admin\MediaController::class, 'json']);

// SEO
$router->get('/admin/seo', [Admin\SeoController::class, 'index']);
$router->post('/admin/seo', [Admin\SeoController::class, 'save']);
$router->get('/admin/seo/redirectionari', [Admin\SeoController::class, 'redirects']);
$router->post('/admin/seo/redirectionari', [Admin\SeoController::class, 'redirectSave']);
$router->post('/admin/seo/redirectionari/{id:\d+}/sterge', [Admin\SeoController::class, 'redirectDelete']);
$router->post('/admin/seo/404/{id:\d+}/sterge', [Admin\SeoController::class, 'notFoundDelete']);
$router->get('/admin/seo/audit', [Admin\SeoController::class, 'audit']);
$router->post('/admin/seo/indexnow', [Admin\SeoController::class, 'pingIndexNow']);

// Setări & sistem
$router->get('/admin/setari/{tab:[a-z]+}', [Admin\SettingsController::class, 'index']);
$router->get('/admin/setari', [Admin\SettingsController::class, 'index']);
$router->post('/admin/setari/{tab:[a-z]+}', [Admin\SettingsController::class, 'save']);
$router->post('/admin/setari/email/test', [Admin\SettingsController::class, 'testEmail']);
$router->post('/admin/setari/plati/test', [Admin\SettingsController::class, 'testBt']);
$router->post('/admin/cache/goleste', [Admin\SettingsController::class, 'clearCache']);
$router->get('/admin/utilizatori', [Admin\UserController::class, 'index']);
$router->any('/admin/utilizatori/nou', [Admin\UserController::class, 'edit']);
$router->any('/admin/utilizatori/{id:\d+}', [Admin\UserController::class, 'edit']);
$router->post('/admin/utilizatori/{id:\d+}/sterge', [Admin\UserController::class, 'delete']);
$router->any('/admin/cont', [Admin\UserController::class, 'account']);
$router->any('/admin/cont/2fa', [Admin\UserController::class, 'twofa']);
$router->get('/admin/sistem', [Admin\SystemController::class, 'index']);
$router->post('/admin/sistem/actualizare', [Admin\SystemController::class, 'update']);
$router->post('/admin/sistem/backup', [Admin\SystemController::class, 'backup']);
$router->get('/admin/sistem/backup/{file:[A-Za-z0-9._-]+}', [Admin\SystemController::class, 'download']);
$router->post('/admin/sistem/backup/{file:[A-Za-z0-9._-]+}/sterge', [Admin\SystemController::class, 'deleteBackup']);
$router->post('/admin/sistem/restaurare/{file:[A-Za-z0-9._-]+}', [Admin\SystemController::class, 'restoreCode']);
$router->get('/admin/sistem/jurnal', [Admin\SystemController::class, 'logs']);

// Pagini generice (trebuie să fie ultima rută)
$router->get('/{slug:[a-z0-9-]+}', [PageController::class, 'show']);
