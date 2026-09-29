<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\DB;
use App\Core\Sanitizer;
use App\Core\Seo;
use App\Core\Settings;
use App\Core\Site;
use App\Core\View;

final class PageController extends SiteController
{
    public function show(string $slug): string
    {
        $page = DB::row('SELECT * FROM pages WHERE slug = ? AND published = 1', [$slug]);
        if (!$page || in_array($slug, ['contact', 'despre-noi'], true)) {
            return $this->notFound();
        }
        $seo = $this->seo($page['title'], $page['subtitle'] ?: excerpt(Site::companyVars((string)$page['body']), 160), [[$page['title'], '/' . $page['slug']]]);
        $this->applyMeta($seo, $page);
        [$body, $toc] = Sanitizer::withToc(Site::companyVars((string)$page['body']));
        return $this->view('page', ['page' => $page, 'body' => $body, 'toc' => $toc], $seo);
    }

    public function about(): string
    {
        $page = DB::row("SELECT * FROM pages WHERE slug = 'despre-noi'") ?? ['title' => 'Despre noi', 'subtitle' => '', 'body' => '', 'slug' => 'despre-noi'];
        $seo = $this->seo($page['title'] . ' – ' . Settings::get('brand_name'), $page['subtitle'] ?: 'Cine suntem, cum lucrăm și de ce firmele din Mureș, Bistrița-Năsăud și Alba ne aleg pentru IT, securitate, marketing și AI.', [['Despre noi', '/despre-noi']]);
        $seo->pageType = 'AboutPage';
        $this->applyMeta($seo, $page);
        [$body] = Sanitizer::withToc(Site::companyVars((string)$page['body']));
        return $this->view('about', [
            'page' => $page,
            'body' => $body,
            'testimonials' => DB::all('SELECT * FROM testimonials WHERE published = 1 ORDER BY sort LIMIT 3'),
        ], $seo);
    }

    public function contact(): string
    {
        $page = DB::row("SELECT * FROM pages WHERE slug = 'contact'") ?? ['title' => 'Contact', 'subtitle' => '', 'body' => '', 'slug' => 'contact'];
        $seo = $this->seo('Contact – ' . Settings::get('brand_name') . ' | ' . Settings::get('phone'), $page['subtitle'] ?: 'Contactează VITIM pentru suport IT, securitate, recuperări de date, SEO, reclame sau agenți AI. Telefon ' . Settings::get('phone') . ', email ' . Settings::get('email') . '.', [['Contact', '/contact']]);
        $seo->pageType = 'ContactPage';
        $seo->rawTitle = true;
        $this->applyMeta($seo, $page);
        $service = preg_replace('/[^a-z0-9-]/', '', (string)($_GET['serviciu'] ?? ''));
        return $this->view('contact', ['page' => $page, 'body' => Site::companyVars((string)$page['body']), 'service' => $service], $seo);
    }

    public function projects(): string
    {
        $items = DB::all('SELECT p.*, s.title AS service_title FROM projects p LEFT JOIN services s ON s.id = p.service_id WHERE p.published = 1 ORDER BY p.sort, p.id DESC');
        if (!$items) {
            return $this->notFound();
        }
        $seo = $this->seo('Proiecte și studii de caz', 'Proiecte reale VITIM: infrastructură IT, securitate, SEO, campanii și automatizări AI – problema, soluția și rezultatele.', [['Proiecte', '/proiecte']]);
        $seo->pageType = 'CollectionPage';
        return $this->view('projects', ['items' => $items], $seo);
    }

    public function project(string $slug): string
    {
        $p = DB::row('SELECT p.*, s.title AS service_title, s.slug AS service_slug FROM projects p LEFT JOIN services s ON s.id = p.service_id WHERE p.slug = ? AND p.published = 1', [$slug]);
        if (!$p) {
            return $this->notFound();
        }
        $seo = $this->seo($p['title'], $p['summary'], [['Proiecte', '/proiecte'], [$p['title'], '/proiecte/' . $p['slug']]]);
        $seo->type = 'article';
        if ($p['cover']) {
            $seo->image = abs_url(upload_url($p['cover']));
        }
        $this->applyMeta($seo, $p);
        $seo->schema[] = ['@type' => 'CreativeWork', 'name' => $p['title'], 'about' => $p['service_title'], 'creator' => ['@id' => Seo::orgId()], 'abstract' => $p['summary']];
        return $this->view('project', ['p' => $p, 'results' => json_list($p['results'])], $seo);
    }

    public function notFound(bool $gone = false): string
    {
        if (!$gone) {
            \App\Core\App::tryRedirect(\App\Core\App::$path); // redirecționări definite în panou au prioritate
        }
        http_response_code($gone ? 410 : 404);
        $seo = $this->seo($gone ? 'Pagina nu mai există' : 'Pagina nu a fost găsită');
        $seo->noindex = true;
        return View::render('site/404', ['gone' => $gone, 'seo' => $seo], 'site/layout');
    }

    public function renderWith(string $view, array $data, Seo $seo): string
    {
        return $this->view($view, $data, $seo);
    }
}
