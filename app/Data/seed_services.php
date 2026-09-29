<?php
declare(strict_types=1);

/**
 * Conținutul inițial al serviciilor. După instalare se editează din Panou → Conținut → Servicii.
 */
return [
    // =====================================================================
    // IT & SUPORT
    // =====================================================================
    [
        'slug' => 'mentenanta-it',
        'category' => 'it',
        'title' => 'Mentenanță IT pentru firme',
        'h1' => 'Mentenanță IT pentru firme – abonament lunar, fără griji',
        'tagline' => 'Abonament lunar: monitorizare, update-uri, suport',
        'icon' => 'server',
        'onsite' => 1,
        'featured' => 1,
        'excerpt' => 'Abonament lunar de mentenanță IT: monitorizare, actualizări, backup verificat, suport remote nelimitat și intervenții la sediu în Mureș, Bistrița-Năsăud și Alba.',
        'meta_title' => 'Mentenanță IT firme – abonament lunar | Mureș, Bistrița, Alba',
        'meta_description' => 'Mentenanță IT pentru firme cu abonament lunar: monitorizare 24/7, update-uri, backup verificat, suport remote și intervenții la sediu în Mureș, Bistrița-Năsăud și Alba.',
        'keywords' => 'mentenanta it, mentenanta it firme, abonament it, outsourcing it, administrare retea, firma it targu mures',
        'features' => [
            ['icon' => 'eye', 'title' => 'Monitorizare proactivă', 'text' => 'Urmărim starea calculatoarelor, serverelor și a rețelei și intervenim înainte ca o problemă să te oprească din lucru.'],
            ['icon' => 'refresh', 'title' => 'Actualizări gestionate', 'text' => 'Windows, macOS, aplicații, firmware de router și imprimante – aplicate controlat, fără surprize în mijlocul zilei.'],
            ['icon' => 'database', 'title' => 'Backup verificat', 'text' => 'Backup automat, cu copii în afara sediului și teste periodice de restaurare. Nu presupunem că merge – verificăm.'],
            ['icon' => 'headset', 'title' => 'Suport remote inclus', 'text' => 'Echipa ta ne sună sau ne scrie și rezolvăm de la distanță, de obicei în câteva minute.'],
            ['icon' => 'map', 'title' => 'Intervenții la sediu', 'text' => 'Ore de intervenție on-site incluse în abonament, cu prioritate față de clienții fără contract.'],
            ['icon' => 'file', 'title' => 'Inventar și rapoarte', 'text' => 'Evidența echipamentelor, licențelor și garanțiilor, plus un raport lunar clar cu ce am făcut.'],
        ],
        'faq' => [
            ['q' => 'Cât costă un abonament de mentenanță IT?', 'a' => 'Prețul depinde de numărul de stații de lucru, servere și de câte ore de intervenție la sediu vrei incluse. După un audit gratuit primești o ofertă fixă pe lună, fără costuri ascunse.'],
            ['q' => 'Ce se întâmplă dacă am o urgență?', 'a' => 'Clienții cu abonament au prioritate. Preluăm urgența remote imediat, iar dacă e nevoie de deplasare, venim cu prioritate în Mureș, Bistrița-Năsăud sau Alba.'],
            ['q' => 'Pot păstra IT-istul intern?', 'a' => 'Da. Putem lucra ca suport de nivel 2 pentru omul tău de IT: preluăm serverele, securitatea și proiectele mari, iar el se ocupă de problemele de zi cu zi.'],
            ['q' => 'Există perioadă minimă de contract?', 'a' => 'Recomandăm minimum 3 luni pentru ca monitorizarea și optimizările să își arate efectul, dar discutăm flexibil în funcție de nevoile firmei.'],
        ],
        'body' => <<<'HTML'
<h2>De ce abonament de mentenanță IT și nu „chemăm pe cineva când se strică”?</h2>
<p>Pentru o firmă, fiecare oră în care calculatoarele, serverul sau internetul nu funcționează înseamnă bani pierduți: angajați care nu pot lucra, facturi care nu pleacă, clienți care așteaptă. Modelul „reparăm când se strică” pare ieftin până în ziua în care se strică ceva important.</p>
<p>Cu un <strong>abonament de mentenanță IT</strong> plătești un cost fix lunar, iar noi avem grijă ca problemele să nu apară. Monitorizăm, actualizăm, facem backup și intervenim din timp. Tu știi exact cât te costă IT-ul în fiecare lună.</p>

<h2>Ce include mentenanța IT VITIM</h2>
<ul>
<li><strong>Stații de lucru</strong> – Windows și macOS: actualizări, antivirus/EDR, optimizare, conturi de utilizator, imprimante.</li>
<li><strong>Servere și NAS</strong> – Windows Server, Linux, Synology/QNAP: monitorizare, actualizări, spațiu de stocare, drepturi de acces.</li>
<li><strong>Rețea și internet</strong> – routere, firewall, Wi-Fi, switch-uri, VPN pentru lucru de acasă.</li>
<li><strong>Email și conturi</strong> – Microsoft 365 sau Google Workspace: utilizatori noi, parole, licențe, securitate.</li>
<li><strong>Backup</strong> – copii automate, criptate, cu păstrare în afara sediului și test de restaurare.</li>
<li><strong>Helpdesk</strong> – telefon, email, WhatsApp și conectare remote securizată.</li>
</ul>

<h2>Remote când se poate, la sediu când e nevoie</h2>
<p>Majoritatea solicitărilor se rezolvă remote, în câteva minute, fără deplasare. Pentru restul – un echipament nou, cablare, un server care trebuie înlocuit – venim la sediul tău în <strong>județele Mureș, Bistrița-Năsăud și Alba</strong>: Târgu Mureș, Reghin, Sighișoara, Bistrița, Alba Iulia, Aiud, Blaj, Sebeș și împrejurimi.</p>

<h2>Pentru cine este potrivit</h2>
<ul>
<li>Firme cu 3–100 de calculatoare care nu vor (sau nu pot) angaja un departament IT.</li>
<li>Cabinete medicale, birouri de contabilitate, notariate și cabinete de avocatură, unde datele sunt critice.</li>
<li>Magazine, service-uri, hoteluri și pensiuni care depind de casa de marcat, POS și internet.</li>
<li>Firme cu IT intern care au nevoie de un partener pentru proiecte mari și securitate.</li>
</ul>

<div class="callout"><strong>Primul pas este gratuit:</strong> facem un audit rapid al infrastructurii tale, îți spunem ce riscuri vedem și îți trimitem o ofertă fixă lunară.</div>

<h2>Cum începem colaborarea</h2>
<ol>
<li><strong>Audit și inventar</strong> – vedem ce echipamente, licențe și riscuri ai.</li>
<li><strong>Remedieri inițiale</strong> – rezolvăm problemele urgente găsite (backup lipsă, parole slabe, echipamente neactualizate).</li>
<li><strong>Monitorizare și mentenanță continuă</strong> – intrăm în ritmul lunar, cu raport clar la final de lună.</li>
</ol>
HTML,
    ],
    [
        'slug' => 'suport-it-remote',
        'category' => 'it',
        'title' => 'Suport IT remote',
        'h1' => 'Suport IT remote – problemele rezolvate în minute, de oriunde',
        'tagline' => 'Helpdesk rapid, în toată România',
        'icon' => 'headset',
        'onsite' => 0,
        'featured' => 0,
        'excerpt' => 'Helpdesk IT prin telefon, email și conectare remote securizată. Rezolvăm rapid problemele de calculator, email, imprimante și aplicații – oriunde în România.',
        'meta_title' => 'Suport IT remote pentru firme – helpdesk rapid | VITIM',
        'meta_description' => 'Suport IT remote pentru firme și profesioniști: probleme de calculator, email, Office, imprimante și aplicații rezolvate rapid prin conectare securizată. Toată România.',
        'keywords' => 'suport it remote, helpdesk it, asistenta it la distanta, suport tehnic calculator, it support romania',
        'features' => [
            ['icon' => 'zap', 'title' => 'Răspuns rapid', 'text' => 'Preluăm solicitarea imediat în programul de lucru; clienții cu abonament au prioritate.'],
            ['icon' => 'lock', 'title' => 'Conectare securizată', 'text' => 'Conexiune criptată, doar cu acordul tău, și jurnal complet al fiecărei sesiuni.'],
            ['icon' => 'monitor', 'title' => 'Windows, macOS, Linux', 'text' => 'Sisteme de operare, drivere, lentoare, erori, actualizări blocate.'],
            ['icon' => 'mail', 'title' => 'Email și Office', 'text' => 'Outlook, Microsoft 365, Gmail/Google Workspace, Teams, OneDrive, Drive.'],
            ['icon' => 'printer', 'title' => 'Imprimante și periferice', 'text' => 'Instalare, scanare pe email, drivere, partajare în rețea.'],
            ['icon' => 'users', 'title' => 'Conturi și acces', 'text' => 'Utilizatori noi, resetare parole, drepturi de acces, autentificare în doi pași.'],
        ],
        'faq' => [
            ['q' => 'Cum funcționează o sesiune de suport remote?', 'a' => 'Ne suni sau ne scrii, îți trimitem un link de conectare, iar tu aprobi accesul. Vezi tot ce facem pe ecran și poți închide sesiunea oricând.'],
            ['q' => 'Este sigur să las pe cineva să se conecteze la calculatorul meu?', 'a' => 'Da, folosim soluții profesionale cu criptare, conexiune doar cu aprobarea ta și jurnal al sesiunilor. Nu păstrăm acces permanent fără acordul tău scris.'],
            ['q' => 'Oferiți suport remote și persoanelor fizice?', 'a' => 'Ne concentrăm pe firme și profesioniști (PFA, cabinete), dar putem ajuta și persoane fizice pentru probleme punctuale.'],
            ['q' => 'Cum se tarifează?', 'a' => 'Fie inclus în abonamentul de mentenanță, fie per intervenție, cu tarif orar și minim de facturare anunțat dinainte.'],
        ],
        'body' => <<<'HTML'
<h2>Suport IT fără deplasări și fără așteptare</h2>
<p>Cele mai multe probleme IT de zi cu zi – un email care nu pleacă, o imprimantă care nu mai scanează, un program care nu pornește, un calculator lent – se rezolvă <strong>remote, în câteva minute</strong>. Fără să aștepți un tehnician, fără costuri de deplasare.</p>

<h2>Ce rezolvăm remote</h2>
<ul>
<li>Probleme de <strong>Windows și macOS</strong>: erori, lentoare, actualizări blocate, drivere.</li>
<li><strong>Email</strong>: configurare Outlook, căsuțe pline, sincronizare pe telefon, reguli și semnături.</li>
<li><strong>Microsoft 365 și Google Workspace</strong>: licențe, utilizatori, partajare fișiere, Teams și Meet.</li>
<li><strong>Imprimante și scanere</strong>: instalare, scan-to-email, partajare în rețea.</li>
<li><strong>Aplicații de business</strong>: programe de contabilitate, facturare, e-Factura/SPV, semnătură electronică.</li>
<li><strong>Securitate</strong>: verificare de viruși, emailuri suspecte, autentificare în doi pași.</li>
</ul>

<h2>Remote în toată țara</h2>
<p>Suportul remote nu depinde de distanță: lucrăm cu firme din toată România. Pentru clienții din <strong>Mureș, Bistrița-Năsăud și Alba</strong> putem completa oricând cu o intervenție la sediu.</p>

<div class="callout">Ai o problemă acum? Sună-ne și începem imediat, sau scrie-ne pe WhatsApp o captură de ecran cu eroarea.</div>
HTML,
    ],
    [
        'slug' => 'reparatii-it',
        'category' => 'it',
        'title' => 'Reparații IT: PC, laptop, servere',
        'h1' => 'Reparații calculatoare, laptopuri și servere pentru firme',
        'tagline' => 'Diagnoză, reparații și upgrade-uri',
        'icon' => 'wrench',
        'onsite' => 1,
        'featured' => 0,
        'excerpt' => 'Diagnoză și reparații pentru calculatoare, laptopuri, servere, imprimante și echipamente de rețea. Upgrade SSD și RAM, reinstalări, înlocuiri de componente – la sediu sau în atelier.',
        'meta_title' => 'Reparații calculatoare și laptopuri pentru firme | Mureș, Alba, Bistrița',
        'meta_description' => 'Service IT: reparații PC, laptopuri, servere și imprimante, upgrade SSD/RAM, reinstalare Windows, curățare. Intervenții la sediu în Mureș, Bistrița-Năsăud și Alba.',
        'keywords' => 'reparatii calculatoare, reparatii laptop, service it, service calculatoare targu mures, upgrade ssd, reparatii servere',
        'features' => [
            ['icon' => 'search', 'title' => 'Diagnoză clară', 'text' => 'Îți spunem ce s-a stricat, cât costă reparația și dacă merită – înainte să începem.'],
            ['icon' => 'hard-drive', 'title' => 'Upgrade SSD și RAM', 'text' => 'Un calculator de 4–5 ani poate deveni din nou rapid cu un upgrade inteligent.'],
            ['icon' => 'laptop', 'title' => 'Laptopuri', 'text' => 'Ecrane, tastaturi, balamale, baterii, încărcare, supraîncălzire.'],
            ['icon' => 'server', 'title' => 'Servere și NAS', 'text' => 'Discuri RAID, surse, plăci, migrare pe hardware nou fără pierderi de date.'],
            ['icon' => 'printer', 'title' => 'Imprimante și rețea', 'text' => 'Imprimante de birou, routere, switch-uri, access point-uri Wi-Fi.'],
            ['icon' => 'shield', 'title' => 'Datele rămân în siguranță', 'text' => 'Facem copie de siguranță înainte de orice intervenție riscantă.'],
        ],
        'faq' => [
            ['q' => 'Veniți la sediu sau trebuie să aduc echipamentul?', 'a' => 'Ambele variante. Multe reparații le facem direct la sediul tău în Mureș, Bistrița-Năsăud și Alba; pentru reparații complexe preluăm echipamentul și îl aducem înapoi.'],
            ['q' => 'Cât durează o reparație?', 'a' => 'Diagnoza durează de obicei câteva ore. Reparațiile uzuale (SSD, RAM, reinstalare) se fac în 1–2 zile; piesele comandate pot adăuga câteva zile.'],
            ['q' => 'Merită să repar sau să cumpăr un calculator nou?', 'a' => 'Îți spunem sincer. Dacă reparația costă peste 40–50% din valoarea unui echipament nou echivalent, îți recomandăm înlocuirea și te ajutăm să alegi.'],
            ['q' => 'Ce se întâmplă cu datele mele?', 'a' => 'Tratăm datele ca fiind confidențiale. Înainte de intervențiile riscante facem copie de siguranță, iar la cerere semnăm acord de confidențialitate.'],
        ],
        'body' => <<<'HTML'
<h2>Service IT pentru firme, cu diagnoză onestă</h2>
<p>Un calculator care nu mai pornește sau un server care dă erori nu trebuie să-ți blocheze activitatea. Facem <strong>diagnoză rapidă</strong>, îți spunem exact ce e de făcut și cât costă, iar tu decizi. Fără „am schimbat tot, ca să fim siguri”.</p>

<h2>Ce reparăm</h2>
<ul>
<li><strong>Calculatoare desktop și all-in-one</strong>: nu pornesc, se restartează, se supraîncălzesc, sunt lente.</li>
<li><strong>Laptopuri</strong>: ecran, tastatură, baterie, mufă de încărcare, balamale, lichid vărsat.</li>
<li><strong>Servere și NAS</strong>: discuri defecte, RAID degradat, surse, migrare pe hardware nou.</li>
<li><strong>Imprimante și multifuncționale</strong> de birou.</li>
<li><strong>Echipamente de rețea</strong>: routere, switch-uri, Wi-Fi, cablare structurată.</li>
</ul>

<h2>Upgrade-uri care chiar se simt</h2>
<p>Cel mai eficient upgrade pentru un calculator mai vechi este înlocuirea hard-diskului clasic cu un <strong>SSD</strong>, plus memorie RAM suplimentară. Diferența de viteză e de multe ori spectaculoasă, la o fracțiune din prețul unui echipament nou.</p>

<h2>Unde intervenim</h2>
<p>La sediul tău în <strong>județele Mureș, Bistrița-Năsăud și Alba</strong>, sau în atelier pentru reparațiile care necesită mai mult timp. Pentru firmele cu abonament de mentenanță, reparațiile au prioritate.</p>

<div class="callout">Calculatorul a căzut sau ai vărsat lichid pe el? <strong>Oprește-l imediat</strong> și nu încerca să-l pornești din nou – crești șansele de reparație și de salvare a datelor.</div>
HTML,
    ],
    [
        'slug' => 'solutii-google-workspace',
        'category' => 'it',
        'title' => 'Google Workspace & soluții Google',
        'h1' => 'Google Workspace și soluții Google pentru firme',
        'tagline' => 'Email pe domeniu, Drive, Meet, Business Profile',
        'icon' => 'google',
        'onsite' => 0,
        'featured' => 0,
        'excerpt' => 'Implementăm și administrăm Google Workspace (Gmail pe domeniu, Drive, Meet, Calendar), migrăm emailurile existente și configurăm Google Business Profile, Analytics și Search Console.',
        'meta_title' => 'Google Workspace pentru firme – implementare, migrare, administrare',
        'meta_description' => 'Email profesional pe domeniul firmei cu Google Workspace: implementare, migrare fără pierderi, securitate, Drive partajat, plus Google Business Profile, Analytics și Search Console.',
        'keywords' => 'google workspace, gmail pe domeniu, email firma, migrare email google, google business profile, google drive firma',
        'features' => [
            ['icon' => 'mail', 'title' => 'Email pe domeniul tău', 'text' => 'nume@firmata.ro în Gmail, cu configurare corectă SPF, DKIM și DMARC – emailurile nu mai ajung în spam.'],
            ['icon' => 'refresh', 'title' => 'Migrare fără pierderi', 'text' => 'Mutăm emailurile, contactele și calendarele din vechiul server fără întreruperi.'],
            ['icon' => 'cloud', 'title' => 'Drive-uri partajate', 'text' => 'Structură de foldere pe departamente, drepturi corecte, fără fișiere pierdute pe laptopuri.'],
            ['icon' => 'lock', 'title' => 'Securitate', 'text' => 'Autentificare în doi pași, politici de parole, ștergere de la distanță pe telefoane pierdute.'],
            ['icon' => 'map', 'title' => 'Google Business Profile', 'text' => 'Apari corect pe Google Maps: categorie, program, poze, recenzii, postări.'],
            ['icon' => 'chart', 'title' => 'Analytics & Search Console', 'text' => 'Măsurare corectă a vizitatorilor și a conversiilor, cu acces doar pentru cine trebuie.'],
        ],
        'faq' => [
            ['q' => 'Google Workspace sau Microsoft 365?', 'a' => 'Depinde de cum lucrează echipa ta. Workspace e excelent pentru colaborare în browser; Microsoft 365 e alegerea naturală dacă folosiți intens Excel și Word desktop. Te ajutăm să alegi și lucrăm cu ambele.'],
            ['q' => 'Pierd emailurile vechi la migrare?', 'a' => 'Nu. Migrăm istoricul de emailuri, contactele și calendarele. Trecerea se face planificat, de obicei peste noapte sau în weekend.'],
            ['q' => 'De ce ajung emailurile firmei în spam?', 'a' => 'De cele mai multe ori lipsesc sau sunt greșite înregistrările SPF, DKIM și DMARC ale domeniului. Le configurăm corect și verificăm livrabilitatea.'],
            ['q' => 'Ne ajutați și cu Google Business Profile?', 'a' => 'Da: creare sau revendicare profil, optimizare completă, strategie de recenzii și postări regulate pentru vizibilitate locală în Google Maps.'],
        ],
        'body' => <<<'HTML'
<h2>Lucrul „în Google”, făcut ca la carte</h2>
<p>Multe firme folosesc deja produse Google – Gmail, Drive, Maps, Analytics – dar de multe ori fiecare angajat cu contul lui personal, fără reguli și fără control. Rezultatul: fișiere pierdute când pleacă un angajat, emailuri care ajung în spam și date răspândite peste tot.</p>
<p>Implementăm <strong>Google Workspace</strong> corect, de la zero sau prin migrare, și îl administrăm pentru tine.</p>

<h2>Google Workspace: ce configurăm</h2>
<ul>
<li><strong>Gmail pe domeniul firmei</strong>, cu aliasuri, grupuri (office@, contact@, facturi@) și semnături unitare.</li>
<li><strong>Autentificarea domeniului</strong> – SPF, DKIM, DMARC – pentru livrabilitate și protecție împotriva falsificării.</li>
<li><strong>Drive-uri partajate</strong> pe departamente, cu drepturi de acces clare.</li>
<li><strong>Calendar și Meet</strong> pentru programări și întâlniri online.</li>
<li><strong>Administrarea dispozitivelor</strong>: telefoane și laptopuri securizate, ștergere de la distanță.</li>
<li><strong>Gemini în Workspace</strong>: activare și bune practici pentru folosirea AI în documente și emailuri.</li>
</ul>

<h2>Vizibilitate în Google: Business Profile, Analytics, Search Console</h2>
<p>Pentru afacerile locale, <strong>Google Business Profile</strong> (fostul Google My Business) aduce apeluri și vizite direct din Google Maps. Îl optimizăm complet și îl legăm de site. Configurăm <strong>Google Analytics 4</strong>, <strong>Search Console</strong> și <strong>Tag Manager</strong>, ca să știi exact de unde vin clienții.</p>
HTML,
    ],

    // =====================================================================
    // SECURITATE & DATE
    // =====================================================================
    [
        'slug' => 'securitate-cibernetica',
        'category' => 'securitate',
        'title' => 'Securitate cibernetică',
        'h1' => 'Securitate cibernetică pentru IMM-uri – protecție reală, nu doar antivirus',
        'tagline' => 'Audit, protecție, backup, instruire',
        'icon' => 'shield',
        'onsite' => 1,
        'featured' => 1,
        'excerpt' => 'Audit de securitate, protecție pentru calculatoare și email, firewall, backup imun la ransomware, autentificare în doi pași și instruirea angajaților. Pregătire pentru cerințele NIS2.',
        'meta_title' => 'Securitate cibernetică pentru firme – audit, ransomware, NIS2',
        'meta_description' => 'Protejează-ți firma de ransomware, phishing și furt de date: audit de securitate, EDR, firewall, backup imutabil, 2FA și instruire anti-phishing. Suport pentru conformare NIS2.',
        'keywords' => 'securitate cibernetica, securitate it firme, protectie ransomware, audit securitate it, nis2 romania, phishing',
        'features' => [
            ['icon' => 'search', 'title' => 'Audit de securitate', 'text' => 'Identificăm punctele slabe: parole, acces, echipamente neactualizate, backup, expunere pe internet.'],
            ['icon' => 'shield', 'title' => 'Protecție endpoint (EDR)', 'text' => 'Mai mult decât un antivirus: detecție comportamentală și izolare automată a amenințărilor.'],
            ['icon' => 'mail', 'title' => 'Email securizat', 'text' => 'Filtrare phishing, SPF/DKIM/DMARC, protecție împotriva fraudelor cu facturi false.'],
            ['icon' => 'database', 'title' => 'Backup anti-ransomware', 'text' => 'Copii imutabile, izolate, care nu pot fi criptate de atacatori.'],
            ['icon' => 'key', 'title' => 'Acces și parole', 'text' => 'Autentificare în doi pași, manager de parole, drepturi minime necesare.'],
            ['icon' => 'users', 'title' => 'Instruirea echipei', 'text' => 'Sesiuni practice anti-phishing și simulări, pentru că oamenii sunt prima linie de apărare.'],
        ],
        'faq' => [
            ['q' => 'Suntem o firmă mică. Chiar suntem o țintă?', 'a' => 'Da. Majoritatea atacurilor sunt automatizate și lovesc oricine are o breșă: parole slabe, echipamente neactualizate, angajați care deschid atașamente. Firmele mici sunt ținte tocmai pentru că sunt mai puțin protejate.'],
            ['q' => 'Ce este NIS2 și mă afectează?', 'a' => 'NIS2 este directiva europeană privind securitatea cibernetică, transpusă în România. Se aplică direct entităților din sectoare critice și importante, dar și furnizorilor lor. Te ajutăm să afli dacă ești vizat și ce măsuri trebuie implementate.'],
            ['q' => 'Ce fac dacă am fost deja atacat?', 'a' => 'Deconectează echipamentele afectate de la rețea (fără să le oprești, dacă e posibil) și sună-ne imediat. Izolăm incidentul, evaluăm pagubele și restaurăm din backup-uri curate.'],
            ['q' => 'Cât costă un audit de securitate?', 'a' => 'Pentru IMM-uri, auditul are un preț fix, stabilit în funcție de numărul de echipamente și locații. Primești un raport clar, cu riscurile ordonate după gravitate și pașii de remediere.'],
        ],
        'body' => <<<'HTML'
<h2>Atacurile nu mai sunt o problemă „doar a firmelor mari”</h2>
<p>Ransomware care criptează toate fișierele, emailuri false care cer plata unei facturi într-un alt cont, conturi compromise din cauza unei parole reutilizate – toate acestea se întâmplă zilnic și firmelor mici și mijlocii din România. Un singur incident poate opri activitatea zile întregi.</p>
<p>Abordarea noastră e simplă: <strong>reducem riscul la un nivel rezonabil, cu măsuri practice</strong>, adaptate bugetului și dimensiunii firmei tale.</p>

<h2>Ce facem concret</h2>
<h3>1. Audit și plan de acțiune</h3>
<p>Analizăm infrastructura, conturile, backup-ul și obiceiurile de lucru. Primești un raport pe înțeles, cu riscurile ordonate după impact și cost.</p>
<h3>2. Protecția echipamentelor și a rețelei</h3>
<ul>
<li>Soluții <strong>EDR</strong> gestionate centralizat pe toate calculatoarele și serverele.</li>
<li><strong>Firewall</strong> configurat corect, segmentarea rețelei (oaspeți, angajați, echipamente).</li>
<li>Actualizări de securitate aplicate constant.</li>
</ul>
<h3>3. Protecția identităților și a emailului</h3>
<ul>
<li><strong>Autentificare în doi pași</strong> pe email, VPN și aplicațiile critice.</li>
<li>Manager de parole pentru echipă.</li>
<li>Filtrare anti-phishing și configurarea <strong>SPF, DKIM, DMARC</strong>.</li>
</ul>
<h3>4. Backup care rezistă la ransomware</h3>
<p>Regula 3-2-1: trei copii, pe două tipuri de medii, una în afara sediului – plus copii <strong>imutabile</strong>, care nu pot fi șterse sau criptate de un atacator. Și, foarte important, <strong>teste periodice de restaurare</strong>.</p>
<h3>5. Oamenii</h3>
<p>Instruiri scurte și practice, simulări de phishing și reguli clare pentru echipă. Cele mai multe incidente încep cu un click.</p>

<h2>NIS2 și conformitate</h2>
<p>Directiva <strong>NIS2</strong> impune măsuri de gestionare a riscurilor cibernetice pentru multe sectoare și, indirect, pentru furnizorii acestora. Te ajutăm cu evaluarea, politicile de securitate și măsurile tehnice necesare, în colaborare cu consultantul tău juridic.</p>

<div class="callout"><strong>Ai primit un email suspect sau crezi că ai un incident?</strong> Nu mai da click pe nimic și sună-ne. În securitate, primele ore contează cel mai mult.</div>
HTML,
    ],
    [
        'slug' => 'recuperare-date',
        'category' => 'securitate',
        'title' => 'Recuperare date',
        'h1' => 'Recuperare date de pe HDD, SSD, stick-uri, carduri și servere',
        'tagline' => 'HDD, SSD, RAID, stick, card, ștergeri accidentale',
        'icon' => 'hard-drive',
        'onsite' => 1,
        'featured' => 1,
        'excerpt' => 'Recuperăm fișiere șterse, partiții pierdute și date de pe hard diskuri, SSD-uri, stick-uri, carduri de memorie, NAS și servere RAID. Evaluare gratuită, confidențialitate totală.',
        'meta_title' => 'Recuperare date HDD, SSD, RAID, stick – evaluare gratuită',
        'meta_description' => 'Recuperare date de pe hard disk, SSD, stick USB, card de memorie, NAS și servere RAID. Fișiere șterse, formatări, defecte logice. Evaluare gratuită și confidențialitate.',
        'keywords' => 'recuperare date, recuperare date hard disk, recuperare date ssd, recuperare fisiere sterse, recuperare date raid, recuperare date targu mures',
        'features' => [
            ['icon' => 'search', 'title' => 'Evaluare gratuită', 'text' => 'Analizăm suportul și îți spunem șansele de reușită și costul înainte de orice intervenție.'],
            ['icon' => 'hard-drive', 'title' => 'HDD și SSD', 'text' => 'Defecte logice, partiții pierdute, formatări accidentale, sectoare defecte.'],
            ['icon' => 'server', 'title' => 'NAS și RAID', 'text' => 'Reconstrucție de volume RAID degradate pe Synology, QNAP și servere.'],
            ['icon' => 'box', 'title' => 'Stick-uri și carduri', 'text' => 'Poze, video și documente de pe stick-uri USB, carduri SD și microSD.'],
            ['icon' => 'lock', 'title' => 'Confidențialitate', 'text' => 'Datele tale nu sunt văzute sau păstrate. La cerere semnăm acord de confidențialitate.'],
            ['icon' => 'check-circle', 'title' => 'Plătești doar dacă reușim', 'text' => 'Pentru majoritatea cazurilor, tariful se aplică doar dacă recuperăm datele importante pentru tine.'],
        ],
        'faq' => [
            ['q' => 'Ce fac imediat după ce am pierdut datele?', 'a' => 'Nu mai scrie nimic pe dispozitiv, nu instala programe de recuperare pe el și nu îl mai porni dacă face zgomote ciudate. Fiecare încercare poate reduce șansele de recuperare.'],
            ['q' => 'Se pot recupera datele de pe un SSD?', 'a' => 'Uneori da, dar e mai dificil decât la HDD, din cauza funcției TRIM care șterge definitiv blocurile eliberate. Cu cât ne contactezi mai repede, cu atât șansele sunt mai mari.'],
            ['q' => 'Cât durează recuperarea?', 'a' => 'Evaluarea durează de obicei 1–2 zile lucrătoare. Recuperarea propriu-zisă poate dura de la câteva ore la câteva zile, în funcție de capacitate și de gradul de deteriorare.'],
            ['q' => 'Recuperați și date după un atac ransomware?', 'a' => 'În unele cazuri, da – din copii de umbră, backup-uri sau versiuni anterioare. Prevenția este însă mult mai sigură: backup-ul imutabil este singura garanție reală.'],
        ],
        'body' => <<<'HTML'
<h2>Datele pierdute nu sunt întotdeauna pierdute definitiv</h2>
<p>Un fișier șters din greșeală, un stick formatat, un hard disk care nu mai este recunoscut, un NAS cu RAID degradat – în multe dintre aceste situații <strong>datele pot fi recuperate</strong>, dacă se acționează corect și rapid.</p>

<div class="callout"><strong>Important:</strong> oprește imediat folosirea dispozitivului. Nu mai copia nimic pe el, nu rula programe de „reparare” și nu îl mai porni dacă scoate zgomote (țăcănit, bâzâit). Fiecare încercare poate transforma o recuperare simplă într-una imposibilă.</div>

<h2>Ce tipuri de situații tratăm</h2>
<ul>
<li><strong>Ștergeri accidentale</strong> – fișiere șterse, coș golit, foldere suprascrise.</li>
<li><strong>Formatări și partiții pierdute</strong> – disc formatat din greșeală, partiție dispărută, „discul trebuie formatat”.</li>
<li><strong>Sisteme de fișiere corupte</strong> – după căderi de curent, scoatere bruscă a stick-ului, erori de sistem.</li>
<li><strong>NAS și RAID</strong> – volume degradate, discuri căzute, controlere defecte.</li>
<li><strong>Carduri de memorie și stick-uri</strong> – poze și filmări de pe camere foto, drone, telefoane.</li>
</ul>

<h2>Cum lucrăm</h2>
<ol>
<li><strong>Evaluare gratuită</strong>: analizăm dispozitivul și îți spunem ce se poate recupera și cât costă.</li>
<li><strong>Clonare</strong>: lucrăm pe o copie a discului, nu pe original, ca să nu riscăm deteriorarea suplimentară.</li>
<li><strong>Recuperare și verificare</strong>: extragem datele și verificăm, împreună cu tine, că fișierele importante sunt întregi.</li>
<li><strong>Predare</strong>: pe un suport nou, cu recomandări ca să nu se repete.</li>
</ol>
<p>Pentru defectele mecanice grave (capete de citire, motor), care necesită cameră curată, colaborăm cu laboratoare specializate și îți spunem transparent costul înainte.</p>

<h2>Mai bine să nu ajungi aici</h2>
<p>Cea mai ieftină recuperare de date este un <strong>backup care funcționează</strong>. După recuperare, îți putem configura un sistem de backup automat, verificat, ca să nu mai treci niciodată prin asta.</p>
HTML,
    ],

    // =====================================================================
    // MARKETING, SEO & WEB
    // =====================================================================
    [
        'slug' => 'seo',
        'category' => 'marketing',
        'title' => 'SEO avansat',
        'h1' => 'SEO avansat – clienți din Google, fără să plătești pe fiecare click',
        'tagline' => 'Tehnic, conținut, local, AI search',
        'icon' => 'search',
        'onsite' => 0,
        'featured' => 1,
        'excerpt' => 'Optimizare SEO tehnică, conținut care răspunde la ce caută clienții, SEO local pentru Google Maps și vizibilitate în motoarele AI (ChatGPT, Gemini, Perplexity). Rapoarte clare lunare.',
        'meta_title' => 'Servicii SEO avansat – optimizare Google și AI search | VITIM',
        'meta_description' => 'SEO avansat pentru firme: audit tehnic, Core Web Vitals, date structurate, conținut, SEO local în Google Maps și optimizare pentru ChatGPT, Gemini și Perplexity. Rapoarte lunare.',
        'keywords' => 'seo, optimizare seo, servicii seo, seo targu mures, seo local, agentie seo, optimizare google, seo ai',
        'features' => [
            ['icon' => 'code', 'title' => 'SEO tehnic', 'text' => 'Viteză, Core Web Vitals, indexare, structură URL, date structurate schema.org, sitemap-uri.'],
            ['icon' => 'file', 'title' => 'Conținut strategic', 'text' => 'Pagini și articole construite pe intenția reală de căutare a clienților tăi.'],
            ['icon' => 'map', 'title' => 'SEO local', 'text' => 'Google Business Profile, citări locale, recenzii și pagini pentru fiecare oraș deservit.'],
            ['icon' => 'sparkles', 'title' => 'Vizibilitate în AI', 'text' => 'Optimizare pentru AI Overviews, ChatGPT, Gemini și Perplexity (GEO / AEO).'],
            ['icon' => 'link', 'title' => 'Autoritate', 'text' => 'Link-uri relevante din presă locală, parteneri și directoare de calitate – nu ferme de link-uri.'],
            ['icon' => 'chart', 'title' => 'Raportare clară', 'text' => 'Poziții, trafic, apeluri și cereri de ofertă – nu doar grafice frumoase.'],
        ],
        'faq' => [
            ['q' => 'În cât timp văd rezultate din SEO?', 'a' => 'Primele îmbunătățiri apar de obicei în 2–3 luni, iar rezultatele solide în 6–12 luni, în funcție de concurență și de starea inițială a site-ului. SEO este o investiție pe termen lung, cu cel mai bun randament în timp.'],
            ['q' => 'Garantați locul 1 în Google?', 'a' => 'Nu, și ar trebui să fii precaut cu oricine garantează asta. Nimeni nu controlează algoritmul Google. Garantăm în schimb o muncă transparentă, bune practici și rapoarte cu progresul real.'],
            ['q' => 'Ce este optimizarea pentru căutarea AI?', 'a' => 'Tot mai mulți oameni cer recomandări de la ChatGPT, Gemini sau Perplexity, iar Google afișează răspunsuri generate (AI Overviews). Structurăm conținutul, datele despre firmă și reputația online ca să fii citat și recomandat și acolo.'],
            ['q' => 'Lucrați și pe site-uri făcute de alții?', 'a' => 'Da. Lucrăm pe WordPress, WooCommerce, Shopify și site-uri custom. Dacă platforma limitează serios SEO-ul, îți spunem sincer și îți propunem soluții.'],
        ],
        'body' => <<<'HTML'
<h2>SEO care aduce clienți, nu doar trafic</h2>
<p>Scopul SEO nu e să „fii pe prima pagină” la un cuvânt ales la întâmplare. Scopul e ca <strong>oamenii care au nevoie exact de ce vinzi tu</strong> să te găsească în Google – și acum și în asistenții AI – și să te contacteze.</p>

<h2>Ce include SEO avansat</h2>
<h3>SEO tehnic</h3>
<ul>
<li>Audit complet: indexare, erori, pagini duplicate, redirecționări, canonicalizare.</li>
<li><strong>Viteză și Core Web Vitals</strong> (LCP, INP, CLS) – optimizare reală, nu doar pentru scor.</li>
<li><strong>Date structurate schema.org</strong>: LocalBusiness, Service, FAQ, Article, Breadcrumb, Product.</li>
<li>Sitemap-uri XML, robots.txt, hreflang, arhitectură internă de linkuri.</li>
</ul>
<h3>Conținut</h3>
<ul>
<li>Cercetare de cuvinte cheie pe <strong>intenția de căutare</strong> (informare, comparare, cumpărare).</li>
<li>Pagini de servicii și pagini locale care răspund complet la întrebările clienților.</li>
<li>Articole de blog care atrag, educă și aduc linkuri naturale.</li>
</ul>
<h3>SEO local</h3>
<ul>
<li>Optimizarea completă a <strong>Google Business Profile</strong> și strategie de recenzii.</li>
<li>Consistența datelor firmei (NAP) în directoare și hărți.</li>
<li>Pagini pentru fiecare oraș și județ deservit, fără conținut duplicat.</li>
</ul>
<h3>Optimizare pentru motoarele AI (GEO)</h3>
<p>În 2026, o parte tot mai mare din căutări se termină într-un răspuns generat de AI. Optimizăm entitatea firmei tale, conținutul și semnalele de încredere ca să fii <strong>citat în AI Overviews, ChatGPT, Gemini și Perplexity</strong>, inclusiv prin fișiere dedicate precum <code>llms.txt</code>.</p>

<h2>Cum măsurăm succesul</h2>
<p>Configurăm Google Search Console, Analytics 4 și urmărirea conversiilor (formulare, apeluri, WhatsApp). În raportul lunar vezi <strong>câte cereri de ofertă și apeluri</strong> au venit din căutare organică, nu doar poziții și grafice.</p>
HTML,
    ],
    [
        'slug' => 'marketing-tehnic',
        'category' => 'marketing',
        'title' => 'Marketing tehnic & tracking',
        'h1' => 'Marketing tehnic: tracking corect, date curate, decizii bune',
        'tagline' => 'GA4, GTM, server-side, CRO, automatizări',
        'icon' => 'chart',
        'onsite' => 0,
        'featured' => 0,
        'excerpt' => 'Configurăm Google Analytics 4, Tag Manager, tracking server-side, Consent Mode v2, Meta Conversions API și dashboard-uri, ca să știi exact ce campanie îți aduce clienți.',
        'meta_title' => 'Marketing tehnic: GA4, Tag Manager, server-side tracking, CAPI',
        'meta_description' => 'Tracking corect pentru campanii: Google Analytics 4, Google Tag Manager, server-side tagging, Consent Mode v2, Meta Conversions API, dashboard-uri Looker Studio și optimizarea conversiilor.',
        'keywords' => 'marketing tehnic, google analytics 4, google tag manager, server side tracking, consent mode v2, meta conversions api, looker studio',
        'features' => [
            ['icon' => 'chart', 'title' => 'GA4 configurat corect', 'text' => 'Evenimente, conversii, audiențe și filtre – nu doar codul pus pe site.'],
            ['icon' => 'code', 'title' => 'Google Tag Manager', 'text' => 'Toate etichetele într-un singur loc, ușor de controlat și de auditat.'],
            ['icon' => 'server', 'title' => 'Tracking server-side', 'text' => 'Date mai complete, site mai rapid, mai puțin impact de la blocante și restricții de browser.'],
            ['icon' => 'shield', 'title' => 'Consent Mode v2 & GDPR', 'text' => 'Banner de cookies conform, cu măsurare modelată când utilizatorul refuză.'],
            ['icon' => 'target', 'title' => 'Meta CAPI & Enhanced Conversions', 'text' => 'Conversii trimise direct din server către Meta și Google Ads.'],
            ['icon' => 'dashboard', 'title' => 'Dashboard-uri', 'text' => 'Looker Studio cu costuri, lead-uri și vânzări pe canal, actualizate automat.'],
        ],
        'faq' => [
            ['q' => 'De ce diferă cifrele din Google Ads, Meta și Analytics?', 'a' => 'Fiecare platformă atribuie conversiile diferit, iar blocantele de reclame și refuzul cookies-urilor pierd date. Configurăm tracking-ul astfel încât să ai o sursă de adevăr coerentă și explicăm clar diferențele.'],
            ['q' => 'Ce este Consent Mode v2 și e obligatoriu?', 'a' => 'Este mecanismul Google prin care etichetele respectă alegerea utilizatorului privind cookies. Pentru a folosi funcțiile de remarketing și măsurare în Google Ads pentru utilizatorii din UE, implementarea lui este practic necesară.'],
            ['q' => 'Merită tracking server-side pentru o firmă mică?', 'a' => 'Dacă investești lunar sume serioase în reclame, da – datele mai complete îmbunătățesc optimizarea campaniilor. Pentru bugete mici, o configurare client-side corectă e suficientă la început.'],
        ],
        'body' => <<<'HTML'
<h2>Fără date corecte, marketingul e ghicit</h2>
<p>Cele mai multe conturi de Google Ads și Meta Ads pe care le auditam au aceeași problemă: <strong>conversiile nu sunt măsurate corect</strong>. Se numără vizite pe pagina de mulțumire de două ori, apelurile telefonice nu sunt urmărite deloc, iar algoritmii de licitare optimizează pe date greșite.</p>

<h2>Ce implementăm</h2>
<ul>
<li><strong>Google Analytics 4</strong>: evenimente cheie, conversii, audiențe, excluderea traficului intern, legături cu Ads și Search Console.</li>
<li><strong>Google Tag Manager</strong>: structură curată, denumiri clare, versiuni documentate.</li>
<li><strong>Tracking de conversii complet</strong>: formulare, apeluri din site, click pe WhatsApp, email, rezervări, achiziții.</li>
<li><strong>Consent Mode v2</strong> și banner de cookies conform GDPR.</li>
<li><strong>Server-side tagging</strong> și <strong>Meta Conversions API</strong> pentru date mai robuste.</li>
<li><strong>Enhanced Conversions</strong> și importul conversiilor offline din CRM (când un lead devine client).</li>
</ul>

<h2>Optimizarea conversiilor (CRO)</h2>
<p>Trafic ai. Întrebarea e câți vizitatori devin clienți. Analizăm hărți de click, înregistrări de sesiuni și pâlnia de conversie, apoi testăm îmbunătățiri concrete: titluri, formulare, dovezi sociale, viteză.</p>

<h2>Legătura cu CRM-ul</h2>
<p>Fiecare lead are o sursă (Google Ads, Meta, organic, recomandare). Când lead-ul devine client, trimitem informația înapoi în platformele de reclame – astfel algoritmii învață să aducă <strong>clienți</strong>, nu doar formulare completate.</p>
HTML,
    ],
    [
        'slug' => 'google-ads',
        'category' => 'marketing',
        'title' => 'Campanii Google Ads',
        'h1' => 'Campanii Google Ads care aduc clienți, nu doar click-uri',
        'tagline' => 'Search, Performance Max, YouTube, remarketing',
        'icon' => 'target',
        'onsite' => 0,
        'featured' => 0,
        'excerpt' => 'Strategie, configurare și optimizare de campanii Google Ads: Search, Performance Max, YouTube, Display și remarketing, cu tracking corect al conversiilor și raportare pe costul per client.',
        'meta_title' => 'Administrare campanii Google Ads – Search, PMax, YouTube',
        'meta_description' => 'Campanii Google Ads administrate profesionist: cercetare cuvinte cheie, Search, Performance Max, YouTube, remarketing, tracking conversii și optimizare continuă pe cost per client.',
        'keywords' => 'google ads, campanii google ads, administrare google ads, reclame google, ppc, performance max, agentie google ads',
        'features' => [
            ['icon' => 'search', 'title' => 'Campanii Search', 'text' => 'Apari exact când cineva caută serviciul tău, cu anunțuri relevante și extensii complete.'],
            ['icon' => 'rocket', 'title' => 'Performance Max', 'text' => 'Acoperire pe toate rețelele Google, cu semnale de audiență și excluderi corecte.'],
            ['icon' => 'youtube', 'title' => 'YouTube & Demand Gen', 'text' => 'Notorietate și remarketing video pentru produse și servicii cu decizie mai lungă.'],
            ['icon' => 'target', 'title' => 'Tracking conversii', 'text' => 'Formulare, apeluri, WhatsApp și conversii offline din CRM.'],
            ['icon' => 'trending', 'title' => 'Optimizare continuă', 'text' => 'Cuvinte negative, teste de anunțuri, ajustări de buget și licitare săptămânal.'],
            ['icon' => 'chart', 'title' => 'Raport pe bani', 'text' => 'Cost per lead, cost per client și rentabilitate – nu doar afișări și click-uri.'],
        ],
        'faq' => [
            ['q' => 'Ce buget minim recomandați pentru Google Ads?', 'a' => 'Depinde de nișă și de costul pe click din domeniul tău. Pentru servicii locale, recomandăm de obicei un buget de pornire care să permită minimum 100–150 de click-uri pe lună, ca algoritmii să aibă date. Îți facem o estimare înainte de a porni.'],
            ['q' => 'Cât timp durează până văd rezultate?', 'a' => 'Campaniile de Search pot aduce lead-uri din prima săptămână. Optimizarea reală se vede după 4–8 săptămâni, când avem suficiente date de conversie.'],
            ['q' => 'Contul de Google Ads rămâne al meu?', 'a' => 'Da, întotdeauna. Contul, datele și istoricul îți aparțin; noi lucrăm cu acces de administrator oferit de tine.'],
            ['q' => 'Faceți și pagini de destinație?', 'a' => 'Da. O pagină rapidă, relevantă pentru anunț și cu un formular simplu poate reduce la jumătate costul pe lead.'],
        ],
        'body' => <<<'HTML'
<h2>Google Ads: vizibil exact în momentul în care clientul caută</h2>
<p>Spre deosebire de alte forme de publicitate, <strong>Google Ads</strong> te arată oamenilor care caută activ ce oferi tu: „reparații laptop Târgu Mureș”, „firmă IT Alba Iulia”, „recuperare date hard disk”. Asta înseamnă intenție mare de cumpărare și rezultate rapide – dacă e configurat corect.</p>

<h2>Cum construim o campanie profitabilă</h2>
<ol>
<li><strong>Cercetare</strong>: cuvinte cheie cu intenție comercială, concurență, costuri estimate.</li>
<li><strong>Structură</strong>: campanii și grupuri de anunțuri separate pe servicii și zone.</li>
<li><strong>Anunțuri</strong>: texte relevante, extensii (apel, locație, linkuri, fragmente), recenzii.</li>
<li><strong>Tracking</strong>: conversii măsurate corect înainte de a cheltui primul leu.</li>
<li><strong>Pagini de destinație</strong>: rapide, specifice, cu un pas clar de urmat.</li>
<li><strong>Optimizare</strong>: săptămânal – cuvinte negative, licitări, teste A/B, bugete.</li>
</ol>

<h2>Tipuri de campanii</h2>
<ul>
<li><strong>Search</strong> – cea mai directă sursă de lead-uri pentru servicii.</li>
<li><strong>Performance Max</strong> – pentru magazine online și pentru acoperire extinsă.</li>
<li><strong>YouTube și Demand Gen</strong> – notorietate și remarketing vizual.</li>
<li><strong>Remarketing</strong> – readucem vizitatorii care nu au cerut încă ofertă.</li>
<li><strong>Campanii locale</strong> – apeluri și vizite, cu extensii de locație din Google Business Profile.</li>
</ul>

<h2>Transparență totală</h2>
<p>Contul rămâne al tău. Primești raport lunar cu <strong>cost per lead și cost per client</strong>, plus recomandări concrete. Fără contracte pe termen lung obligatorii și fără comisioane ascunse din buget.</p>
HTML,
    ],
    [
        'slug' => 'meta-ads',
        'category' => 'marketing',
        'title' => 'Campanii Meta Ads (Facebook & Instagram)',
        'h1' => 'Campanii Meta Ads pe Facebook și Instagram',
        'tagline' => 'Lead-uri, vânzări și notorietate locală',
        'icon' => 'megaphone',
        'onsite' => 0,
        'featured' => 0,
        'excerpt' => 'Campanii Facebook și Instagram pentru lead-uri, vânzări și notorietate locală: strategie, creații, audiențe, Pixel + Conversions API și optimizare pe costul per client.',
        'meta_title' => 'Campanii Facebook & Instagram Ads – administrare Meta Ads',
        'meta_description' => 'Administrare campanii Meta Ads (Facebook, Instagram): strategie, creații video și grafice, formulare de lead, Pixel și Conversions API, remarketing și raportare pe cost per client.',
        'keywords' => 'meta ads, facebook ads, reclame facebook, instagram ads, campanii facebook, administrare reclame facebook, lead ads',
        'features' => [
            ['icon' => 'target', 'title' => 'Strategie pe obiectiv', 'text' => 'Lead-uri, mesaje, vânzări sau notorietate – structura campaniei urmează obiectivul de business.'],
            ['icon' => 'image', 'title' => 'Creații care opresc scroll-ul', 'text' => 'Concepte video scurte, carusele și grafice adaptate pentru Reels și Stories.'],
            ['icon' => 'users', 'title' => 'Audiențe inteligente', 'text' => 'Audiențe similare, remarketing, clienți existenți din CRM, targetare locală.'],
            ['icon' => 'inbox', 'title' => 'Lead Ads + CRM', 'text' => 'Formularele din Facebook ajung automat în CRM, cu notificare instant.'],
            ['icon' => 'code', 'title' => 'Pixel + Conversions API', 'text' => 'Măsurare robustă, chiar și cu restricțiile iOS și ale browserelor.'],
            ['icon' => 'chart', 'title' => 'Raportare clară', 'text' => 'Cost per lead, calitatea lead-urilor și rezultate reale în vânzări.'],
        ],
        'faq' => [
            ['q' => 'Facebook Ads sau Google Ads?', 'a' => 'Google Ads captează cererea existentă (oameni care caută deja). Meta Ads creează cerere și e excelent pentru oferte vizuale, evenimente, afaceri locale și remarketing. De multe ori combinația dă cele mai bune rezultate.'],
            ['q' => 'Faceți și creațiile (video, grafică)?', 'a' => 'Da, realizăm concepte și creații adaptate fiecărui format, sau lucrăm cu materialele tale și le optimizăm pentru performanță.'],
            ['q' => 'Lead-urile din Facebook sunt de calitate slabă. Se poate îmbunătăți?', 'a' => 'Da: formulare cu întrebări de calificare, optimizare pe lead-uri calificate prin Conversions API și excluderea audiențelor nepotrivite. Calitatea crește vizibil.'],
        ],
        'body' => <<<'HTML'
<h2>Reclame pe Facebook și Instagram care se transformă în clienți</h2>
<p>Pe <strong>Facebook și Instagram</strong> clienții tăi petrec zilnic zeci de minute. Cu o strategie potrivită, Meta Ads aduce lead-uri, mesaje și vânzări la costuri bune – mai ales pentru afacerile locale și ofertele vizuale.</p>

<h2>Ce facem concret</h2>
<ul>
<li><strong>Strategie</strong>: obiective, oferte, pâlnie de conversie (rece → cald → client).</li>
<li><strong>Creații</strong>: video scurte pentru Reels, carusele, grafice, texte care vând.</li>
<li><strong>Audiențe</strong>: locale, de interes, similare cu clienții existenți, remarketing.</li>
<li><strong>Lead Ads</strong> integrate cu CRM-ul și notificări instant pentru echipa de vânzări.</li>
<li><strong>Pixel Meta + Conversions API</strong> pentru măsurare corectă și optimizare.</li>
<li><strong>Optimizare săptămânală</strong> și teste A/B pe creații și audiențe.</li>
</ul>

<h2>Pentru afacerile locale din Mureș, Bistrița-Năsăud și Alba</h2>
<p>Targetarea pe rază geografică permite campanii eficiente chiar și cu bugete mici: clinici, service-uri, restaurante, pensiuni, magazine, firme de construcții. Combinăm reclamele cu <strong>WhatsApp și Messenger</strong>, ca oamenii să te poată contacta dintr-un singur click.</p>
HTML,
    ],
    [
        'slug' => 'site-uri-web',
        'category' => 'marketing',
        'title' => 'Site-uri web & magazine online',
        'h1' => 'Site-uri web rapide și magazine online care convertesc',
        'tagline' => 'Rapide, SEO-ready, cu CRM integrat',
        'icon' => 'globe',
        'onsite' => 0,
        'featured' => 0,
        'excerpt' => 'Site-uri de prezentare, pagini de destinație și magazine online rapide, optimizate SEO din start, cu tracking, formulare conectate la CRM și panou de administrare simplu.',
        'meta_title' => 'Creare site-uri web și magazine online rapide, optimizate SEO',
        'meta_description' => 'Creare site de prezentare, landing page și magazin online: design modern, viteză excelentă, SEO tehnic din start, tracking conversii și integrare cu CRM și email marketing.',
        'keywords' => 'creare site, creare site web, web design, magazin online, landing page, site prezentare, creare site targu mures',
        'features' => [
            ['icon' => 'zap', 'title' => 'Viteză excelentă', 'text' => 'Core Web Vitals în verde, încărcare rapidă pe mobil, fără module inutile.'],
            ['icon' => 'search', 'title' => 'SEO din start', 'text' => 'Structură, date structurate, sitemap, meta și redirecționări gândite de la început.'],
            ['icon' => 'monitor', 'title' => 'Design modern', 'text' => 'Interfață curată, adaptată pe mobil, cu accent pe încredere și conversie.'],
            ['icon' => 'kanban', 'title' => 'CRM & email marketing', 'text' => 'Formularele ajung direct în CRM, cu newsletter și automatizări.'],
            ['icon' => 'shopping', 'title' => 'Magazine online', 'text' => 'WooCommerce sau Shopify, plăți online, curierat, facturare și feed-uri.'],
            ['icon' => 'shield', 'title' => 'Securitate & mentenanță', 'text' => 'Actualizări, backup, monitorizare și suport după lansare.'],
        ],
        'faq' => [
            ['q' => 'Cât costă un site de prezentare?', 'a' => 'Depinde de numărul de pagini, funcționalități și conținut. După o discuție scurtă primești o ofertă fixă, cu tot ce include clar specificat.'],
            ['q' => 'Cât durează realizarea unui site?', 'a' => 'Un site de prezentare durează de obicei 2–5 săptămâni, iar un magazin online 4–10 săptămâni, în funcție de complexitate și de cât de repede avem conținutul.'],
            ['q' => 'Voi putea modifica singur site-ul?', 'a' => 'Da. Primești un panou de administrare simplu și o scurtă instruire. Pentru modificări mai mari suntem mereu disponibili.'],
            ['q' => 'Mutați site-ul existent fără să pierd pozițiile din Google?', 'a' => 'Da, facem migrarea cu redirecționări 301 pentru toate adresele vechi, păstrăm conținutul valoros și monitorizăm indexarea după lansare.'],
        ],
        'body' => <<<'HTML'
<h2>Un site care lucrează pentru tine, nu doar arată bine</h2>
<p>Un site bun în 2026 trebuie să fie <strong>rapid, clar, credibil și ușor de găsit</strong>. Trebuie să răspundă în câteva secunde la întrebarea vizitatorului – „mă poate ajuta firma asta?” – și să-i facă foarte ușor pasul următor: un apel, un mesaj, o cerere de ofertă sau o comandă.</p>

<h2>Ce construim</h2>
<ul>
<li><strong>Site-uri de prezentare</strong> pentru firme de servicii, cabinete, producători.</li>
<li><strong>Pagini de destinație</strong> pentru campanii Google Ads și Meta Ads.</li>
<li><strong>Magazine online</strong> cu plăți, curierat, facturare automată și feed-uri pentru comparatoare.</li>
<li><strong>Portaluri și aplicații web</strong> personalizate, inclusiv cu funcții AI.</li>
</ul>

<h2>Standardul VITIM pentru fiecare site</h2>
<ul>
<li>Design responsive, optimizat întâi pentru mobil.</li>
<li>Viteză: imagini optimizate, cache, cod curat, fără pluginuri inutile.</li>
<li>SEO tehnic complet: meta, schema.org, sitemap, redirecționări, URL-uri curate.</li>
<li>Tracking de conversii și banner de cookies conform GDPR.</li>
<li>Formulare conectate la CRM, cu notificări instant.</li>
<li>Pagini legale și securitate (HTTPS, backup, actualizări).</li>
</ul>
HTML,
    ],

    // =====================================================================
    // AI & AUTOMATIZĂRI
    // =====================================================================
    [
        'slug' => 'agenti-ai-software-personalizat',
        'category' => 'ai',
        'title' => 'Agenți AI & software personalizat',
        'h1' => 'Agenți AI și software personalizat pentru afacerea ta',
        'tagline' => 'Asistenți AI construiți pe procesele tale',
        'icon' => 'bot',
        'onsite' => 0,
        'featured' => 1,
        'excerpt' => 'Construim agenți AI și aplicații personalizate care lucrează cu datele firmei tale: asistenți pentru clienți, generatoare de oferte, analiză de documente, rapoarte automate și instrumente interne.',
        'meta_title' => 'Agenți AI și software personalizat pentru firme | VITIM',
        'meta_description' => 'Dezvoltare agenți AI și software personalizat: asistenți care răspund clienților, generează oferte, procesează documente și facturi, lucrează în CRM și ERP. Date sigure, sub controlul tău.',
        'keywords' => 'agenti ai, agent ai firma, software personalizat, dezvoltare ai, chatbot ai, asistent ai, inteligenta artificiala firme',
        'features' => [
            ['icon' => 'message', 'title' => 'Asistent pentru clienți', 'text' => 'Răspunde 24/7 pe site, WhatsApp sau email, din documentele și ofertele tale – și predă conversația unui om când e cazul.'],
            ['icon' => 'file', 'title' => 'Documente & facturi', 'text' => 'Extrage automat date din facturi, contracte, comenzi și le introduce în sistemele tale.'],
            ['icon' => 'pen', 'title' => 'Oferte & rapoarte', 'text' => 'Generează oferte, rapoarte și emailuri în stilul firmei, gata de aprobat.'],
            ['icon' => 'brain', 'title' => 'Cunoașterea firmei', 'text' => 'Un „ChatGPT intern” care știe procedurile, produsele și istoricul firmei tale.'],
            ['icon' => 'code', 'title' => 'Aplicații la comandă', 'text' => 'Instrumente web interne, portaluri pentru clienți, integrări între sisteme.'],
            ['icon' => 'lock', 'title' => 'Securitate & control', 'text' => 'Acces pe roluri, jurnal al acțiunilor, date găzduite în UE, fără antrenarea modelelor pe datele tale.'],
        ],
        'faq' => [
            ['q' => 'Care e diferența dintre un chatbot și un agent AI?', 'a' => 'Un chatbot doar răspunde la întrebări. Un agent AI poate și acționa: caută în sistemele tale, completează formulare, creează înregistrări în CRM, trimite emailuri, generează documente – în limitele și cu aprobările stabilite de tine.'],
            ['q' => 'Sunt datele firmei în siguranță?', 'a' => 'Da. Folosim variante de business ale modelelor AI, unde datele nu sunt folosite pentru antrenare, găzduire în UE unde e posibil, criptare și acces pe roluri. Pentru date foarte sensibile putem folosi și modele rulate local.'],
            ['q' => 'Cât costă un agent AI?', 'a' => 'Începem de obicei cu un proiect pilot limitat, cu cost fix, pe un singur proces cu impact mare. Dacă rezultatele sunt bune, extindem. Costurile de utilizare a modelelor AI sunt transparente și de regulă mici comparativ cu timpul economisit.'],
            ['q' => 'Ce modele AI folosiți?', 'a' => 'Alegem modelul potrivit pentru fiecare sarcină: modele de ultimă generație de la Anthropic (Claude), OpenAI sau Google (Gemini), ori modele open-source rulate local. Nu suntem legați de un singur furnizor.'],
        ],
        'body' => <<<'HTML'
<h2>AI care face muncă reală în firma ta</h2>
<p>Instrumentele AI generale sunt utile, dar cea mai mare valoare apare atunci când AI-ul <strong>cunoaște firma ta și are acces controlat la sistemele tale</strong>. Asta face un agent AI: un asistent software construit pe procesele, documentele și aplicațiile tale, care preia sarcinile repetitive.</p>

<h2>Exemple concrete de agenți AI</h2>
<ul>
<li><strong>Asistent de vânzări</strong>: răspunde la întrebări pe site și WhatsApp, califică lead-ul, programează o discuție și completează CRM-ul.</li>
<li><strong>Generator de oferte</strong>: primește o cerere pe email, verifică prețurile și stocul, pregătește oferta PDF pentru aprobare.</li>
<li><strong>Procesare documente</strong>: citește facturi, avize și contracte, extrage datele și le introduce în contabilitate sau ERP.</li>
<li><strong>Asistent intern</strong>: răspunde angajaților din proceduri, manuale și politici interne.</li>
<li><strong>Analist de rapoarte</strong>: sintetizează săptămânal vânzările, campaniile sau tichetele de suport.</li>
</ul>

<h2>Software personalizat, cu AI integrat</h2>
<p>Uneori soluția potrivită nu e un program cumpărat, ci o aplicație simplă construită exact pentru fluxul tău: un portal pentru clienți, un sistem de programări, un instrument de gestiune internă. Le dezvoltăm modern, rapid și cu AI integrat acolo unde aduce valoare.</p>

<h2>Cum lucrăm</h2>
<ol>
<li><strong>Atelier de descoperire</strong>: identificăm 2–3 procese unde AI-ul economisește cel mai mult timp.</li>
<li><strong>Pilot</strong>: construim un prim agent pe un singur proces, cu cost și termen fix.</li>
<li><strong>Testare cu echipa</strong>: ajustăm pe baza feedback-ului real, cu aprobare umană unde contează.</li>
<li><strong>Lansare și îmbunătățire</strong>: monitorizăm calitatea răspunsurilor și costurile, extindem pas cu pas.</li>
</ol>

<div class="callout"><strong>Principiul nostru:</strong> AI-ul propune, omul decide acolo unde miza e mare. Construim agenți utili, previzibili și ușor de controlat.</div>
HTML,
    ],
    [
        'slug' => 'integrare-agenti-ai',
        'category' => 'ai',
        'title' => 'Integrare agenți AI',
        'h1' => 'Integrare agenți AI în sistemele pe care le folosești deja',
        'tagline' => 'AI conectat la CRM, ERP, email, WhatsApp',
        'icon' => 'plug',
        'onsite' => 0,
        'featured' => 0,
        'excerpt' => 'Conectăm agenți AI la emailul, CRM-ul, ERP-ul, magazinul online, WhatsApp și documentele firmei, prin API-uri și MCP, cu permisiuni clare și jurnal complet al acțiunilor.',
        'meta_title' => 'Integrare AI în CRM, ERP, email și WhatsApp | Agenți AI',
        'meta_description' => 'Integrăm agenți AI (Claude, ChatGPT, Gemini) cu CRM, ERP, Microsoft 365, Google Workspace, WhatsApp, magazine online și baze de date, prin API și MCP, în siguranță.',
        'keywords' => 'integrare ai, integrare chatgpt, integrare claude, ai crm, ai erp, mcp, api ai, automatizare ai whatsapp',
        'features' => [
            ['icon' => 'mail', 'title' => 'Email & calendar', 'text' => 'Microsoft 365 și Google Workspace: triere, răspunsuri propuse, programări.'],
            ['icon' => 'kanban', 'title' => 'CRM & vânzări', 'text' => 'Lead-uri îmbogățite, rezumate de conversații, follow-up-uri automate.'],
            ['icon' => 'database', 'title' => 'ERP & baze de date', 'text' => 'Interogări în limbaj natural pe stocuri, comenzi și facturi.'],
            ['icon' => 'whatsapp', 'title' => 'WhatsApp & chat', 'text' => 'Asistent pe WhatsApp Business și chat pe site, cu predare către un om.'],
            ['icon' => 'plug', 'title' => 'API & MCP', 'text' => 'Conectori standard Model Context Protocol pentru acces controlat la instrumente.'],
            ['icon' => 'eye', 'title' => 'Control și audit', 'text' => 'Permisiuni minime, aprobări pentru acțiuni sensibile, jurnal complet.'],
        ],
        'faq' => [
            ['q' => 'Ce este MCP (Model Context Protocol)?', 'a' => 'Este un standard deschis prin care asistenții AI se conectează la aplicații și date (CRM, fișiere, baze de date) într-un mod controlat. Îl folosim pentru integrări curate și ușor de întreținut.'],
            ['q' => 'Trebuie să schimbăm programele pe care le folosim?', 'a' => 'Nu. Integrarea se face peste sistemele existente, prin API-uri, conectori sau, la nevoie, prin automatizări. Scopul e ca echipa să lucreze la fel, doar mai repede.'],
            ['q' => 'Ce se întâmplă dacă AI-ul greșește?', 'a' => 'Pentru acțiunile cu impact (plăți, emailuri către clienți, modificări de date importante) setăm aprobare umană obligatorie. Toate acțiunile sunt înregistrate și pot fi verificate.'],
        ],
        'body' => <<<'HTML'
<h2>AI-ul e util doar dacă e conectat</h2>
<p>Un asistent AI care nu are acces la informațiile firmei va da răspunsuri generale. Unul <strong>integrat cu emailul, CRM-ul, ERP-ul și documentele tale</strong> poate lucra efectiv: poate găsi o comandă, verifica un stoc, pregăti o ofertă sau actualiza un client.</p>

<h2>Ce integrăm</h2>
<ul>
<li><strong>Microsoft 365 și Google Workspace</strong>: emailuri, calendar, OneDrive/Drive, Teams/Meet.</li>
<li><strong>CRM-uri</strong>: HubSpot, Pipedrive, Zoho, Salesforce sau CRM-uri personalizate.</li>
<li><strong>ERP și contabilitate</strong>: prin API sau export/import automatizat.</li>
<li><strong>Magazine online</strong>: WooCommerce, Shopify – comenzi, stocuri, clienți.</li>
<li><strong>Canale de comunicare</strong>: WhatsApp Business, chat pe site, Messenger.</li>
<li><strong>Baze de documente</strong>: proceduri, contracte, cataloage, manuale – cu căutare semantică.</li>
</ul>

<h2>Cum ne asigurăm că integrarea e sigură</h2>
<ul>
<li><strong>Principiul privilegiului minim</strong>: agentul are acces doar la ce are nevoie.</li>
<li><strong>Aprobări</strong> pentru acțiuni sensibile și limite clare de acțiune.</li>
<li><strong>Jurnal complet</strong> al fiecărei acțiuni făcute de AI.</li>
<li><strong>Protecție la „prompt injection”</strong>: datele venite din exterior sunt tratate ca date, nu ca instrucțiuni.</li>
<li><strong>Conformitate GDPR</strong>: date minimizate, furnizori cu acorduri de prelucrare, găzduire în UE unde e posibil.</li>
</ul>
HTML,
    ],
    [
        'slug' => 'automatizari',
        'category' => 'ai',
        'title' => 'Automatizări de procese',
        'h1' => 'Automatizări de procese: mai puțină muncă manuală, zero copy-paste',
        'tagline' => 'n8n, Make, Zapier, Power Automate',
        'icon' => 'workflow',
        'onsite' => 0,
        'featured' => 0,
        'excerpt' => 'Automatizăm fluxurile repetitive dintre aplicații: lead-uri, facturi, rapoarte, notificări, aprobări și sincronizări de date, cu n8n, Make, Zapier sau Power Automate – și AI acolo unde are sens.',
        'meta_title' => 'Automatizări procese business – n8n, Make, Zapier, Power Automate',
        'meta_description' => 'Automatizăm procesele repetitive din firmă: lead-uri, facturare, rapoarte, notificări, sincronizare CRM și e-Factura, cu n8n, Make, Zapier, Power Automate și AI.',
        'keywords' => 'automatizari, automatizare procese, n8n, make, zapier, power automate, automatizare facturi, automatizare crm',
        'features' => [
            ['icon' => 'inbox', 'title' => 'Lead-uri automate', 'text' => 'Din formulare, Facebook, email sau telefon direct în CRM, cu notificare și sarcină de follow-up.'],
            ['icon' => 'file', 'title' => 'Facturare & documente', 'text' => 'Generare facturi, trimitere automată, arhivare și urmărirea încasărilor.'],
            ['icon' => 'chart', 'title' => 'Rapoarte automate', 'text' => 'Rapoarte zilnice sau săptămânale trimise pe email ori în Teams/Slack.'],
            ['icon' => 'refresh', 'title' => 'Sincronizare între aplicații', 'text' => 'Clienți, produse, comenzi și stocuri sincronizate fără copy-paste.'],
            ['icon' => 'check-circle', 'title' => 'Aprobări', 'text' => 'Fluxuri de aprobare pentru achiziții, concedii, oferte și cheltuieli.'],
            ['icon' => 'sparkles', 'title' => 'Pași cu AI', 'text' => 'Clasificare emailuri, extragere de date, rezumate și răspunsuri propuse.'],
        ],
        'faq' => [
            ['q' => 'Ce procese merită automatizate?', 'a' => 'Cele repetitive, bazate pe reguli și care se fac des: introducere de date, copiere între aplicații, trimiterea de documente, notificări, rapoarte. Dacă cineva face același lucru de 20 de ori pe săptămână, probabil merită automatizat.'],
            ['q' => 'n8n, Make sau Zapier?', 'a' => 'Zapier e cel mai simplu, Make e flexibil și accesibil, iar n8n poate fi găzduit la tine, fără costuri per operație, ideal pentru volume mari și date sensibile. Recomandăm în funcție de caz.'],
            ['q' => 'Ce se întâmplă când o automatizare se strică?', 'a' => 'Configurăm alerte pentru erori și monitorizăm fluxurile. În abonamentul de mentenanță, intervenim rapid și ajustăm automatizările când se schimbă aplicațiile conectate.'],
        ],
        'body' => <<<'HTML'
<h2>Câte ore pe săptămână pierde echipa ta cu copy-paste?</h2>
<p>Date copiate dintr-un email în CRM, facturi trimise manual, rapoarte făcute în Excel în fiecare vineri, notificări uitate. Fiecare sarcină pare mică, dar împreună înseamnă <strong>zeci de ore pe lună</strong> și greșeli costisitoare.</p>

<h2>Exemple de automatizări pe care le implementăm des</h2>
<ul>
<li>Formular de pe site → contact în CRM → email de confirmare → sarcină de follow-up pentru vânzări.</li>
<li>Comandă nouă în magazinul online → factură → email către client → actualizare stoc.</li>
<li>Email cu factură de la furnizor → extragere date cu AI → înregistrare în contabilitate → arhivare.</li>
<li>Raport automat de vânzări și campanii, în fiecare luni dimineață, pe email.</li>
<li>Angajat nou → conturi create, licențe alocate, acces la foldere, checklist de onboarding.</li>
<li>Recenzie nouă pe Google → notificare + propunere de răspuns generată de AI.</li>
</ul>

<h2>Instrumente</h2>
<p>Lucrăm cu <strong>n8n</strong> (inclusiv găzduit pe serverul tău, pentru control total), <strong>Make</strong>, <strong>Zapier</strong> și <strong>Microsoft Power Automate</strong>, plus scripturi și integrări API personalizate unde e nevoie.</p>

<h2>Rezultatul</h2>
<p>Mai puțină muncă manuală, mai puține greșeli, răspuns mai rapid către clienți și o echipă care se poate concentra pe ce contează. Măsurăm împreună timpul economisit, ca să vezi clar valoarea.</p>
HTML,
    ],
    [
        'slug' => 'ai-pentru-firme',
        'category' => 'ai',
        'title' => 'AI pentru firme: implementare & instruire',
        'h1' => 'AI pentru firme: implementare, instruire și reguli clare',
        'tagline' => 'ChatGPT, Gemini, Copilot – folosite corect',
        'icon' => 'sparkles',
        'onsite' => 1,
        'featured' => 0,
        'excerpt' => 'Te ajutăm să folosești sistemele AI (ChatGPT, Gemini, Microsoft Copilot și altele) în mod productiv și sigur: alegerea instrumentelor, configurare pentru firmă, politici de utilizare și instruirea echipei.',
        'meta_title' => 'Implementare AI în firmă – ChatGPT, Claude, Gemini, Copilot | Instruire',
        'meta_description' => 'Implementare AI în firmă: alegerea și configurarea ChatGPT, Claude, Gemini sau Microsoft Copilot, politici de utilizare AI, protecția datelor, conformare AI Act și instruiri practice pentru echipă.',
        'keywords' => 'ai pentru firme, implementare ai, instruire ai, curs chatgpt firme, microsoft copilot, claude, gemini, ai act, politica ai',
        'features' => [
            ['icon' => 'search', 'title' => 'Evaluare & strategie', 'text' => 'Unde aduce AI-ul valoare reală în firma ta și cu ce instrumente.'],
            ['icon' => 'settings', 'title' => 'Configurare pentru firmă', 'text' => 'Conturi de business, spații de lucru, proiecte și asistenți personalizați pe departamente.'],
            ['icon' => 'lock', 'title' => 'Protecția datelor', 'text' => 'Ce date pot fi folosite cu AI și cum, fără riscuri pentru clienți și secrete comerciale.'],
            ['icon' => 'file', 'title' => 'Politică de utilizare AI', 'text' => 'Reguli clare pentru angajați, aliniate cu GDPR și Regulamentul european privind AI (AI Act).'],
            ['icon' => 'users', 'title' => 'Instruire practică', 'text' => 'Workshop-uri pe exemple reale din activitatea echipei – la sediu sau online.'],
            ['icon' => 'trending', 'title' => 'Măsurare', 'text' => 'Urmărim timpul economisit și adopția, ca investiția să fie justificată.'],
        ],
        'faq' => [
            ['q' => 'Angajații folosesc deja ChatGPT pe cont personal. E o problemă?', 'a' => 'Poate fi. Pe conturile gratuite sau personale, datele introduse pot ajunge să fie folosite de furnizor și ies de sub controlul firmei. Recomandăm conturi de business și o politică clară despre ce date se pot folosi.'],
            ['q' => 'Ce este AI Act și ne afectează?', 'a' => 'Regulamentul european privind inteligența artificială introduce obligații în funcție de risc, inclusiv obligația ca personalul care folosește AI să aibă cunoștințe adecvate (AI literacy). Instruirile noastre acoperă și această cerință practică.'],
            ['q' => 'Faceți instruiri la sediul firmei?', 'a' => 'Da, în Mureș, Bistrița-Năsăud și Alba facem workshop-uri la sediu; oriunde altundeva, online. Adaptăm conținutul pe departamente: vânzări, marketing, contabilitate, administrativ.'],
        ],
        'body' => <<<'HTML'
<h2>AI-ul e deja în firma ta. Întrebarea e dacă e folosit bine.</h2>
<p>Mulți angajați folosesc deja instrumente AI – de multe ori pe conturi personale, fără reguli și fără să știe ce date au voie să introducă. Asta înseamnă atât <strong>productivitate pierdută</strong> (instrumente folosite superficial), cât și <strong>riscuri</strong> (date de clienți ieșite din firmă).</p>

<h2>Ce facem</h2>
<h3>1. Alegem instrumentele potrivite</h3>
<p>ChatGPT, Claude, Gemini, Microsoft Copilot – fiecare are puncte forte. Recomandăm varianta potrivită pentru ecosistemul tău (Microsoft 365 sau Google Workspace), bugetul și tipul de muncă.</p>
<h3>2. Le configurăm pentru firmă</h3>
<ul>
<li>Conturi de business, administrate central, unde datele nu sunt folosite pentru antrenare.</li>
<li>Asistenți și proiecte personalizate: tonul firmei, șabloane, documente de referință.</li>
<li>Integrare cu emailul, documentele și aplicațiile folosite zilnic.</li>
</ul>
<h3>3. Stabilim reguli clare</h3>
<p>O <strong>politică de utilizare AI</strong> scurtă și aplicabilă: ce date se pot folosi, cum se verifică rezultatele, cine răspunde. Aliniată cu GDPR și cu cerințele <strong>AI Act</strong>.</p>
<h3>4. Instruim echipa</h3>
<p>Workshop-uri practice, pe sarcinile reale ale fiecărui departament: emailuri și oferte, analiză de documente, rapoarte, marketing, suport clienți. Oamenii pleacă cu șabloane și obiceiuri noi, nu doar cu teorie.</p>

<h2>Următorul nivel: agenți AI</h2>
<p>După ce echipa folosește AI-ul cu încredere, pasul natural este automatizarea proceselor întregi cu <a href="/servicii/agenti-ai-software-personalizat">agenți AI personalizați</a> și <a href="/servicii/integrare-agenti-ai">integrarea lor în sistemele firmei</a>.</p>
HTML,
    ],
];
