<?php
declare(strict_types=1);

/**
 * Articole de pornire pentru blog (inclusiv articolul existent de pe vechiul site, păstrat la același URL).
 */
return [
    [
        'slug' => 'ce-faci-cand-hard-diskul-a-cedat',
        'title' => 'Hard diskul a cedat? 7 pași ca să nu pierzi definitiv datele',
        'category' => 'Recuperare date',
        'tags' => 'recuperare date, hard disk, ssd, backup',
        'published_at' => '2026-09-22 07:00:00',
        'excerpt' => 'Primele minute după ce un hard disk sau SSD cedează decid dacă datele mai pot fi salvate. Ce să faci – și mai ales ce să nu faci.',
        'faq' => [
            ['q' => 'Pot folosi un program gratuit de recuperare?', 'a' => 'Doar pentru ștergeri simple, pe un disc sănătos, și niciodată instalând programul pe discul afectat. Dacă discul face zgomote, e lent sau nu e recunoscut, orice program poate agrava situația.'],
            ['q' => 'Congelatorul ajută la un hard disk defect?', 'a' => 'Nu. Este un mit periculos: condensul poate distruge definitiv suprafața discului.'],
        ],
        'body' => <<<'HTML'
<p>Calculatorul nu mai pornește, hard diskul extern țăcăne, stick-ul cere să fie formatat. Pentru multe firme, asta înseamnă facturi, contracte, baze de date sau ani întregi de poze și documente. Vestea bună: <strong>în multe cazuri datele se pot recupera</strong>. Vestea mai puțin bună: primele minute contează enorm.</p>
<h2>1. Oprește imediat dispozitivul</h2>
<p>Dacă discul face zgomote (țăcănit, bâzâit, clicuri repetate), oprește-l. Fiecare pornire poate deteriora suplimentar suprafața magnetică. La SSD-uri, oprirea previne ștergerea automată a blocurilor eliberate (funcția TRIM).</p>
<h2>2. Nu mai scrie nimic pe el</h2>
<p>Fișierele șterse nu dispar imediat – spațiul lor e doar marcat ca liber. Orice fișier nou copiat, orice program instalat, chiar și navigarea pe internet pot suprascrie exact datele pe care vrei să le salvezi.</p>
<h2>3. Nu rula „reparări” automate</h2>
<p>CHKDSK, „Scanează și repară” sau formatarea sugerată de Windows pot modifica structura sistemului de fișiere și reduc șansele de recuperare. Apasă <strong>Anulează</strong>.</p>
<h2>4. Notează ce s-a întâmplat</h2>
<p>A căzut? A fost o pană de curent? S-a vărsat ceva? Ce fișiere sunt cele mai importante? Informațiile acestea ne ajută să alegem metoda corectă și să prioritizăm.</p>
<h2>5. Verifică dacă ai copii în altă parte</h2>
<p>OneDrive, Google Drive, Dropbox, emailuri trimise, un NAS, calculatorul unui coleg – uneori o parte importantă din date există deja în altă parte.</p>
<h2>6. Cere o evaluare profesională</h2>
<p>Un specialist face mai întâi o <strong>clonă</strong> a discului și lucrează pe copie, nu pe original. Așa, chiar dacă discul cedează complet în timpul procesului, datele extrase până atunci sunt în siguranță. La VITIM, <a href="/servicii/recuperare-date">evaluarea este gratuită</a>.</p>
<h2>7. După recuperare: backup care chiar funcționează</h2>
<p>Regula 3-2-1: <strong>trei copii</strong> ale datelor, pe <strong>două tipuri</strong> de medii, <strong>una în afara sediului</strong>. Și, cel mai important, testarea periodică a restaurării. Un backup netestat nu este un backup.</p>
<div class="callout">Ai o situație acum? Sună-ne și îți spunem, gratuit, ce șanse de recuperare ai și cum să procedezi.</div>
HTML,
    ],
    [
        'slug' => 'agenti-ai-pentru-imm-uri',
        'title' => 'Agenți AI pentru IMM-uri: ce pot face concret în 2026',
        'category' => 'AI',
        'tags' => 'agenti ai, automatizari, inteligenta artificiala, imm',
        'published_at' => '2026-09-15 07:00:00',
        'excerpt' => 'Dincolo de chatboți: cum preiau agenții AI sarcini reale într-o firmă mică sau mijlocie, cu exemple, costuri și riscurile de care trebuie să ții cont.',
        'faq' => [
            ['q' => 'Am nevoie de date multe ca să folosesc un agent AI?', 'a' => 'Nu. Modelele AI moderne sunt deja antrenate; agentul are nevoie doar de acces controlat la documentele și aplicațiile relevante pentru sarcina lui.'],
            ['q' => 'Un agent AI înlocuiește angajați?', 'a' => 'În practică, preia sarcinile repetitive și le lasă oamenilor partea care cere judecată, relație cu clientul și decizie. Cele mai bune rezultate apar când echipa lucrează împreună cu agentul.'],
        ],
        'body' => <<<'HTML'
<p>„Folosim și noi ChatGPT” e un început bun. Dar diferența reală de productivitate apare atunci când AI-ul nu doar răspunde la întrebări, ci <strong>face treabă</strong>: citește un email, caută în sistemele firmei, pregătește o ofertă și o pune în CRM. Asta este un <strong>agent AI</strong>.</p>
<h2>Chatbot vs. agent AI</h2>
<ul>
<li><strong>Chatbotul</strong> răspunde la întrebări, de obicei din informații generale.</li>
<li><strong>Agentul AI</strong> are acces controlat la instrumente – email, CRM, fișiere, baze de date – și poate executa pași, cu aprobare umană acolo unde contează.</li>
</ul>
<h2>5 exemple concrete pentru IMM-uri</h2>
<h3>1. Calificarea cererilor de ofertă</h3>
<p>Agentul citește cererile venite pe email sau din formular, extrage datele importante, pune întrebările lipsă și creează oportunitatea în CRM cu un rezumat clar.</p>
<h3>2. Oferte generate în câteva minute</h3>
<p>Pe baza listei de prețuri și a șabloanelor firmei, agentul pregătește oferta. Omul verifică, ajustează și trimite.</p>
<h3>3. Procesarea facturilor de la furnizori</h3>
<p>Datele din PDF-uri sunt extrase automat și pregătite pentru contabilitate, cu semnalarea diferențelor față de comandă.</p>
<h3>4. Asistent intern pentru proceduri</h3>
<p>Angajații noi întreabă agentul „cum se face X la noi?” și primesc răspunsul din procedurile interne, cu link către documentul sursă.</p>
<h3>5. Rapoarte automate</h3>
<p>În fiecare luni, un rezumat cu vânzările, lead-urile pe surse și campaniile – scris în limbaj natural, nu doar tabele.</p>
<h2>Ce trebuie să ai în vedere</h2>
<ul>
<li><strong>Datele:</strong> folosește variante de business ale modelelor AI, unde datele nu sunt folosite pentru antrenare.</li>
<li><strong>Controlul:</strong> acces minim necesar, aprobări pentru acțiunile sensibile, jurnal al acțiunilor.</li>
<li><strong>Calitatea:</strong> începe cu un proces limitat și măsoară rezultatele înainte de a extinde.</li>
<li><strong>Reglementări:</strong> GDPR și Regulamentul european privind AI (AI Act) cer transparență și instruirea oamenilor care folosesc AI.</li>
</ul>
<h2>Cum începi</h2>
<p>Alege un singur proces repetitiv, cu volum mare și reguli clare. Construiește un pilot în câteva săptămâni, măsoară timpul economisit, apoi extinde. Dacă vrei să vezi ce s-ar putea automatiza la tine, <a href="/servicii/agenti-ai-software-personalizat">hai să discutăm</a>.</p>
HTML,
    ],
    [
        'slug' => 'checklist-securitate-cibernetica-firme-mici',
        'title' => 'Checklist de securitate cibernetică pentru firme mici (10 pași)',
        'category' => 'Securitate',
        'tags' => 'securitate cibernetica, ransomware, phishing, backup, nis2',
        'published_at' => '2026-09-08 07:00:00',
        'excerpt' => 'Zece măsuri practice, cu cost mic sau zero, care reduc dramatic riscul de ransomware, phishing și furt de date într-o firmă mică.',
        'faq' => [
            ['q' => 'Antivirusul gratuit din Windows este suficient?', 'a' => 'Pentru o firmă, Microsoft Defender configurat corect este o bază bună, dar recomandăm o soluție administrată central (EDR), plus celelalte măsuri din listă. Antivirusul singur nu oprește phishingul sau parolele furate.'],
        ],
        'body' => <<<'HTML'
<p>Securitatea cibernetică nu înseamnă neapărat bugete mari. Majoritatea incidentelor din firmele mici au cauze simple: parole reutilizate, echipamente neactualizate, un atașament deschis din greșeală, un backup care nu funcționa. Iată <strong>10 pași</strong> pe care îi poți verifica chiar azi.</p>
<h2>Identități și acces</h2>
<ol>
<li><strong>Autentificare în doi pași (2FA)</strong> pe email, conturile bancare, contabilitate și orice acces de la distanță. E cea mai eficientă măsură, la cost zero.</li>
<li><strong>Manager de parole</strong> pentru echipă – fără parole în Excel sau pe post-it.</li>
<li><strong>Conturi separate</strong> pentru fiecare angajat și drepturi de administrator doar pentru cine are nevoie.</li>
</ol>
<h2>Echipamente</h2>
<ol start="4">
<li><strong>Actualizări automate</strong> pentru Windows, macOS, browsere și aplicații. Routerul și imprimantele au și ele firmware care trebuie actualizat.</li>
<li><strong>Protecție endpoint</strong> administrată central pe toate calculatoarele și serverele.</li>
<li><strong>Criptarea laptopurilor</strong> (BitLocker / FileVault) – un laptop pierdut nu mai înseamnă date pierdute.</li>
</ol>
<h2>Date</h2>
<ol start="7">
<li><strong>Backup 3-2-1</strong>, cu o copie imutabilă sau offline, și test de restaurare cel puțin trimestrial.</li>
<li><strong>Fișierele firmei într-un singur loc</strong> (server, OneDrive/SharePoint, Google Drive partajat), nu răspândite pe laptopuri.</li>
</ol>
<h2>Email și oameni</h2>
<ol start="9">
<li><strong>SPF, DKIM și DMARC</strong> configurate pe domeniu – îngreunează falsificarea emailurilor în numele tău.</li>
<li><strong>Instruire scurtă anti-phishing</strong> și o regulă simplă: orice cerere de plată sau schimbare de cont bancar se confirmă telefonic.</li>
</ol>
<h2>Și NIS2?</h2>
<p>Directiva NIS2 impune obligații suplimentare pentru entitățile din sectoarele esențiale și importante, iar clienții mari cer tot mai des aceleași standarde și de la furnizorii lor. Lista de mai sus este un punct de plecare bun în orice caz.</p>
<div class="callout">Vrei să afli unde stai? Facem un <a href="/servicii/securitate-cibernetica">audit de securitate</a> cu preț fix și îți dăm un plan clar, ordonat după impact.</div>
HTML,
    ],
    [
        'slug' => 'seo-mures-agentie-marketing-promovare',
        'title' => 'SEO și mintea umană: de ce ne bazăm pe Google?',
        'category' => 'SEO',
        'tags' => 'seo, psihologie, google, vizibilitate',
        'published_at' => '2022-08-09 10:00:00',
        'excerpt' => 'De ce aproape nimeni nu trece de prima pagină Google și ce înseamnă asta pentru afacerea ta.',
        'faq' => [],
        'body' => <<<'HTML'
<h2>Prima pagină Google este, practic, singura pagină</h2>
<p>Majoritatea oamenilor nu trec niciodată de prima pagină a rezultatelor Google. Nu e întâmplător: creierul nostru tinde să perceapă primele rezultate ca fiind cele mai de încredere și mai relevante. Dacă afacerea ta nu este acolo, riști să rămâi invizibil pentru clienți.</p>
<h2>SEO influențează comportamentul, nu doar algoritmul</h2>
<p><strong>SEO nu este doar o strategie digitală, ci o modalitate de a te afla exact acolo unde oamenii caută soluțiile pe care le oferi</strong>, în momentul în care au nevoie de ele.</p>
<p>Întrebare pentru tine: când ai căutat ultima oară ceva pe Google, ai trecut de prima pagină? Dacă nu, imaginează-ți că nici potențialii tăi clienți nu o fac.</p>
<h2>În 2026: și în răspunsurile AI</h2>
<p>Astăzi, pe lângă rezultatele clasice, Google afișează răspunsuri generate de AI, iar tot mai mulți oameni cer recomandări direct de la ChatGPT, Gemini sau Perplexity. Principiul rămâne același: <strong>cine e văzut primul, e ales primul</strong>. Doar că acum trebuie să fii și sursa pe care AI-ul o citează.</p>
<p>Hai să discutăm cum te putem ajuta să fii primul în mintea și în căutările clienților tăi – vezi <a href="/servicii/seo">serviciile noastre de SEO avansat</a>.</p>
HTML,
    ],
];
