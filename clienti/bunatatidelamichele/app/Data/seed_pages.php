<?php
declare(strict_types=1);

/**
 * Pagini de conținut. Variabilele {{firma}}, {{cui}}, {{reg_com}}, {{adresa}}, {{email}}, {{telefon}}, {{site}},
 * {{livrare_cost}}, {{livrare_timp}}, {{retur_zile}} sunt înlocuite automat cu datele din Setări.
 * Textele legale sunt un punct de plecare solid; recomandăm verificarea lor de către un jurist.
 */
return [
    [
        'slug' => 'despre-noi',
        'title' => 'Despre noi',
        'subtitle' => 'Pasiunea pentru cafea ne definește',
        'in_footer' => 0,
        'footer_group' => 'info',
        'sort' => 1,
        'meta_title' => 'Despre noi – importatorul cafelei Saka și Pareo în România',
        'meta_description' => 'Bunătăți de la Michele aduce în România cafea italiană autentică: suntem unicii importatori ai cafelei Saka și Pareo, prăjită tradițional la foc de lemn.',
        'body' => '<h2>Ceea ce iubim să facem</h2>
<p>De la selecția boabelor până la prăjire, fiecare pas contează pentru a-ți aduce o cafea de excepție.</p>
<p>La <strong>Bunătăți de la Michele</strong>, pasiunea noastră pentru gusturi autentice din Italia ne-a adus în România ca să împărtășim savoarea unei cafele cu adevărat speciale. Suntem mândri să fim <strong>unicii importatori ai cafelei Saka și Pareo</strong>, două branduri care duc mai departe tradiția italiană a cafelei de calitate.</p>
<p>Fie că preferi cafeaua boabe pentru un espresso intens sau pad-urile pentru un confort rapid, fiecare ceașcă pe care o savurezi aduce cu sine o bucată din Italia. Alegem doar cele mai bune produse, pentru ca tu să te bucuri de un moment de răsfăț autentic în fiecare zi.</p>
<p>La noi, cafeaua este mai mult decât o băutură – este o experiență care unește oamenii și creează momente de neuitat. Descoperă alături de noi bucuria unei cafele perfecte!</p>
<h2>Procesul nostru de lucru</h2>
<p>Lucrăm doar cu boabe de cafea verzi, atent alese, pe care le prăjim tradițional, la foc de lemn.</p>
<p>Totul a pornit de la dragostea pentru o ceașcă de cafea bine făcută. Selectăm boabe de calitate superioară, le prăjim tradițional și le aducem direct la tine acasă sau la birou, păstrând prospețimea și aroma intacte.</p>',
    ],
    [
        'slug' => 'termeni-si-conditii',
        'title' => 'Termeni și condiții',
        'subtitle' => 'Regulile de utilizare a magazinului online',
        'in_footer' => 1,
        'footer_group' => 'legal',
        'sort' => 10,
        'meta_title' => 'Termeni și condiții',
        'meta_description' => 'Termenii și condițiile de utilizare a magazinului online Bunătăți de la Michele: comenzi, prețuri, plată, livrare, retur, garanții și soluționarea litigiilor.',
        'body' => '<p>Ultima actualizare: {{data}}</p>
<h2>1. Informații despre vânzător</h2>
<p>Magazinul online {{site}} este administrat de <strong>{{firma}}</strong>, CUI {{cui}}, Nr. Reg. Com. {{reg_com}}, cu sediul în {{adresa}}. Ne poți contacta la <a href="mailto:{{email}}">{{email}}</a>{{telefon_text}}.</p>
<h2>2. Acceptarea termenilor</h2>
<p>Prin plasarea unei comenzi confirmi că ai citit, ai înțeles și accepți acești termeni și condiții, precum și <a href="/politica-de-confidentialitate">Politica de confidențialitate</a>. Pentru a comanda trebuie să ai cel puțin 18 ani.</p>
<h2>3. Produse și prețuri</h2>
<ul>
<li>Prețurile sunt afișate în lei (RON) și sunt prețuri finale. Costul livrării este afișat separat, în coș și la finalizarea comenzii, înainte de plată.</li>
<li>Fotografiile produselor sunt cu titlu de prezentare; ambalajul poate diferi ușor de imagine, fără a schimba conținutul produsului.</li>
<li>Pentru monodoze și cialde prețul afișat este per bucată, dacă nu este precizat altfel în pagina produsului.</li>
<li>Ne rezervăm dreptul de a corecta erorile evidente de preț sau de descriere. Dacă o comandă este afectată, te contactăm înainte de livrare și poți anula comanda fără costuri.</li>
</ul>
<h2>4. Comanda și încheierea contractului</h2>
<p>După plasarea comenzii primești un email de confirmare a înregistrării ei. Contractul se consideră încheiat în momentul în care îți confirmăm expedierea comenzii sau, pentru plata cu cardul, în momentul confirmării plății. Putem refuza sau anula o comandă în cazul în care produsul nu mai este disponibil, datele de livrare sunt incomplete sau există suspiciuni de fraudă; în acest caz, orice sumă încasată îți este returnată integral.</p>
<h2>5. Plata</h2>
<p>Metodele de plată disponibile sunt descrise în pagina <a href="/metode-de-plata">Metode de plată</a>: plata online cu cardul prin BT iPay (Banca Transilvania) și plata ramburs, la livrare. Pentru plata cu cardul, datele cardului sunt introduse exclusiv în pagina securizată a băncii; noi nu vedem și nu stocăm datele cardului tău.</p>
<h2>6. Livrarea</h2>
<p>Condițiile de livrare sunt descrise în pagina <a href="/livrare">Livrare</a>. Riscul de pierdere sau deteriorare a produselor se transferă către tine în momentul predării coletului.</p>
<h2>7. Dreptul de retragere și retururi</h2>
<p>Ai dreptul să te retragi din contract în termen de {{retur_zile}} zile, fără a invoca un motiv, conform OUG nr. 34/2014. Condițiile și excepțiile (de exemplu, produsele alimentare desigilate) sunt descrise în pagina <a href="/politica-de-retur">Politica de retur</a>.</p>
<h2>8. Garanții și conformitate</h2>
<p>Produsele beneficiază de garanția legală de conformitate, conform legislației în vigoare (Legea nr. 449/2003, OUG nr. 140/2021). Aparatele de cafea beneficiază de garanția comercială a producătorului, menționată în certificatul de garanție, acolo unde este cazul. Produsele alimentare au termenul de valabilitate înscris pe ambalaj.</p>
<h2>9. Răspundere</h2>
<p>Nu răspundem pentru întârzieri sau imposibilitatea de a livra cauzate de evenimente de forță majoră sau de informații greșite furnizate la comandă. Răspunderea noastră este limitată la valoarea comenzii.</p>
<h2>10. Proprietate intelectuală</h2>
<p>Conținutul site-ului (texte, imagini, logo, grafică) aparține {{firma}} sau partenerilor săi și nu poate fi copiat fără acord scris.</p>
<h2>11. Soluționarea litigiilor</h2>
<p>Orice neînțelegere o rezolvăm, în primul rând, pe cale amiabilă – scrie-ne la {{email}}. Dacă nu ajungem la o soluție, te poți adresa Autorității Naționale pentru Protecția Consumatorilor (<a href="https://anpc.ro" target="_blank" rel="noopener">anpc.ro</a>), procedurii de soluționare alternativă a litigiilor (<a href="https://anpc.ro/ce-este-sal/" target="_blank" rel="noopener">SAL</a>) sau platformei europene de soluționare online a litigiilor (<a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noopener">SOL</a>). Legea aplicabilă este legea română.</p>
<h2>12. Modificări</h2>
<p>Putem actualiza acești termeni. Pentru fiecare comandă se aplică versiunea în vigoare la data plasării ei.</p>',
    ],
    [
        'slug' => 'politica-de-confidentialitate',
        'title' => 'Politica de confidențialitate',
        'subtitle' => 'Cum folosim și protejăm datele tale personale (GDPR)',
        'in_footer' => 1,
        'footer_group' => 'legal',
        'sort' => 20,
        'meta_title' => 'Politica de confidențialitate (GDPR)',
        'meta_description' => 'Ce date personale colectăm în magazinul Bunătăți de la Michele, în ce scop, cât timp le păstrăm, cui le transmitem și ce drepturi ai conform GDPR.',
        'body' => '<p>Ultima actualizare: {{data}}</p>
<h2>1. Cine suntem</h2>
<p>Operatorul datelor este <strong>{{firma}}</strong>, CUI {{cui}}, cu sediul în {{adresa}}, email <a href="mailto:{{email}}">{{email}}</a>. Prelucrăm datele conform Regulamentului (UE) 2016/679 (GDPR) și legislației române aplicabile.</p>
<h2>2. Ce date colectăm și de ce</h2>
<ul>
<li><strong>Comenzi</strong>: nume, email, telefon, adresă de livrare și facturare, iar pentru firme denumirea și CUI. Le folosim ca să preluăm, să livrăm și să facturăm comanda (temei: executarea contractului și obligații legale fiscale).</li>
<li><strong>Plata cu cardul</strong>: plata este procesată de Banca Transilvania prin BT iPay. Noi primim doar confirmarea plății și, eventual, ultimele cifre ale cardului; nu avem acces la datele complete ale cardului.</li>
<li><strong>Mesaje prin formularul de contact</strong>: nume, email, telefon (opțional) și mesajul tău, pentru a-ți răspunde (temei: consimțământ / interes legitim).</li>
<li><strong>Date tehnice</strong>: adresa IP și tipul browserului, pentru securitate și prevenirea fraudelor (temei: interes legitim).</li>
<li><strong>Cookies de analiză și marketing</strong>: doar dacă îți dai acordul din bannerul de cookies. Detalii în <a href="/politica-cookies">Politica de cookies</a>.</li>
</ul>
<h2>3. Cui transmitem datele</h2>
<p>Doar partenerilor necesari pentru comanda ta: firma de curierat (nume, adresă, telefon), Banca Transilvania (pentru plata cu cardul), furnizorul de găzduire web și de email, contabilitatea, precum și autorităților, când legea o cere. Nu vindem datele tale.</p>
<h2>4. Cât timp păstrăm datele</h2>
<p>Documentele contabile (facturi și datele din ele) se păstrează 10 ani, conform legii contabilității. Datele din comenzi le păstrăm cât este necesar pentru garanție și eventuale reclamații, apoi le anonimizăm. Mesajele din formularul de contact le păstrăm cel mult 2 ani.</p>
<h2>5. Drepturile tale</h2>
<p>Ai dreptul de acces, rectificare, ștergere, restricționare, portabilitate și opoziție, precum și dreptul de a-ți retrage consimțământul în orice moment. Scrie-ne la {{email}} și îți răspundem în cel mult 30 de zile. Ai și dreptul de a depune o plângere la Autoritatea Națională de Supraveghere a Prelucrării Datelor cu Caracter Personal (<a href="https://www.dataprotection.ro" target="_blank" rel="noopener">dataprotection.ro</a>).</p>
<h2>6. Securitate</h2>
<p>Site-ul folosește conexiune criptată (HTTPS). Accesul la datele comenzilor este restricționat și protejat prin parolă și autentificare în doi pași.</p>',
    ],
    [
        'slug' => 'politica-cookies',
        'title' => 'Politica de cookies',
        'subtitle' => 'Ce sunt cookie-urile și cum le folosim',
        'in_footer' => 1,
        'footer_group' => 'legal',
        'sort' => 30,
        'meta_title' => 'Politica de cookies',
        'meta_description' => 'Ce cookie-uri folosește magazinul Bunătăți de la Michele, la ce servesc și cum îți poți modifica oricând preferințele.',
        'body' => '<p>Cookie-urile sunt fișiere mici salvate în browser, care ajută site-ul să funcționeze și să înțelegem cum este folosit.</p>
<h2>Cookie-uri necesare (mereu active)</h2>
<ul>
<li><strong>bdm_cart</strong> – ține minte produsele din coșul tău (30 de zile);</li>
<li><strong>bdm_consent</strong> – ține minte alegerea ta privind cookie-urile (6 luni);</li>
<li><strong>bdm_admin</strong> – doar pentru administratorii magazinului, sesiunea din panoul de control.</li>
</ul>
<h2>Cookie-uri de analiză și marketing (doar cu acordul tău)</h2>
<p>Dacă sunt activate de administrator și doar după ce accepți din banner: Google Analytics / Google Tag Manager (statistici de trafic), Google Ads și Meta Pixel (măsurarea campaniilor), Microsoft Clarity (îmbunătățirea experienței). Folosim Google Consent Mode v2: fără acordul tău, aceste servicii nu salvează cookie-uri.</p>
<h2>Cum îți schimbi preferințele</h2>
<p>Poți modifica oricând alegerea din linkul „Setări cookies” din subsolul site-ului sau poți șterge cookie-urile din setările browserului.</p>',
    ],
    [
        'slug' => 'livrare',
        'title' => 'Livrare',
        'subtitle' => 'Livrăm prin curier rapid în toată România',
        'in_footer' => 1,
        'footer_group' => 'info',
        'sort' => 40,
        'meta_title' => 'Livrare prin curier în toată România',
        'meta_description' => 'Livrăm comenzile prin curier rapid în toată România, în 1–3 zile lucrătoare. Vezi costul livrării, cum urmărești coletul și ce faci dacă lipsești.',
        'body' => '<h2>Cât costă și cât durează</h2>
<ul>
<li><strong>Livrare prin curier rapid</strong>: {{livrare_cost}} pe comandă, oriunde în România.</li>
<li><strong>Termen de livrare</strong>: {{livrare_timp}} de la confirmarea comenzii (pentru plata cu cardul, de la confirmarea plății).</li>
</ul>
<p>Costul exact al livrării îl vezi în coș și la finalizarea comenzii, înainte de plată.</p>
<h2>Urmărirea coletului</h2>
<p>Când predăm coletul curierului, primești un email cu numărul de urmărire (AWB). Starea comenzii o poți verifica oricând în pagina <a href="/urmarire-comanda">Urmărire comandă</a>.</p>
<h2>La primirea coletului</h2>
<p>Te rugăm să verifici coletul în prezența curierului. Dacă ambalajul este deteriorat, cere întocmirea unui proces-verbal și anunță-ne la {{email}} în aceeași zi.</p>
<h2>Dacă nu ești acasă</h2>
<p>Curierul te sună înainte de livrare. Dacă nu te găsește, reîncearcă livrarea sau lasă un aviz. Coletele nerevendicate se întorc la noi; pentru comenzile plătite cu cardul îți returnăm contravaloarea produselor.</p>',
    ],
    [
        'slug' => 'politica-de-retur',
        'title' => 'Politica de retur',
        'subtitle' => 'Dreptul de retragere în {{retur_zile}} zile',
        'in_footer' => 1,
        'footer_group' => 'info',
        'sort' => 50,
        'meta_title' => 'Politica de retur – retragere în 14 zile',
        'meta_description' => 'Cum returnezi un produs cumpărat de la Bunătăți de la Michele: dreptul de retragere în 14 zile, condiții, excepții, formular și rambursarea banilor.',
        'body' => '<h2>Dreptul de retragere</h2>
<p>Conform OUG nr. 34/2014, ai dreptul să te retragi din contract în termen de <strong>{{retur_zile}} zile calendaristice</strong> de la primirea produselor, fără să invoci un motiv.</p>
<h2>Condiții</h2>
<ul>
<li>Produsele alimentare (cafea boabe, cialde, monodoze) pot fi returnate doar <strong>nedeschise, sigilate și în ambalajul original</strong>. Din motive de igienă și protecție a sănătății, produsele desigilate după livrare nu pot fi returnate (art. 16 lit. e din OUG nr. 34/2014).</li>
<li>Aparatele de cafea se returnează în starea în care au fost primite, cu toate accesoriile și documentele, în ambalajul original.</li>
<li>Costul direct al returnării produselor este suportat de client, cu excepția produselor livrate greșit sau neconforme.</li>
</ul>
<h2>Cum returnezi</h2>
<ol>
<li>Anunță-ne la <a href="mailto:{{email}}">{{email}}</a> în termenul de {{retur_zile}} zile, menționând numărul comenzii și produsele returnate. Poți folosi modelul de mai jos.</li>
<li>Trimite produsele în cel mult 14 zile de la notificare, la adresa pe care ți-o comunicăm.</li>
<li>Îți returnăm banii (inclusiv costul livrării standard) în cel mult 14 zile de la primirea notificării, pe același card folosit la plată sau prin transfer bancar în contul indicat de tine. Putem amâna rambursarea până primim produsele înapoi.</li>
</ol>
<h2>Model de formular de retragere</h2>
<blockquote><p>Către {{firma}}, {{adresa}}, {{email}}:<br>Vă informez prin prezenta cu privire la retragerea mea din contractul referitor la vânzarea următoarelor produse: ……<br>Comanda nr.: …… · Data primirii: ……<br>Numele consumatorului: ……<br>Adresa consumatorului: ……<br>IBAN pentru rambursare (dacă nu ați plătit cu cardul): ……<br>Data: ……</p></blockquote>
<h2>Produse neconforme sau deteriorate</h2>
<p>Dacă ai primit un produs greșit, deteriorat sau neconform, scrie-ne cu câteva fotografii și îl înlocuim sau îți returnăm banii, fără costuri pentru tine.</p>',
    ],
    [
        'slug' => 'metode-de-plata',
        'title' => 'Metode de plată',
        'subtitle' => 'Plătește online cu cardul, în siguranță, sau ramburs',
        'in_footer' => 1,
        'footer_group' => 'info',
        'sort' => 60,
        'meta_title' => 'Metode de plată – card online (BT iPay) sau ramburs',
        'meta_description' => 'Plătește comanda online cu cardul prin BT iPay (Banca Transilvania), cu 3D Secure, sau ramburs la livrare. Află cum funcționează plata securizată.',
        'body' => '<h2>Plata online cu cardul – BT iPay</h2>
<p>Plățile cu cardul sunt procesate de <strong>Banca Transilvania</strong>, prin serviciul <strong>BT iPay</strong>. Acceptăm carduri <strong>Visa, Mastercard și Maestro</strong>, emise de orice bancă.</p>
<ol>
<li>La finalizarea comenzii alegi „Plată online cu cardul” și apeși „Plasează comanda”.</li>
<li>Ești redirecționat în pagina securizată a Băncii Transilvania, unde introduci datele cardului.</li>
<li>Confirmi plata prin 3D Secure (cod primit prin SMS sau în aplicația băncii tale).</li>
<li>Revii automat pe site, unde vezi confirmarea plății, și primești emailul de confirmare.</li>
</ol>
<p><strong>Siguranța datelor</strong>: datele cardului sunt introduse exclusiv în pagina băncii, protejată prin criptare și standardul PCI DSS. {{brand}} nu are acces la numărul cardului, data expirării sau codul CVV și nu le stochează.</p>
<p>Moneda tranzacției este leul românesc (RON). Dacă plata nu reușește, poți încerca din nou din pagina comenzii sau poți alege plata ramburs.</p>
<h2>Plata ramburs</h2>
<p>Plătești curierului, la livrare, numerar sau cu cardul (în funcție de curier).</p>
<h2>Rambursări</h2>
<p>În cazul unui retur sau al anulării comenzii, suma plătită cu cardul se returnează pe același card, de regulă în 3–10 zile lucrătoare, în funcție de banca emitentă.</p>',
    ],
];
