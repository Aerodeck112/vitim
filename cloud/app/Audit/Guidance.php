<?php

declare(strict_types=1);

namespace App\Audit;

/**
 * „Cum rezolvi”: categoria și pașii de remediere pentru fiecare tip de problemă (din auditul extern și din scanarea
 * pluginului). Textele stau în panou, nu în plugin, ca să poată fi îmbunătățite fără să actualizezi site-urile.
 */
final class Guidance
{
    public const CATEGORIES = [
        'security' => 'Securitate',
        'seo' => 'SEO',
        'legal' => 'Legal (România)',
        'updates' => 'Actualizări',
        'performance' => 'Viteză',
    ];

    /** cod (sau prefix înainte de „:”) => [categorie, cum rezolvi] */
    private const GUIDE = [
        // ---------- actualizări (plugin) ----------
        'core_update' => ['updates', "Fă întâi un backup, apoi actualizează din butonul „Actualizează WordPress” sau din WordPress → Panou → Actualizări.\nVerifică după aceea formularul de contact, coșul (dacă e magazin) și paginile principale."],
        'plugin_update' => ['updates', 'Actualizează pluginul din buton. Pentru pluginuri mari (constructor de pagini, WooCommerce) fă înainte un backup și verifică site-ul după.'],
        'theme_update' => ['updates', 'Actualizează tema din buton. Dacă tema a fost modificată direct (fără temă copil), modificările se pierd: verifică întâi cu echipa care a făcut site-ul.'],
        'php_old' => ['updates', "cPanel → Select PHP Version (sau MultiPHP Manager) → alege PHP 8.2 sau 8.3 pentru domeniu.\nÎnainte, verifică pe o copie că tema și pluginurile merg pe versiunea nouă."],

        // ---------- securitate (plugin) ----------
        'core_modified' => ['security', "Fișierele nucleului WordPress diferă de cele oficiale: de obicei înseamnă cod malițios.\n1. Apasă „Reinstalează fișierele WordPress” (înlocuiește doar fișierele nucleului, nu atinge conținutul).\n2. Schimbă parolele tuturor administratorilor și parola bazei de date.\n3. Verifică pluginurile și tema (malware-ul se ascunde des și acolo) și fișierele PHP din uploads."],
        'core_checksums_unavailable' => ['security', 'Verificarea se reia automat la următoarea scanare.'],
        'php_in_uploads' => ['security', "În folderul de imagini nu trebuie să existe fișiere PHP.\n1. Apasă „Blochează PHP în uploads”, ca să nu mai poată fi rulate.\n2. Deschide fișierele din listă în File Manager și șterge-le dacă nu le recunoști (de obicei sunt backdoor-uri).\n3. Dacă erau malițioase: schimbă parolele și verifică nucleul (reinstalare)."],
        'debug_log' => ['security', 'Apasă „Șterge debug.log”. Ca să nu se mai creeze, în wp-config.php pune WP_DEBUG_LOG pe false (sau o cale în afara public_html).'],
        'debug_display' => ['security', "În wp-config.php, pe site-ul live:\ndefine('WP_DEBUG', false);\ndefine('WP_DEBUG_DISPLAY', false);"],
        'readme' => ['security', 'Apasă „Șterge readme.html” (WordPress îl recreează la actualizări; scanarea îl semnalează din nou).'],
        'file_edit' => ['security', "Apasă „Dezactivează editorul de fișiere”. Alternativ, în wp-config.php: define('DISALLOW_FILE_EDIT', true);"],
        'xmlrpc' => ['security', 'Apasă „Dezactivează XML-RPC”. Excepție: dacă firma folosește aplicația mobilă WordPress sau Jetpack, lasă-l activ.'],
        'admin_username' => ['security', "1. WordPress → Utilizatori → Adaugă nou: un administrator cu alt nume și parolă puternică.\n2. Autentifică-te cu el și șterge contul „admin”, atribuind conținutul noului cont."],
        'many_admins' => ['security', 'Treci în revistă lista: fiecare administrator e o ușă de intrare. Celor care doar scriu conținut dă-le rolul „Editor”.'],
        'inactive_plugins' => ['security', 'Pluginurile inactive tot pot fi exploatate. Șterge-le din WordPress → Module dacă nu mai sunt folosite.'],

        // ---------- SEO ----------
        'noindex' => ['seo', 'Apasă „Permite indexarea în Google” (sau WordPress → Setări → Citire → debifează „Descurajează motoarele de căutare”). Apoi trimite sitemap-ul în Google Search Console.'],
        'seo.noindex_home' => ['seo', "Prima pagină cere Google să nu o indexeze (meta robots „noindex” sau antetul X-Robots-Tag).\nÎn WordPress: Setări → Citire și setările pluginului SEO pentru prima pagină. Pe site-uri PHP: șterge eticheta <meta name=\"robots\" content=\"noindex\"> din șablon."],
        'seo.robots_block_all' => ['seo', "robots.txt conține „Disallow: /” pentru toți roboții: Google nu poate citi site-ul.\nÎnlocuiește conținutul cu:\nUser-agent: *\nDisallow:\nSitemap: https://DOMENIU/sitemap.xml"],
        'seo.robots_missing' => ['seo', "Creează robots.txt în rădăcina site-ului:\nUser-agent: *\nDisallow:\nSitemap: https://DOMENIU/sitemap.xml\n(În WordPress îl generează pluginul SEO.)"],
        'seo.sitemap_missing' => ['seo', 'Instalează / activează sitemap-ul (Rank Math sau Yoast în WordPress; WordPress are și /wp-sitemap.xml). Apoi trimite adresa în Google Search Console → Sitemaps.'],
        'seo.https_redirect' => ['seo', "Versiunea http:// nu redirecționează spre https://. În .htaccess (Apache):\nRewriteEngine On\nRewriteCond %{HTTPS} off\nRewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]\nSau în cPanel → Domains → Force HTTPS Redirect."],
        'seo.www_duplicate' => ['seo', 'Site-ul răspunde și cu www, și fără, fără redirect: Google vede două site-uri. Alege o variantă și redirecționează-o pe cealaltă cu 301 (cPanel → Redirects sau .htaccess).'],
        'seo.title_missing' => ['seo', 'Fiecare pagină are nevoie de un titlu unic (50–60 de caractere) cu serviciul și localitatea, ex. „Service auto Târgu Mureș – distribuție, ITP | Demo Auto”. În WordPress se setează din pluginul SEO.'],
        'seo.title_length' => ['seo', 'Titlurile prea lungi sunt tăiate în Google, cele prea scurte nu spun nimic. Ținta: 30–60 de caractere, cu cuvântul cheie la început.'],
        'seo.title_duplicate' => ['seo', 'Mai multe pagini au același titlu și se concurează între ele în Google. Scrie un titlu diferit pentru fiecare.'],
        'seo.description_missing' => ['seo', 'Scrie pentru fiecare pagină o descriere de 120–155 de caractere: ce oferă pagina și un îndemn („Sună acum”, „Cere ofertă”). Crește rata de click din Google.'],
        'seo.h1_missing' => ['seo', 'Fiecare pagină trebuie să aibă exact un titlu principal (H1) care spune despre ce e pagina. În constructorul de pagini setează titlul principal ca „H1”.'],
        'seo.h1_multiple' => ['seo', 'Păstrează un singur H1 pe pagină; restul titlurilor devin H2 / H3.'],
        'seo.canonical_missing' => ['seo', 'Adaugă eticheta canonical (pluginul SEO o pune automat în WordPress). Previne conținutul duplicat din parametri (?utm=, ?page=).'],
        'seo.images_alt' => ['seo', 'Imaginile fără text alternativ nu apar în Google Imagini și sunt inaccesibile cititoarelor de ecran. Completează „Text alternativ” în Media, descriind imaginea (ex. „schimb distribuție Ford Focus”).'],
        'seo.lang_missing' => ['seo', 'Adaugă limba în eticheta <html lang="ro">. În WordPress: Setări → General → Limba site-ului.'],
        'seo.viewport_missing' => ['seo', 'Lipsește <meta name="viewport" content="width=device-width, initial-scale=1">: pe telefon site-ul apare micșorat, iar Google penalizează paginile neadaptate pentru mobil.'],
        'seo.og_missing' => ['seo', 'Lipsesc etichetele Open Graph (titlu și imagine la distribuirea pe Facebook / WhatsApp). Pluginul SEO le adaugă: setează o imagine implicită de distribuire.'],
        'seo.schema_missing' => ['seo', 'Adaugă date structurate (JSON-LD) de tip LocalBusiness / Organization cu nume, adresă, telefon, program. Ajută la apariția în Google Maps și în rezultatele locale (Rank Math → Local SEO).'],
        'seo.broken_pages' => ['seo', 'Pagini din sitemap care dau eroare. Repară-le sau redirecționează-le (301) spre pagina cea mai apropiată și scoate-le din sitemap.'],

        // ---------- viteză ----------
        'perf.slow_response' => ['performance', 'Serverul răspunde greu. Pași: activează cache-ul de pagină (LiteSpeed Cache dacă hostingul e LiteSpeed, altfel WP Super Cache), PHP 8.2+, șterge pluginurile grele nefolosite, verifică planul de hosting.'],
        'perf.page_size' => ['performance', 'Prima pagină e foarte mare. Comprimă imaginile (WebP), încarcă imaginile „lazy”, elimină scripturile nefolosite (slidere, fonturi multiple).'],
        'perf.no_compression' => ['performance', "Activează compresia: cPanel → Optimize Website → „Compress all content”, sau în .htaccess:\nAddOutputFilterByType DEFLATE text/html text/css application/javascript"],

        // ---------- securitate (audit extern) ----------
        'sec.ssl_invalid' => ['security', 'Certificatul HTTPS nu e valid. cPanel → SSL/TLS Status → Run AutoSSL. Dacă domeniul e prin Cloudflare, setează modul SSL „Full”.'],
        'sec.ssl_expiring' => ['security', 'Certificatul expiră curând. Verifică în cPanel → SSL/TLS Status că AutoSSL e activ pentru domeniu (și pentru www) și rulează-l manual.'],
        'sec.exposed_files' => ['security', "Fișiere care nu ar trebui să fie publice se pot descărca din browser. Șterge-le sau mută-le în afara public_html IMEDIAT.\nDacă e expus .env sau un backup wp-config: schimbă parolele bazei de date și cheile din el."],
        'sec.dir_listing' => ['security', "Folderul își afișează conținutul în browser. În .htaccess din rădăcina site-ului adaugă:\nOptions -Indexes"],
        'sec.headers_missing' => ['security', "Lipsesc antete de securitate. În .htaccess:\n<IfModule mod_headers.c>\nHeader always set X-Content-Type-Options \"nosniff\"\nHeader always set X-Frame-Options \"SAMEORIGIN\"\nHeader always set Referrer-Policy \"strict-origin-when-cross-origin\"\n</IfModule>"],
        'sec.hsts_missing' => ['security', "După ce HTTPS merge peste tot, adaugă în .htaccess:\nHeader always set Strict-Transport-Security \"max-age=31536000\""],
        'sec.server_leak' => ['security', 'Serverul își afișează versiunea PHP (antetul X-Powered-By). cPanel → MultiPHP INI Editor → expose_php = Off.'],

        // ---------- legal (România) ----------
        'legal.privacy_policy' => ['legal', "GDPR (Regulamentul UE 2016/679, art. 13) cere informarea celor ale căror date le colectezi (formulare, newsletter, cookie-uri).\nCreează pagina „Politica de confidențialitate” (cine e operatorul, ce date, de ce, cât timp, drepturile persoanei, contact) și pune link în subsolul fiecărei pagini."],
        'legal.cookie_policy' => ['legal', 'Creează pagina „Politica de cookie-uri” cu lista cookie-urilor (ale site-ului și ale terților: Google Analytics, Facebook etc.), scopul și durata lor. Link în subsol și în bannerul de cookie-uri.'],
        'legal.cookie_consent' => ['legal', "Site-ul încarcă scripturi de urmărire (Google Analytics / Ads, Facebook Pixel etc.) fără un banner de consimțământ. Legea 506/2004 și GDPR cer acordul ÎNAINTE de aceste cookie-uri.\nÎn WordPress: Complianz sau CookieYes, cu blocarea scripturilor până la acord și Google Consent Mode v2 (obligatoriu și pentru Google Ads)."],
        'legal.cookie_banner' => ['legal', 'Nu am găsit un banner de cookie-uri. Dacă site-ul folosește doar cookie-uri strict necesare, e suficientă politica de cookie-uri; dacă adaugi Analytics / Ads / Pixel, ai nevoie de banner cu consimțământ.'],
        'legal.terms' => ['legal', 'Adaugă pagina „Termeni și condiții” (cine e firma, condițiile de utilizare / comandă, livrare, garanție, retur). Obligatorie pentru magazine online, recomandată pentru orice firmă.'],
        'legal.anpc' => ['legal', 'Comercianții care vând online către consumatori trebuie să afișeze link spre ANPC – Soluționarea alternativă a litigiilor (https://anpc.ro/ce-este-sal/), conform OG 38/2015 și Ordinului ANPC 449/2022 (cu pictograma oficială), de obicei în subsol.'],
        'legal.sol' => ['legal', 'Comercianții online trebuie să afișeze link spre platforma europeană SOL (https://ec.europa.eu/consumers/odr), conform Regulamentului UE 524/2013, cu pictograma ANPC corespunzătoare, în subsol.'],
        'legal.company_id' => ['legal', "Legea 365/2002 (art. 5) cere afișarea datelor firmei: denumire, sediu, nr. Registrul Comerțului, CUI, telefon / email.\nAdaugă-le în subsol (ex. „Demo Auto SRL · CUI RO12345678 · J26/123/2015 · Str. Exemplu 10, Târgu Mureș”) și în pagina de contact."],
        'legal.contact' => ['legal', 'Afișează un email și un telefon de contact vizibile (subsol și pagina Contact).'],
        'legal.form_consent' => ['legal', 'Formularele care colectează date personale trebuie să informeze (link spre politica de confidențialitate) și, pentru newsletter / marketing, să ceară acord separat printr-o căsuță nebifată implicit.'],
    ];

    public static function category(string $code): string
    {
        return self::GUIDE[$code][0] ?? self::GUIDE[self::prefix($code)][0] ?? 'security';
    }

    public static function howTo(string $code, ?string $domain = null): ?string
    {
        $text = self::GUIDE[$code][1] ?? self::GUIDE[self::prefix($code)][1] ?? null;

        return $text && $domain ? str_replace('DOMENIU', $domain, $text) : $text;
    }

    /** Scor 0–100 per categorie, din problemele deschise. @param iterable<array{category: string, severity: string}|object> $issues @return array<string, int> */
    public static function scores(iterable $issues): array
    {
        $weights = ['critical' => 25, 'warning' => 10, 'info' => 3];
        $scores = array_fill_keys(array_keys(self::CATEGORIES), 100);
        foreach ($issues as $issue) {
            $issue = (array) (is_object($issue) && method_exists($issue, 'toArray') ? $issue->toArray() : $issue);
            if (isset($scores[$issue['category']])) {
                $scores[$issue['category']] = max(0, $scores[$issue['category']] - ($weights[$issue['severity']] ?? 0));
            }
        }

        return $scores;
    }

    private static function prefix(string $code): string
    {
        return explode(':', $code, 2)[0];
    }
}
