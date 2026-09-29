<?php
declare(strict_types=1);

/** @var \App\Core\Router $router */

use App\Controllers\Site\HomeController;
use App\Controllers\Site\ServiceController;
use App\Controllers\Site\LocationController;
use App\Controllers\Site\BlogController;
use App\Controllers\Site\PageController;
use App\Controllers\Site\FormController;
use App\Controllers\Site\SeoController;
use App\Controllers\Site\TrackController;
use App\Controllers\Admin;

// ---------- Site public ----------
$router->get('/', [HomeController::class, 'index']);
$router->get('/servicii', [ServiceController::class, 'index']);
$router->get('/servicii/{slug:[a-z0-9-]+}', [ServiceController::class, 'show']);
$router->get('/zone', [LocationController::class, 'index']);
$router->get('/zone/{slug:[a-z0-9-]+}', [LocationController::class, 'show']);
$router->get('/blog', [BlogController::class, 'index']);
$router->get('/blog/pagina/{page:\d+}', [BlogController::class, 'index']);
$router->get('/blog/{slug:[a-z0-9-]+}', [BlogController::class, 'show']);
$router->get('/proiecte', [PageController::class, 'projects']);
$router->get('/proiecte/{slug:[a-z0-9-]+}', [PageController::class, 'project']);
$router->get('/contact', [PageController::class, 'contact']);
$router->get('/despre-noi', [PageController::class, 'about']);

$router->get('/api/form-token', [FormController::class, 'token']);
$router->post('/api/contact', [FormController::class, 'contact']);
$router->post('/api/newsletter', [FormController::class, 'newsletter']);
$router->post('/api/chat', [\App\Controllers\Site\ChatController::class, 'send']);
$router->get('/api/chat/{token:[A-Za-z0-9_-]+}', [\App\Controllers\Site\ChatController::class, 'history']);
$router->get('/multumim', [FormController::class, 'thanks']);
$router->get('/newsletter/confirmare/{token:[A-Za-z0-9_-]+}', [FormController::class, 'confirm']);
$router->any('/newsletter/dezabonare/{token:[A-Za-z0-9_-]+}', [FormController::class, 'unsubscribe']);

$router->get('/e/o/{token:[A-Za-z0-9_-]+}.gif', [TrackController::class, 'open']);
$router->get('/e/c/{token:[A-Za-z0-9_-]+}/{link:\d+}', [TrackController::class, 'click']);

$router->get('/sitemap.xml', [SeoController::class, 'sitemapIndex']);
$router->get('/sitemap-{type:pagini|servicii|zone|blog|proiecte}.xml', [SeoController::class, 'sitemap']);
$router->get('/sitemap.xsl', [SeoController::class, 'xsl']);
$router->get('/robots.txt', [SeoController::class, 'robots']);
$router->get('/llms.txt', [SeoController::class, 'llms']);
$router->get('/manifest.webmanifest', [SeoController::class, 'manifest']);
$router->get('/feed.xml', [SeoController::class, 'feed']);
$router->get('/og/{type:[a-z]+}/{slug:[a-z0-9-]+}.png', [SeoController::class, 'og']);
$router->get('/{key:[a-f0-9]{32}}.txt', [SeoController::class, 'indexNowKey']);
$router->get('/cron', [TrackController::class, 'cron']);

// ---------- Panou de control ----------
$router->any('/admin', [Admin\AuthController::class, 'root']);
$router->any('/admin/login', [Admin\AuthController::class, 'login']);
$router->any('/admin/2fa', [Admin\AuthController::class, 'twofa']);
$router->post('/admin/logout', [Admin\AuthController::class, 'logout']);
$router->any('/admin/parola-uitata', [Admin\AuthController::class, 'forgot']);
$router->any('/admin/resetare/{token:[A-Za-z0-9_-]+}', [Admin\AuthController::class, 'reset']);
$router->get('/admin/dashboard', [Admin\DashboardController::class, 'index']);

// conținut (CRUD generic)
$router->get('/admin/c/{res:[a-z]+}', [Admin\ContentController::class, 'index']);
$router->any('/admin/c/{res:[a-z]+}/nou', [Admin\ContentController::class, 'edit']);
$router->any('/admin/c/{res:[a-z]+}/{id:\d+}', [Admin\ContentController::class, 'edit']);
$router->post('/admin/c/{res:[a-z]+}/{id:\d+}/sterge', [Admin\ContentController::class, 'delete']);
$router->post('/admin/c/{res:[a-z]+}/ordine', [Admin\ContentController::class, 'reorder']);

$router->get('/admin/media', [Admin\MediaController::class, 'index']);
$router->post('/admin/media/upload', [Admin\MediaController::class, 'upload']);
$router->post('/admin/media/{id:\d+}', [Admin\MediaController::class, 'update']);
$router->post('/admin/media/{id:\d+}/sterge', [Admin\MediaController::class, 'delete']);
$router->get('/admin/media/json', [Admin\MediaController::class, 'json']);

// CRM
$router->get('/admin/crm', [Admin\CrmController::class, 'pipeline']);
$router->get('/admin/crm/contacte', [Admin\CrmController::class, 'contacts']);
$router->any('/admin/crm/contacte/nou', [Admin\CrmController::class, 'contactEdit']);
$router->any('/admin/crm/contacte/{id:\d+}', [Admin\CrmController::class, 'contactShow']);
$router->any('/admin/crm/contacte/{id:\d+}/editare', [Admin\CrmController::class, 'contactEdit']);
$router->post('/admin/crm/contacte/{id:\d+}/sterge', [Admin\CrmController::class, 'contactDelete']);
$router->get('/admin/crm/export', [Admin\CrmController::class, 'export']);
$router->any('/admin/crm/import', [Admin\CrmController::class, 'import']);
$router->any('/admin/crm/oportunitati/nou', [Admin\CrmController::class, 'dealEdit']);
$router->any('/admin/crm/oportunitati/{id:\d+}', [Admin\CrmController::class, 'dealEdit']);
$router->post('/admin/crm/oportunitati/{id:\d+}/etapa', [Admin\CrmController::class, 'dealStage']);
$router->post('/admin/crm/oportunitati/{id:\d+}/sterge', [Admin\CrmController::class, 'dealDelete']);
$router->post('/admin/crm/activitate', [Admin\CrmController::class, 'activityAdd']);
$router->post('/admin/crm/activitate/{id:\d+}/gata', [Admin\CrmController::class, 'activityDone']);
$router->post('/admin/crm/activitate/{id:\d+}/sterge', [Admin\CrmController::class, 'activityDelete']);
$router->get('/admin/crm/sarcini', [Admin\CrmController::class, 'tasks']);
$router->get('/admin/formulare', [Admin\CrmController::class, 'submissions']);
$router->get('/admin/formulare/{id:\d+}', [Admin\CrmController::class, 'submission']);

// Email marketing
$router->get('/admin/email', [Admin\EmailController::class, 'index']);
$router->any('/admin/email/nou', [Admin\EmailController::class, 'edit']);
$router->any('/admin/email/{id:\d+}', [Admin\EmailController::class, 'edit']);
$router->get('/admin/email/{id:\d+}/raport', [Admin\EmailController::class, 'report']);
$router->get('/admin/email/{id:\d+}/previzualizare', [Admin\EmailController::class, 'preview']);
$router->post('/admin/email/{id:\d+}/test', [Admin\EmailController::class, 'test']);
$router->post('/admin/email/{id:\d+}/lanseaza', [Admin\EmailController::class, 'launch']);
$router->post('/admin/email/{id:\d+}/lot', [Admin\EmailController::class, 'batch']);
$router->post('/admin/email/{id:\d+}/pauza', [Admin\EmailController::class, 'pause']);
$router->post('/admin/email/{id:\d+}/duplica', [Admin\EmailController::class, 'duplicate']);
$router->post('/admin/email/{id:\d+}/sterge', [Admin\EmailController::class, 'delete']);
$router->post('/admin/email/audienta', [Admin\EmailController::class, 'audienceCount']);
$router->get('/admin/abonati', [Admin\EmailController::class, 'subscribers']);
$router->get('/admin/email/jurnal', [Admin\EmailController::class, 'log']);

// Asistent AI
$router->get('/admin/asistent', [Admin\AssistantController::class, 'index']);
$router->get('/admin/asistent/{id:\d+}', [Admin\AssistantController::class, 'show']);
$router->post('/admin/asistent/test', [Admin\AssistantController::class, 'test']);
$router->post('/admin/asistent/{id:\d+}/sterge', [Admin\AssistantController::class, 'delete']);

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
