# Istoric versiuni

## 1.7.1

- Pagina „Despre noi” nu mai are secțiune de echipă și site-ul nu mai folosește nume de persoane. Prezentarea rămâne la nivel de firmă: cine este VITIM, cum lucrăm, „Om, nu robot”.
- Emailurile automate și asistentul de pe site semnează „VITIM”, fără „Echipa VITIM”.
- **Actualizare:** setările echipei (nume, roluri, fotografii) se șterg automat din baza de date. Dacă ai personalizat răspunsul automat din Setări → Email și semnează „Echipa VITIM”, schimbă textul de acolo.

## 1.7.0 — repoziționare: departamentul extern de IT & AI

- **Prima pagină, refăcută ca ofertă comercială clară:** „Departamentul extern de IT & AI al firmei tale”, abonamente pentru companii de la 1.000 RON/lună, butoanele „Solicită o evaluare” și „Vezi cum lucrăm”.
- Secțiuni noi pe prima pagină:
  - „Un singur partener pentru tehnologia firmei tale” (problemele tipice și schema care le leagă);
  - cele trei direcții (IT & securitate, VITIM AI & automatizări, Web & creștere digitală), cu legături spre toate paginile de servicii;
  - „Un singur număr pentru tehnologia firmei tale”;
  - abonamente cu mini configurator (calculatoare, locații, servicii), care completează singur cererea de evaluare;
  - „Om, nu robot”;
  - îndemnul final.
- **Proiecte reale** în formatul problemă → soluția VITIM → ce am implementat: HIKeVET, LocalMureș, Podreg, Autohouse Westcar, Optoplus, Argento Metal, FerestrePartner.ro, SC Profil, Optofarm, Dental Arena. Primele patru apar pe prima pagină, toate sunt pe `/proiecte`, iar numele clienților apar în „Companii care au lucrat cu VITIM”. Fără cifre inventate; rezultatele se adaugă din panou doar când sunt confirmate de client.
- **Despre noi:** mesajul „Înainte să ne dai acces la infrastructura și datele companiei tale, vrem să știi cine suntem” și filosofia „Om, nu robot”.
- **Formular de contact mai scurt:** nume, telefon sau email, „Cu ce te putem ajuta?” și butonul „Vreau să discutăm”. Detaliile despre firmă (angajați, calculatoare, servicii, buget, județ) sunt opționale.
- **Pagina VITIM AI** are schema „Ce poate face VITIM AI?” (client → canale → VITIM AI → CRM / ERP / WooCommerce / calendar / documente → acțiune) și patru fluxuri exemplu.
- Paginile de servicii și toate adresele rămân aceleași. Paginile de servicii trimit acum spre abonamente, iar meniul are „Abonamente” și butonul „Solicită o evaluare”.
- **Actualizare:** textele vechi ale primei pagini și ale paginii „Despre noi” se păstrează în setarea `backup_texte_v16`. În Panou → Setări → Prima pagină se pot edita prețul „de la” și nota de preț. La proiecte se pot edita problema, soluția, ce am implementat, eticheta, logo-ul și opțiunea „Pe prima pagină”.

## 1.6.0 — design reîmprospătat

- O singură culoare de accent (albastru, mai calm) în loc de gradientul albastru-violet; butoanele, chatul și iconițele o folosesc unitar.
- Etichete mai lizibile (fără majuscule mono), titluri echilibrate pe rânduri, paragrafe limitate la o lățime comodă de citit, cifre aliniate.
- Secțiunile cu beneficii („De ce VITIM”, „Despre noi”, „VITIM AI”) au o grilă variată, nu trei carduri identice pe rând.
- Butoanele reacționează la apăsare; animațiile se opresc pentru cine a cerut asta în sistem (reducerea mișcării); o textură fină pe fundal.

## 1.5.0

- **Pagină nouă: „VITIM AI”** (`/vitim-ai`, în meniul principal și pe mobil): ce este platforma, ce primește firma (asistent AI pe site 24/7, chat live, contacte și cereri, campanii email / SMS / WhatsApp, automatizări, editor de email, formulare, magazin online, analiză, site administrat, conformitate GDPR și cookie-uri, echipă cu roluri), cum o configurăm și o administrăm noi, de ce e unică, demo-ul pe site-ul clientului și întrebări frecvente.
- Date structurate pentru Google (produs software + întrebări frecvente), pagina e în sitemap și în `llms.txt`. Pe prima pagină, secțiunea despre agenți AI trimite la noua pagină.

## 1.4.0

- **„Testează un agent AI pentru firma ta”** pe prima pagină. Vizitatorul scrie adresa site-ului său și emailul, apoi apasă „Creează demo AI”. Cererea intră în CRM ca oportunitate „Demo agent AI” și primești notificare pe email, ca la formularul de contact. Butonul „Testează pe site-ul tău” din secțiunea despre agenți AI duce direct la formular.
- Adresa e verificată: se acceptă `firma.ro`, `www.firma.ro` sau un link complet, nu se acceptă IP-uri sau adrese locale. Limită: 4 cereri la 10 minute de pe același IP, plus aceleași protecții anti-spam ca la contact.
- Deocamdată demo-ul îl pregătește echipa VITIM; în VITIM AI, același formular va genera demo-ul automat.

## 1.3.0

- **Backup zilnic automat al bazei de date, trimis pe email.** O dată pe zi, arhiva bazei de date pleacă pe adresa din Setări → Avansat → „Email pentru backup” (implicit emailul pentru notificări). Așa există mereu o copie în afara serverului. Funcționează și fără cron: se declanșează după o vizită pe site, fără să încetinească pagina.
- Arhivele mai mari de 15 MB nu se atașează; emailul anunță că trebuie descărcate din Panou → Sistem.

## 1.2.1

- Recuperare acces: dacă baza de date nu are niciun administrator (de exemplu după ce a fost refăcută de la zero), pagina `/install/` permite crearea unui cont nou de administrator. Pentru siguranță, se cere parola bazei de date din `app/config.php`.

## 1.2.0 — telefoane, tablete, plăci de bază și site mai rezistent

- **Serviciu nou: Reparații plăci de bază (nivel componentă)**: microsoldering, scurtcircuite, lichid vărsat, cipuri/BGA, pentru laptopuri, PC-uri, telefoane, tablete și console. Apare pe prima pagină ca serviciu evidențiat.
- **Serviciu nou: Reparații telefoane și tablete**: ecran, baterie, încărcare, apă, placă de bază, software.
- Fotografii noi pentru cele două servicii; „Reparații IT” are legături către ele; descrierea SEO, llms.txt și întrebările sugerate de asistent includ noile servicii.
- **Dacă baza de date nu răspunde** (server MySQL oprit, parolă schimbată, limită de conexiuni la hosting), site-ul nu mai afișează o pagină de eroare generică: vizitatorii primesc ultima versiune salvată a paginii sau o pagină cu butoane Sună / WhatsApp / Email (cod 503, fără penalizare în Google).
- Paginile de eroare afișează un cod scurt, găsit și în `storage/logs/app.log`, ca problema să fie identificată rapid.

## 1.1.1

- Animația „agentului” (consola cu activitatea unei zile) revine pe prima pagină ca variantă implicită.
- Setări → Aspect → „Prima pagină: element din dreapta titlului”: alegi între animație și fotografie.
- Cardurile plutitoare din jurul animației nu mai acoperă textul.

## 1.1.0 — asistent AI și fotografii

- **Asistent virtual pe site**: răspunde vizitatorilor 24/7 din serviciile, zonele, întrebările frecvente și articolele site-ului. Când cineva vrea ofertă, cere acordul, salvează cererea în CRM (sursa „Asistent AI”) și trimite notificare pe email. Conversațiile se văd în Panou → Asistent AI.
- Setări noi în Setări → Asistent AI: cheie API (salvată criptat), model, nume, mesaj de întâmpinare, întrebări sugerate, instrucțiuni suplimentare, limite anti-abuz.
- 20 de fotografii profesionale (licență gratuită Unsplash/Pexels), optimizate WebP, pe prima pagină, pe servicii și pe articole. Se pot înlocui oricând din panou cu poze proprii.
- Prima pagină are o fotografie reală în loc de consola animată; titlurile nu mai folosesc gradient peste tot, ca să arate mai sobru.
- Setări → Aspect: fotografie pentru prima pagină și pentru pagina Despre noi.
- Politica de confidențialitate are o secțiune despre asistentul virtual; conversațiile mai vechi de 12 luni se șterg automat (cron).

## 1.0.0 — prima versiune

**Site public**
- Design nou 2026: temă întunecată/luminoasă, animații discrete, optimizat pentru mobil (bară de acțiuni rapide: Sună / WhatsApp / Ofertă).
- 15 pagini de servicii (IT, securitate, recuperări de date, SEO, marketing tehnic, Google Ads, Meta Ads, Google Workspace, site-uri, agenți AI, integrare AI, automatizări, AI pentru firme).
- Pagini locale pentru județele Mureș, Bistrița-Năsăud, Alba și 16 localități.
- Blog cu cuprins automat, articole programate, RSS.
- Formular de ofertă cu urmărirea sursei (Google Ads, Meta, organic, AI), anti-spam fără captcha, răspuns automat.
- Pagini legale: confidențialitate (GDPR), cookies, termeni; banner de cookies cu Google Consent Mode v2.

**SEO avansat**
- Date structurate schema.org (LocalBusiness, Service, FAQPage, BlogPosting, BreadcrumbList).
- Sitemap XML cu imagini, robots.txt, llms.txt pentru motoarele AI, IndexNow, imagini Open Graph generate automat.
- Cache de pagini (răspuns în câteva milisecunde), imagini WebP responsive.
- Redirecționări 301 pentru toate adresele vechi ale site-ului WordPress; 410 pentru conținutul demo.
- Audit SEO automat în panou, jurnal 404, redirecționări automate la schimbarea adresei unei pagini.

**Panou de control**
- Conținut: servicii, zone, blog, pagini, proiecte, testimoniale, media (conversie automată WebP).
- CRM: pipeline Kanban, contacte cu istoric, sarcini, email din fișa clientului, import/export CSV, ștergere GDPR.
- Email marketing: campanii cu segmentare (abonați, clienți, județ, etichete), trimitere în loturi, programare, rapoarte deschideri/click-uri, dezabonare cu un click, double opt-in.
- Securitate: autentificare în doi pași (TOTP), roluri, protecție CSRF, limitare încercări, parole SMTP criptate.
- Sistem: actualizare din arhivă .zip cu backup automat, backup-uri (bază de date, imagini, cod), cron.
