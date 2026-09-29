<?php
declare(strict_types=1);

use App\Core\DB;
use App\Core\Uploader;

/**
 * v1.1: conversațiile asistentului AI + fotografii pentru servicii, blog și prima pagină.
 * Imaginile se atașează doar unde nu există deja una (nu suprascriem pozele tale).
 */
return function (): void {
    DB::createTable('ai_chats', [
        'id' => 'id',
        'token' => 'short',
        'messages' => 'json',
        'contact_id' => 'int?',
        'deal_id' => 'int?',
        'page' => 'string?',
        'ip' => 'short?',
        'turns' => 'int=0',
        'input_tokens' => 'int=0',
        'output_tokens' => 'int=0',
        'status' => 'short=open',
        'created_at' => 'datetime?',
        'updated_at' => 'datetime?',
    ], [['created_at'], ['contact_id']], [['token']]);

    $dir = APP_PATH . '/Data/images';
    $import = function (string $name, string $alt) use ($dir): ?string {
        $src = $dir . '/' . $name . '.webp';
        if (!is_file($src)) {
            return null;
        }
        $existing = DB::val('SELECT path FROM media WHERE original_name = ?', [$name . '.webp']);
        if ($existing) {
            return (string)$existing;
        }
        // copie temporară: Uploader lucrează pe fișiere temporare
        $tmp = STORAGE_PATH . '/tmp/' . $name . '.webp';
        copy($src, $tmp);
        [$m] = Uploader::image(['name' => $name . '.webp', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)], $alt);
        @unlink($tmp);
        return $m['path'] ?? null;
    };

    $services = [
        'mentenanta-it' => ['mentenanta-it-firme', 'Rack de servere și cabluri de rețea într-o cameră tehnică'],
        'suport-it-remote' => ['suport-it-remote', 'Persoană lucrând la laptop și telefon, asistență IT de la distanță'],
        'reparatii-it' => ['reparatii-calculatoare-servere', 'Placă de bază cu componente electronice, reparații calculatoare'],
        'solutii-google-workspace' => ['google-workspace-colaborare', 'Echipă lucrând împreună pe laptopuri la aceeași masă'],
        'securitate-cibernetica' => ['securitate-cibernetica', 'Circuit electronic iluminat, protecția sistemelor informatice'],
        'recuperare-date' => ['recuperare-date-hard-disk', 'Hard disk deschis, cu platanul și capul de citire vizibile'],
        'seo' => ['seo-analiza-trafic', 'Laptop cu grafice de trafic și analiză SEO'],
        'marketing-tehnic' => ['marketing-tehnic-dashboard', 'Dashboard cu statistici de marketing și conversii'],
        'google-ads' => ['campanii-google-ads', 'Grafic de performanță al unei campanii de publicitate online'],
        'meta-ads' => ['campanii-meta-ads-social', 'Persoană folosind telefonul și laptopul pentru social media'],
        'site-uri-web' => ['creare-site-web-design', 'Design de site web afișat pe un monitor, într-un birou'],
        'agenti-ai-software-personalizat' => ['agenti-ai-software', 'Rețea de conexiuni digitale, agenți de inteligență artificială'],
        'integrare-agenti-ai' => ['integrare-agenti-ai', 'Interfață digitală cu date și sisteme conectate'],
        'automatizari' => ['automatizari-procese', 'Laptop cu rapoarte generate automat'],
        'ai-pentru-firme' => ['instruire-ai-firme', 'Atelier de lucru cu echipa, planificare pe tablă cu notițe'],
    ];
    foreach ($services as $slug => [$file, $alt]) {
        $row = DB::row('SELECT id, image FROM services WHERE slug = ?', [$slug]);
        if ($row && empty($row['image']) && ($p = $import($file, $alt))) {
            DB::update('services', ['image' => $p], 'id = :id', ['id' => $row['id']]);
        }
    }

    $posts = [
        'ce-faci-cand-hard-diskul-a-cedat' => ['recuperare-date-hard-disk', 'Hard disk deschis, cu platanul și capul de citire vizibile'],
        'agenti-ai-pentru-imm-uri' => ['blog-agenti-ai', 'Literele AI într-o ilustrație tridimensională'],
        'checklist-securitate-cibernetica-firme-mici' => ['blog-securitate-retea', 'Cabluri de rețea conectate într-un switch'],
        'seo-mures-agentie-marketing-promovare' => ['blog-seo-planificare', 'Planificare de marketing pe un birou, cu schițe și telefon'],
    ];
    foreach ($posts as $slug => [$file, $alt]) {
        $row = DB::row('SELECT id, cover FROM posts WHERE slug = ?', [$slug]);
        if ($row && empty($row['cover']) && ($p = $import($file, $alt))) {
            DB::update('posts', ['cover' => $p], 'id = :id', ['id' => $row['id']]);
        }
    }

    foreach ([['hero-echipa-it-server', 'Specialistă IT verificând serverele într-un centru de date', 'home_hero_image'], ['sala-servere', 'Culoar într-o sală de servere', 'about_image']] as [$file, $alt, $key]) {
        if (\App\Core\Settings::get($key, '') === '' && ($p = $import($file, $alt))) {
            \App\Core\Settings::set($key, $p);
        }
    }

    $pp = DB::row("SELECT id, body FROM pages WHERE slug = 'politica-de-confidentialitate'");
    if ($pp && !str_contains((string)$pp['body'], 'Asistentul virtual')) {
        $section = '<h2>Asistentul virtual de pe site</h2><p>Pe site poți discuta cu un asistent virtual bazat pe inteligență artificială. Mesajele tale sunt transmise, în mod securizat, unui furnizor de servicii de inteligență artificială care le procesează ca să genereze răspunsul, în baza unui acord de prelucrare a datelor, fără a le folosi pentru antrenarea modelelor. Conversația este păstrată la noi cel mult 12 luni, ca să îți putem răspunde și pentru a îmbunătăți serviciul. Dacă îi dai asistentului datele tale de contact și accepți să fii contactat, cererea ta este salvată ca solicitare, la fel ca prin formularul de contact. Nu introduce în chat parole, date bancare sau alte informații sensibile.</p>';
        $body = str_contains((string)$pp['body'], '<h2>4.') ? str_replace('<h2>4.', $section . '<h2>4.', (string)$pp['body']) : $pp['body'] . $section;
        DB::update('pages', ['body' => $body, 'updated_at' => DB::now()], 'id = :id', ['id' => $pp['id']]);
    }

    DB::q("UPDATE services SET tagline = ? WHERE slug = 'ai-pentru-firme' AND tagline = ?", ['ChatGPT, Gemini, Copilot – folosite corect', 'ChatGPT, Claude, Gemini, Copilot – folosite corect']);
    $ex = (string)DB::val("SELECT excerpt FROM services WHERE slug = 'ai-pentru-firme'");
    if (str_contains($ex, '(ChatGPT, Claude, Gemini, Microsoft Copilot)')) {
        DB::q("UPDATE services SET excerpt = ? WHERE slug = 'ai-pentru-firme'", [str_replace('(ChatGPT, Claude, Gemini, Microsoft Copilot)', '(ChatGPT, Gemini, Microsoft Copilot și altele)', $ex)]);
    }

    // „Claude” dispare din banda de tehnologii (rămâne în textele serviciilor AI, ca opțiune pentru clienți)
    $trust = \App\Core\Settings::json('home_trust');
    if (in_array('Claude', $trust, true)) {
        \App\Core\Settings::set('home_trust', array_values(array_diff($trust, ['Claude'])));
    }
};
