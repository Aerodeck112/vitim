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
        $seo = $this->seo($page['title'] . ' – ' . Settings::get('brand_name'), $page['subtitle'] ?: 'VITIM ajută companiile să își administreze infrastructura IT, securitatea, automatizările, AI-ul și prezența digitală printr-un singur partener tehnologic.', [['Despre noi', '/despre-noi']]);
        $seo->pageType = 'AboutPage';
        $this->applyMeta($seo, $page);
        [$body] = Sanitizer::withToc(Site::companyVars((string)$page['body']));
        return $this->view('about', [
            'page' => $page,
            'body' => $body,
            'testimonials' => DB::all('SELECT * FROM testimonials WHERE published = 1 ORDER BY sort LIMIT 3'),
            'projects' => DB::all("SELECT * FROM projects WHERE published = 1 AND featured = 1 AND cover IS NOT NULL AND cover <> '' ORDER BY sort, id DESC LIMIT 3"),
        ], $seo);
    }

    /** Pagina produsului VITIM AI: platforma, beneficiile pentru client și cum o administrează VITIM. */
    public function platform(): string
    {
        $url = abs_url('/vitim-ai');
        $faq = self::platformFaq();
        $seo = $this->seo('VITIM AI – AI pentru firme: lead-uri, CRM și automatizări',
            'VITIM AI răspunde clienților pe site, WhatsApp și email, califică lead-uri, urmărește oportunitățile în CRM și automatizează procesele firmei.',
            [['VITIM AI', '/vitim-ai']]);
        $seo->schema[] = [
            '@type' => 'SoftwareApplication', '@id' => $url . '#produs', 'name' => 'VITIM AI', 'url' => $url,
            'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'inLanguage' => 'ro',
            'description' => 'Platformă pentru firme: asistent AI pe site, CRM, inbox, campanii email / SMS / WhatsApp, automatizări, formulare, integrare WooCommerce, administrare și rapoarte lunare.',
            'provider' => ['@id' => \App\Core\Seo::orgId()],
        ];
        if ($f = \App\Core\Seo::faqSchema($faq, $url)) {
            $seo->schema[] = $f;
        }

        return $this->view('platform', ['faq' => $faq, 'pageCss' => ['platform.css']], $seo);
    }

    /** @return list<array{q: string, a: string}> */
    public static function platformFaq(): array
    {
        return [
            ['q' => 'Trebuie să mă pricep la tehnologie ca să folosesc VITIM AI?', 'a' => 'Nu. Noi configurăm totul: asistentul, site-ul, conturile de trimitere, primele campanii și automatizări. Tu intri în panou ca să vezi cererile, să răspunzi clienților și să urmărești rezultatele, cu ecrane în limba română, gândite pentru oameni ocupați.'],
            ['q' => 'Asistentul AI poate spune lucruri greșite despre firma mea?', 'a' => 'Asistentul răspunde din informațiile pe care le aprobi tu (servicii, prețuri orientative, program, zone, condiții). Când nu știe, nu inventează: preia datele clientului și îți trimite cererea. Poți prelua oricând conversația în timp real.'],
            ['q' => 'Funcționează cu site-ul meu actual?', 'a' => 'Da. Pe WordPress și WooCommerce se conectează printr-un modul VITIM, instalat de noi. Pe orice alt site se adaugă o singură linie de cod. Nu trebuie să schimbi site-ul.'],
            ['q' => 'Cât costă trimiterea campaniilor?', 'a' => 'Emailurile pleacă din adresa firmei tale, iar SMS-urile și mesajele WhatsApp din conturile tale. Plătești direct furnizorului doar ce trimiți, fără taxe pe numărul de contacte cum au platformele străine.'],
            ['q' => 'Ce se întâmplă cu datele clienților mei?', 'a' => 'Datele fiecărei firme sunt separate de ale celorlalți clienți VITIM și nu sunt folosite în alt scop. Acordurile de marketing și de cookie-uri se înregistrează cu dovadă (când, unde, ce a acceptat), dezabonarea e automată, iar parolele conturilor tale sunt criptate.'],
            ['q' => 'Ce funcționează acum în VITIM AI și ce urmează?', 'a' => 'Acum funcționează: asistentul AI pe site, inboxul cu conversațiile de pe site, email, WhatsApp și SMS, contactele și lead-urile pe etape, campaniile, automatizările, formularele, integrarea cu WordPress și WooCommerce, rolurile de acces și jurnalul de acțiuni. Integrările cu alte sisteme (calendar, facturare, CRM sau ERP existent) le implementăm la cerere. Raportul zilnic, asistentul intern pe documente și nivelurile de autonomie sunt în dezvoltare. Pe pagină, fiecare funcție are marcat statusul.'],
            ['q' => 'Pot începe doar cu o parte din platformă?', 'a' => 'Da. Mulți clienți încep cu asistentul AI pe site și cu inboxul de cereri, apoi adaugă campaniile, automatizările sau administrarea site-ului. Îți recomandăm ce merită pentru firma ta.'],
        ];
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
        $seo = $this->seo('Proiecte și studii de caz', 'Proiecte reale VITIM pentru companii: platforme custom, automatizări, WooCommerce, infrastructură și marketing – problema, soluția VITIM și ce am implementat.', [['Proiecte', '/proiecte']]);
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
