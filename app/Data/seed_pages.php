<?php
declare(strict_types=1);

/**
 * Pagini inițiale. Variabilele {{firma}}, {{cui}}, {{reg_com}}, {{adresa}}, {{email}}, {{telefon}}, {{site}}
 * se completează automat din Setări → Firmă.
 * ATENȚIE: textele legale sunt un punct de plecare – verifică-le cu un consultant juridic.
 */
return [
    [
        'slug' => 'despre-noi',
        'title' => 'Despre noi',
        'subtitle' => '',
        'template' => 'about',
        'in_footer' => 0,
        'meta_title' => 'Despre VITIM – IT, securitate, marketing și AI | Târgu Mureș',
        'meta_description' => 'VITIM este o firmă din Târgu Mureș care oferă mentenanță IT, securitate cibernetică, recuperări de date, marketing online și soluții AI pentru firme din Mureș, Bistrița-Năsăud și Alba.',
        'body' => <<<'HTML'
<h2>Cine suntem</h2>
<p><strong>{{brand}}</strong> (Various IT and Marketing Solutions) este o firmă din Târgu Mureș care ajută afacerile să folosească tehnologia fără stres. Am pornit din IT – calculatoare, rețele, servere, suport – și ne-am extins firesc spre ce au nevoie azi firmele: securitate, vizibilitate online și automatizare cu inteligență artificială.</p>
<h2>Ce ne diferențiază</h2>
<ul>
<li><strong>Un singur partener pentru tot ce ține de tehnologie.</strong> Nu mai explici aceeași problemă la trei firme diferite.</li>
<li><strong>Vorbim pe înțeles.</strong> Îți explicăm opțiunile și costurile clar, fără jargon, și îți spunem sincer ce nu merită.</li>
<li><strong>Prevenim, nu doar reparăm.</strong> Monitorizare, backup verificat și actualizări, ca problemele să nu ajungă la tine.</li>
<li><strong>Suntem aproape.</strong> Intervenim la sediu în Mureș, Bistrița-Năsăud și Alba și suntem la un telefon distanță pentru restul țării.</li>
</ul>
<h2>LocalMureș</h2>
<p>Credem în economia locală. Prin proiectul <a href="/localmures">LocalMureș</a> promovăm afacerile din județul Mureș și le conectăm între ele.</p>
HTML,
    ],
    [
        'slug' => 'contact',
        'title' => 'Contact',
        'subtitle' => '',
        'template' => 'contact',
        'in_footer' => 0,
        'body' => '',
    ],
    [
        'slug' => 'localmures',
        'title' => 'LocalMureș – susținem afacerile locale',
        'subtitle' => 'Mureșul prosperă prin afacerile locale. Descoperă, susține, crește!',
        'template' => 'cta',
        'in_footer' => 0,
        'meta_title' => 'LocalMureș – promovarea afacerilor locale din județul Mureș',
        'meta_description' => 'LocalMureș este proiectul VITIM de promovare a afacerilor locale din județul Mureș: campanii Meta și Google, reclamă reciprocă între parteneri și networking.',
        'body' => <<<'HTML'
<h2>Ce este LocalMureș</h2>
<p><strong>LocalMureș</strong> este o platformă dedicată promovării afacerilor locale din județul Mureș, prin strategii moderne de marketing digital. Proiectul susține dezvoltarea antreprenorilor locali prin:</p>
<ul>
<li><strong>Promovare pe Meta și Google</strong> – creăm și gestionăm campanii eficiente pe Facebook, Instagram și Google, pentru a ajunge direct la clienții tăi.</li>
<li><strong>Reclamă vizuală reciprocă</strong> – afacerile listate beneficiază de promovare încrucișată pe platforma LocalMures.ro.</li>
<li><strong>Oportunități de networking</strong> – conectăm afacerile locale între ele și cu publicul larg, consolidând economia locală.</li>
</ul>
<p>Fii parte din LocalMureș și crește-ți vizibilitatea într-un mod eficient și accesibil.</p>
<p><a href="https://www.localmures.ro" target="_blank" rel="noopener">www.localmures.ro</a> – Sprijinim comunitatea, susținem afacerile locale!</p>
HTML,
    ],
    [
        'slug' => 'politica-de-confidentialitate',
        'title' => 'Politica de confidențialitate',
        'subtitle' => 'Cum colectăm, folosim și protejăm datele tale personale.',
        'template' => 'default',
        'in_footer' => 1,
        'body' => <<<'HTML'
<p><em>Ultima actualizare: {{data}}</em></p>
<h2>1. Cine suntem</h2>
<p>Operatorul datelor este <strong>{{firma}}</strong>, CUI {{cui}}, {{reg_com}}, cu sediul în {{adresa}}, email {{email}}, telefon {{telefon}} („noi”). Această politică explică modul în care prelucrăm datele personale ale vizitatorilor site-ului {{site}}, ale clienților și ale persoanelor care ne contactează, conform Regulamentului (UE) 2016/679 („GDPR”).</p>
<h2>2. Ce date prelucrăm</h2>
<ul>
<li><strong>Date de contact</strong> trimise prin formulare, email, telefon sau WhatsApp: nume, firmă, telefon, email, județ, mesajul și detaliile solicitării.</li>
<li><strong>Date de abonare la newsletter</strong>: email, nume (opțional), data și IP-ul consimțământului.</li>
<li><strong>Date de client</strong>: date necesare încheierii și executării contractelor, facturării și suportului tehnic.</li>
<li><strong>Date tehnice</strong>: adresa IP, tipul browserului, paginile vizitate, sursa vizitei (inclusiv parametri de campanie), cookies – conform secțiunii despre cookies.</li>
<li><strong>Date de interacțiune cu emailurile</strong>: deschiderea emailurilor și click-urile pe linkuri din campaniile noastre.</li>
</ul>
<h2>3. Scopuri și temeiuri legale</h2>
<ul>
<li><strong>Răspunsul la solicitări și oferte</strong> – demersuri precontractuale la cererea ta (art. 6 alin. 1 lit. b GDPR) și consimțământul tău.</li>
<li><strong>Executarea contractelor și suportul tehnic</strong> – art. 6 alin. 1 lit. b.</li>
<li><strong>Obligații legale</strong> (facturare, contabilitate, arhivare) – art. 6 alin. 1 lit. c.</li>
<li><strong>Newsletter și comunicări de marketing</strong> – consimțământul tău (art. 6 alin. 1 lit. a), pe care îl poți retrage oricând.</li>
<li><strong>Notificări de serviciu către clienți</strong> (ex. mentenanță planificată, alerte de securitate) – interesul nostru legitim și executarea contractului.</li>
<li><strong>Securitatea site-ului, prevenirea fraudei și a spamului</strong> – interes legitim (art. 6 alin. 1 lit. f).</li>
<li><strong>Statistici și măsurarea campaniilor</strong> – consimțământul exprimat prin bannerul de cookies.</li>
</ul>
<h2>4. Cât timp păstrăm datele</h2>
<ul>
<li>Solicitările care nu devin contracte: până la 24 de luni de la ultima interacțiune.</li>
<li>Datele de client și documentele financiare: pe durata contractului și ulterior conform termenelor legale de arhivare.</li>
<li>Datele de newsletter: până la dezabonare; după dezabonare păstrăm doar dovada consimțământului și a retragerii lui.</li>
</ul>
<h2>5. Cui transmitem datele</h2>
<p>Nu vindem datele personale. Le putem transmite, strict în măsura necesară, către: furnizori de găzduire și email, furnizori de servicii de analiză și publicitate (Google, Meta – doar cu consimțământul tău), contabilitate, consultanți juridici și autorități, atunci când legea o cere. Cu furnizorii încheiem acorduri de prelucrare a datelor. Unii furnizori pot transfera date în afara SEE, pe baza mecanismelor legale (decizii de adecvare, clauze contractuale standard).</p>
<h2>6. Drepturile tale</h2>
<p>Ai dreptul de acces, rectificare, ștergere, restricționare, portabilitate, opoziție și dreptul de a retrage oricând consimțământul, fără a afecta legalitatea prelucrării anterioare. Pentru exercitarea drepturilor scrie-ne la {{email}}. Ai dreptul să depui plângere la Autoritatea Națională de Supraveghere a Prelucrării Datelor cu Caracter Personal (<a href="https://www.dataprotection.ro" target="_blank" rel="noopener">www.dataprotection.ro</a>).</p>
<h2>7. Securitate</h2>
<p>Aplicăm măsuri tehnice și organizatorice adecvate: conexiune criptată (HTTPS), acces restricționat pe roluri, autentificare în doi pași pentru administratori, backup-uri și actualizări regulate.</p>
<h2>8. Modificări</h2>
<p>Putem actualiza această politică. Versiunea curentă este întotdeauna publicată pe această pagină.</p>
HTML,
    ],
    [
        'slug' => 'politica-cookies',
        'title' => 'Politica de cookies',
        'subtitle' => 'Ce cookies folosim și cum îți poți controla preferințele.',
        'template' => 'default',
        'in_footer' => 1,
        'body' => <<<'HTML'
<p><em>Ultima actualizare: {{data}}</em></p>
<h2>Ce sunt cookies</h2>
<p>Cookies sunt fișiere mici salvate de browser atunci când vizitezi un site. Le folosim pentru funcționarea site-ului și, doar cu acordul tău, pentru statistici și măsurarea reclamelor.</p>
<h2>Categorii de cookies</h2>
<table>
<thead><tr><th>Categorie</th><th>Scop</th><th>Exemple</th><th>Necesită acord</th></tr></thead>
<tbody>
<tr><td>Necesare</td><td>Funcționarea site-ului, securitate, reținerea preferințelor (temă, cookies)</td><td>preferință temă și consimțământ (stocare locală), sesiune pentru administratori</td><td>Nu</td></tr>
<tr><td>Analiză</td><td>Statistici anonime despre utilizarea site-ului</td><td>Google Analytics (_ga, _ga_*), Microsoft Clarity</td><td>Da</td></tr>
<tr><td>Marketing</td><td>Măsurarea și optimizarea campaniilor publicitare</td><td>Google Ads, Meta Pixel (_fbp)</td><td>Da</td></tr>
</tbody>
</table>
<h2>Cum îți controlezi preferințele</h2>
<p>La prima vizită îți cerem acordul prin bannerul de cookies. Îți poți schimba oricând alegerea din linkul <strong>„Setări cookies”</strong> din subsolul site-ului. De asemenea, poți șterge sau bloca cookies din setările browserului.</p>
<p>Folosim modul de consimțământ Google (Consent Mode v2): dacă refuzi, etichetele Google nu salvează cookies de analiză sau publicitate.</p>
<h2>Contact</h2>
<p>Pentru întrebări: {{email}}. Detalii despre prelucrarea datelor găsești în <a href="/politica-de-confidentialitate">Politica de confidențialitate</a>.</p>
HTML,
    ],
    [
        'slug' => 'termeni-si-conditii',
        'title' => 'Termeni și condiții',
        'subtitle' => 'Condițiile de utilizare a site-ului.',
        'template' => 'default',
        'in_footer' => 1,
        'body' => <<<'HTML'
<p><em>Ultima actualizare: {{data}}</em></p>
<h2>1. Informații despre firmă</h2>
<p>Site-ul {{site}} este administrat de <strong>{{firma}}</strong>, CUI {{cui}}, {{reg_com}}, sediu: {{adresa}}, email {{email}}, telefon {{telefon}}.</p>
<h2>2. Utilizarea site-ului</h2>
<p>Conținutul site-ului are caracter informativ. Ofertele de preț sunt personalizate și devin angajante doar prin ofertă scrisă sau contract semnat de ambele părți.</p>
<h2>3. Proprietate intelectuală</h2>
<p>Textele, grafica, logo-ul și celelalte elemente ale site-ului aparțin {{firma}} sau sunt folosite cu acordul titularilor. Reproducerea fără acord scris este interzisă.</p>
<h2>4. Servicii</h2>
<p>Condițiile concrete ale serviciilor (termene, prețuri, niveluri de suport, răspundere) sunt stabilite prin oferta și contractul încheiat cu fiecare client.</p>
<h2>5. Limitarea răspunderii</h2>
<p>Depunem toate eforturile ca informațiile de pe site să fie corecte și actuale, dar nu garantăm lipsa oricăror erori. Nu răspundem pentru conținutul site-urilor externe către care există linkuri.</p>
<h2>6. Soluționarea litigiilor</h2>
<p>Eventualele neînțelegeri se rezolvă pe cale amiabilă. Consumatorii se pot adresa Autorității Naționale pentru Protecția Consumatorilor (<a href="https://anpc.ro" target="_blank" rel="noopener">anpc.ro</a>), procedurii de soluționare alternativă a litigiilor (<a href="https://anpc.ro/ce-este-sal/" target="_blank" rel="noopener">SAL</a>) sau platformei europene SOL (<a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noopener">ec.europa.eu/consumers/odr</a>).</p>
<h2>7. Legea aplicabilă</h2>
<p>Acești termeni sunt guvernați de legea română.</p>
HTML,
    ],
];
