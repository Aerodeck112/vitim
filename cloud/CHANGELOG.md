# VITIM AI Cloud — versiuni

## 0.19.0 — noutățile VITIM, direct în panoul tău

- **„Noutăți VITIM”** în meniul panoului: toate anunțurile noastre despre platformă, într-un singur loc, ca să le găsești oricând.
- **Ce am făcut pentru firma ta**: emailurile noastre includ acum lucrările făcute pe site-urile tale de la ultimul mesaj (securitate, actualizări, SEO, backup).
- **Rezumatul lunar**: la începutul fiecărei luni primești pe scurt ce a făcut VITIM AI pentru firma ta (conversații pe site, cereri noi, abonați noi, mesaje trimise), lucrările noastre și linkul spre raportul lunar.
- Nu mai vrei aceste emailuri? Fiecare are un link de dezabonare; emailurile importante despre cont continuă.

**Actualizare:** în Admin → „Noutăți către clienți”: anunțuri cu previzualizare pe orice client, test, programare, public pe servicii și roluri, livrări și deschideri; bifele „Trimite automat noutățile fiecărei versiuni” (ciorna din CHANGELOG, trimisă a doua zi la 10:00) și „Rezumatul lunar automat”. Trimiterea folosește emailul platformei (MAIL_* din .env), câte 40 de emailuri la 5 minute. Rândurile „**Actualizare:**” din CHANGELOG nu ajung la clienți.

## 0.18.0 — cookie-uri pe site-urile clienților (plugin 1.6.0)

- **Meniu nou „Cookie-uri”** în panoul firmei: bannerul de consimțământ pentru fiecare site, conform Legii 506/2004 și GDPR. Se activează dintr-o bifă.
- **Bannerul**: bandă jos sau casetă în colț, în culorile firmei, cu „Accept toate”, „Refuz toate” și „Setări” la fel de vizibile. În setări, vizitatorul alege pe categorii (strict necesare, preferințe, statistici, marketing) și vede fiecare cookie: serviciu, furnizor, durată, scop. Un buton mic 🍪 și orice link `#vitim-cookies` redeschid setările.
- **Lista cookie-urilor se face singură**: cookie-urile VITIM (chat, formulare, recunoașterea abonaților), WordPress și WooCommerce, plus serviciile bifate de firmă (Google Analytics, Google Ads, Meta Pixel, TikTok, LinkedIn, Hotjar, Clarity, YouTube, Google Maps, reCAPTCHA) sau adăugate de mână.
- **Nimic nu pornește fără acord**: Google Consent Mode v2 (pluginul îl pune singur în `<head>`), scripturile și iframe-urile marcate `data-vitim-consent` așteaptă alegerea, iar la retragerea acordului cookie-urile categoriei se șterg.
- **Registrul consimțămintelor**: fiecare alegere se păstrează ca dovadă (când, ce, pe ce pagină, pentru ce versiune a politicii; IP-ul doar criptografic), cu statistici și export CSV pentru un control. „Întreabă din nou toți vizitatorii” după ce adaugi un serviciu nou.
- **Politica de cookie-uri** completată automat: `[vitim_cookies]` în WordPress sau `<div data-vitim-cookie-policy></div>` pe orice site.
- Marketingul VITIM respectă alegerea: recunoașterea abonaților (cookie-ul `vitim_ct`) și urmărirea produselor văzute / coșului în magazin doar cu acord de marketing; formularele de abonare apar după ce vizitatorul a ales.

**Actualizare:** pluginul VITIM Connector 1.6.0 pe site-urile WordPress (se oferă automat în Module).

## 0.17.0 — marketing complet: automatizări, editor vizual, formulare, magazin, analiză (plugin 1.5.0)

Tot ce trebuie ca o firmă să-și facă singură marketingul, ca în Klaviyo, din panoul VITIM. Meniu nou: **Automatizări**, **Audiență**, **Analiză**; în Campanii, filele Formulare, Brand și Canale.

**Audiență și urmărire**
- **Activitatea fiecărui contact** pe fișa lui: emailuri primite / deschise / click-uri, SMS, abonări, formulare, cereri, produse văzute, coșuri, comenzi.
- **Deschideri și click-uri** în fiecare email (campanii și automatizări), pe destinatar.
- **Liste** (statice) și **segmente** (dinamice, se actualizează singure): „a deschis în ultimele 30 de zile”, „are acord SMS”, „a cheltuit peste 500 lei”, „nu e în lista X”, „risc de pierdere mare” etc. Campaniile se trimit către liste / segmente, cu excluderi.

**Automatizări (fluxuri)**
- Pornesc la un eveniment (abonare, cerere, comandă începută, comandă, produs văzut), la intrarea într-o listă sau într-un segment ori la ziua de naștere.
- Pași: așteptare, email, SMS, WhatsApp, condiție da / nu, adăugare în listă. Ies singure la comandă; „smart sending” (nu trimite dacă a primit alt mesaj în ultimele 16 ore); SMS / WhatsApp doar între 9 și 20.
- 7 șabloane gata făcute: Bun venit, Follow-up după cerere, Coș abandonat, Produs văzut, După comandă, Recâștigare clienți inactivi, La mulți ani.

**Editor vizual de email**
- Trage blocurile în email: logo, titlu, text, imagine, buton, două coloane, produs (din catalog), linie, spațiu, rețele sociale, **produsele din coș** (automat). Previzualizare live pe calculator și telefon, încărcare imagini, 5 designuri gata făcute și designuri salvate de firmă. Merge și pentru emailurile din automatizări.
- **Brand**: logo, culoare, font și linkuri, aplicate automat în toate emailurile.

**Formulare de abonare pe site**
- Popup, casetă în colț (flyout), bară sus / jos, formular în pagină. Apar după câteva secunde, la derulare sau când vizitatorul vrea să plece; pe ce pagini și dispozitive vrei; nu mai apar celor abonați.
- **Dublă confirmare** prin emailul firmei (recomandat), cod de reducere după abonare, bifă separată pentru SMS, dovada acordului păstrată. Abonații intră în listă și pornesc fluxul „Bun venit”. Afișări, înscrieri și rata de conversie pe fiecare formular.

**Magazin WooCommerce (pluginul VITIM Connector 1.5.0)**
- Produse văzute, adăugări în coș, comenzi începute și comenzi plasate ajung în profilul clientului; catalogul de produse se sincronizează; istoricul comenzilor din ultimele 12 luni se importă o dată.
- **Coș abandonat** cu produsele, prețurile și un link care **reface coșul** dintr-un click; bifă „Vreau oferte pe email” la finalizarea comenzii (checkout clasic și pe blocuri).
- Clienții veniți din emailuri sunt recunoscuți pe site. **Veniturile se atribuie** emailului cu click (sau deschis) în ultimele 5 zile și apar la campanii, automatizări și în Analiză.

**Analiză și optimizare**
- Pagina **Analiză**: venitul adus de marketing față de total, pe zile; emailuri trimise, deschideri, click-uri; abonați noi; campaniile și automatizările cu venitul lor.
- **Test A/B** pe subiect: o parte primește A, o parte B, iar câștigătoarea pleacă automat la restul.
- **Ora recomandată** de trimitere, din orele la care abonații deschid emailurile, cu programare dintr-un click.
- **Predicții** pe fiecare client (recalculate noaptea): valoare estimată, data probabilă a următoarei comenzi, risc de pierdere; se pot folosi în segmente.

**Actualizare:** după instalarea zip-ului, actualizează pluginul pe site-urile WordPress la 1.5.0 (se oferă automat în Module).

## 0.16.0 — campanii pe email, SMS și WhatsApp

- **Campanii** (meniu nou în panoul clientului): email, SMS și WhatsApp, trimise **din conturile firmei**: emailul ei (SMTP, de exemplu din cPanel), contul ei SMSLink.ro și numărul ei de WhatsApp Business (API-ul oficial Meta). Costurile le plătește direct firma.
- **Canale de trimitere** (Campanii → Canale): conectarea conturilor, cu buton de test. Parolele și tokenurile sunt criptate și nu se mai afișează după salvare.
- Fluxul unei campanii: ciornă → **previzualizare** (exact cum arată, cu numele unui contact) → **test către tine** → **aprobare** (acum sau la o oră aleasă) → trimitere în tranșe → **statistici** pe destinatar (trimis, eșuat, exclus și de ce, dezabonat; pe WhatsApp și livrat / citit).
- **Doar contactele cu acord de marketing** pe acel canal primesc campania (GDPR, Legea 506/2004); acordul se verifică și la aprobare, și la trimiterea fiecărui mesaj. Publicul se poate restrânge după sursa contactului, cererile trimise și data adăugării.
- **Personalizare** cu {{prenume}}, {{nume}}, {{firma}}. În email: **îngroșat**, linkuri, datele firmei (denumire, CUI) și linkul de dezabonare adăugate automat, plus dezabonarea dintr-un click din Gmail / Outlook.
- **SMS**: numărul de caractere și de SMS-uri pe destinatar, opțiune fără diacritice (160 de caractere în loc de 70), link scurt de dezabonare.
- **WhatsApp**: șabloane aprobate de Meta, cu variabile; webhook pentru statusurile de livrare și pentru răspunsurile „STOP”, care dezabonează automat.
- **Dezabonare**: pagina de dezabonare retrage acordul (rămâne în istoricul contactului) și pune adresa pe lista de suprimare. Un import ulterior nu o mai readuce.
- **Limita pe oră a emailului** (hostingul limitează trimiterile): campaniile mari se împart automat pe ore.
- **Oprire automată** după 3 erori la rând (parolă schimbată, credit SMS epuizat), cu motivul afișat. Campania se poate opri, continua sau anula oricând.
- **Import de contacte din Excel / CSV** (Contacte → Import): completează contactele existente fără dubluri și înregistrează acordul de marketing doar dacă declari sursa lui.
- Permisiune nouă: „campanii”, pentru proprietar și administratori.

## 0.15.0 — invitații retrimise și clienții pe servicii

- **Invitațiile sunt valabile 7 zile** (înainte: 60 de minute, ca linkul de „Am uitat parola”). Emailul nou spune la ce firmă primește acces și până când e valabil linkul. Linkul de „Am uitat parola” rămâne la 60 de minute.
- **„Retrimite invitația”**: în fișa clientului (Admin), lângă fiecare utilizator care nu și-a setat parola, cu starea invitației („trimisă, valabilă până la…” / „expirată”). Același buton îl are și proprietarul firmei în Setări → Utilizatori, pentru colegii lui.
- Dacă invitația a expirat, mesajul de pe pagina de setare a parolei spune ce e de făcut.
- **Clienții se filtrează după servicii** în lista din Admin: Mentenanță, SEO, Google Ads, Google Business Profile, Fără servicii (cu numărul de clienți la fiecare), iar tabelul are coloana „Servicii”.

## 0.14.0 — SEO reparat la buton (plugin 1.4.0)

- **„Repară” pe fiecare problemă SEO** din audit, pe site-urile WordPress cu pluginul 1.4.0, plus un buton **„Repară tot automat”** care le rezolvă pe toate deodată. După reparare, auditul se reface imediat și problemele trec la „Rezolvate recent”.
- Ce se repară automat: descrierea paginilor, canonical și Open Graph (titlu + imagine la distribuire pe Facebook / WhatsApp), titlul paginilor, robots.txt, sitemap, redirecționarea spre HTTPS (doar dacă certificatul merge), www / fără www, limba paginii, viewport pentru telefon, textul alternativ al imaginilor (completat și în biblioteca media), date structurate cu numele firmei, „Permite indexarea”, plus antetele de securitate și compresia gzip.
- Dacă site-ul are deja Yoast / Rank Math / All in One SEO, pluginul nu dublează descrierile și spune unde se completează.
- **Fiecare reparare apare în „Lucrări VITIM” la categoria SEO**, deci clientul o vede în raportul lunar. Se trece doar ce s-a aplicat efectiv (de exemplu, HTTPS fără certificat nu).
- Totul e reversibil: în WordPress → Setări → VITIM se vede lista reparărilor active și butonul „Oprește remedierile SEO”. Fișierele modificate (robots.txt, .htaccess) au copii de siguranță.
- Rămân manuale (cu „Cum rezolvi”): H1 lipsă sau dublu, titluri duplicate, pagini cu eroare, problemele legale și cele de pe site-urile care nu sunt WordPress.

## 0.13.0 — agentul funcționează și fără Claude; pagina agentului se salvează corect

- **Agent fără AI extern (gratuit)**: dacă pe server nu e pusă cheia Claude, agentul răspunde din **„Informații despre firmă”**: istoria firmei, servicii, prețuri, program, adresă, întrebări frecvente. Caută propoziția sau secțiunea potrivită (fără diacritice, cu forme ca preț / prețul / prețurile și sinonime ca „cât costă” → prețuri, „unde sunteți” → adresă, „de când existați” → istoria firmei).
- Tot fără AI: **salvează lead-uri** (ia numele, telefonul sau emailul din mesaj, cere acordul explicit cu „Da”, apoi trimite emailul către echipă), **anunță un om** când vizitatorul cere sau are o reclamație, răspunde la salut / mulțumesc, iar când nu știe cere datele de contact și spune la ce poate ajuta. Respectă tonul (formal = „dumneavoastră”), datele cerute pentru lead și regulile din pagina agentului.
- Setare nouă **„Cum răspunde agentul”**: Automat (Claude dacă e cheia, altfel din informațiile firmei; și dacă Claude nu răspunde la un moment dat), Doar din informațiile firmei, Doar Claude. Răspunsurile fără AI nu consumă din plafonul de cost.
- Câmpul „Informații despre firmă” acceptă acum până la 60.000 de caractere, cu numărător.
- **Reparat: pagina agentului nu salva.** Avea două formulare cu două butoane (modificările dintr-o parte se pierdeau la apăsarea celuilalt buton), iar câmpurile „Limbă implicită” sau „Limbi” lăsate goale blocau salvarea. Acum e **un singur buton „Salvează”** (fix jos, cu avertisment „Ai modificări nesalvate”), câmpurile goale primesc valori implicite, erorile apar în română lângă câmpul greșit și ce ai scris rămâne în pagină.
- Pagina „Testează agentul” spune clar cum răspunde agentul (din informațiile firmei sau cu Claude).
- În chat, caseta „lasă-ne emailul” dispare după ce vizitatorul și-a lăsat datele.

## 0.12.0 — chat ca Tidio, în română, cu echipa live

- **Widget nou**: buton rotund cu iconiță și bulină de mesaje necitite, fereastră cu antet colorat („Salut 👋 / Cu ce te putem ajuta azi?”), poză sau inițială, starea echipei (online / revine la ora X), bule de mesaj cu avatar și oră, animația „scrie…” (trei puncte), emoji, sunet la mesaj nou, meniu (oprește sunetul, conversație nouă, confidențialitate), „Oferit de VITIM”. Pe telefon, ecran complet.
- **Butoane cu întrebări rapide** sub salut (până la 4, din setări).
- **Mesaj automat** lângă buton după N secunde (implicit 20; o dată pe vizită), cu bulină „1” și sunet.
- **Chat live cu echipa**: din Conversații, un coleg vede dacă vizitatorul e pe site acum, apasă „Preia conversația” (sau doar scrie) și răspunde; mesajul apare în câteva secunde în chatul de pe site, cu prenumele lui. Cât timp conversația e preluată, AI-ul nu mai răspunde. „Predă asistentului AI” și „Încheie conversația”.
- Vizitatorul vede când colegul scrie, primește bulină + previzualizare + sunet dacă a minimizat chatul, iar conversația continuă după reîncărcarea paginii.
- **În afara programului**: după al doilea mesaj, chatul cere numele și emailul (cu bifă de acord) → contact + lead + email către echipă.
- **Panou**: conversațiile necitite apar marcate, filtre noi „Live” și „Încheiate”, numărul de vizitatori aflați acum în chat, sunet și titlu de tab la mesaj nou.
- **Setări noi** (Agent AI → „Agentul pe site”): salut, text, întrebări rapide, mesaj automat și întârzierea lui, programul echipei (ore, weekend), poză, cerere email în afara programului, sunet.
- Pluginul WordPress nu trebuie actualizat (widgetul vine direct din platformă).

## 0.11.0 — agentul AI pe site (plugin 1.3.0)

- **Widgetul de chat** pe site-urile clienților: buton + fereastră de conversație, izolat de stilurile site-ului (Shadow DOM), ~3,5 KB comprimat, adaptat pentru telefon (ecran complet), accesibil din tastatură (Enter trimite, Esc închide). Conversația continuă după reîncărcarea paginii.
- **Notă AI și GDPR** la deschidere: vizitatorul află că vorbește cu un asistent virtual al firmei, cu link spre politica de confidențialitate a site-ului.
- **Setări** (Agent AI → „Agentul pe site”): pornit / oprit, titlu, textul butonului, culoare, poziție (dreapta / stânga), link de confidențialitate. Widgetul apare doar când agentul e Activ.
- **WordPress**: pluginul 1.3.0 adaugă singur widgetul (se poate opri din Setări → VITIM). **Alte site-uri**: un rând de cod, afișat în pagina agentului.
- **Conversații** (meniu nou în panoul clientului, în locul „Inbox – curând”): toate discuțiile vizitatorilor, cu filtrul „Cer un om”, transcriptul complet, cererile salvate și datele de contact.
- **Email către echipa firmei** (proprietar, administratori, operatori) când agentul salvează o cerere (nume, telefon, email, rezumat) sau când un vizitator cere un om.
- **Protecții**: widgetul funcționează doar pe domeniul site-ului (cheie publică + Origin verificat), token per conversație păstrat doar ca hash, limite pe IP (conversații noi, mesaje), maximum 40 de mesaje per conversație și 1.000 de caractere per mesaj, plus plafoanele de cost și de conversații din plan.

## 0.10.0 — servicii și raportul lunar

- **Servicii pe client** (Admin → client → „Servicii și rapoarte”): Mentenanță și securitate, SEO, Google Ads, Google Business Profile.
- **Raportul lunar**: pentru fiecare serviciu activ, cifrele lunii (SEO: click-uri, afișări, poziție medie, cuvinte în top 10, pagini optimizate; Ads: buget, afișări, click-uri, conversii, apeluri; GBP: vizualizări, apeluri, indicații, click-uri spre site, recenzii noi, nota, postări) **comparate automat cu luna anterioară** (verde = mai bine, roșu = mai rău), textul „ce am făcut și ce rezultate” și lucrările din jurnal pe serviciu.
- **Mentenanța se calculează singură**: actualizări aplicate, backup-uri verificate, probleme rezolvate, remedieri din panou, lucrări.
- Ciornă → previzualizare (exact ce vede clientul) → **„Publică și trimite clientului”**: raportul apare în meniul „Rapoarte lunare” al clientului și proprietarul / administratorii firmei primesc email cu linkul. Publicarea se poate retrage.
- Clientul poate descărca raportul ca PDF (tipărire din browser, fără meniuri).
- Tipuri noi în jurnalul de lucrări: Google Ads și Google Business Profile.
- Până la conectarea cu Google, cifrele SEO / Ads / GBP se copiază din Search Console, Google Ads și Business Profile.

## 0.9.0 — backup-urile site-urilor (plugin 1.2.0)

- **Backup automat** făcut de plugin pe hostingul clientului: baza de date (tabelele WordPress) + `wp-content`, `wp-config.php`, `.htaccess`. Zilnic noaptea (sau săptămânal / oprit), se păstrează ultimele N copii (implicit 7).
- Copiile stau **în afara public_html** (`/home/cont/vitim-backups/domeniu/`); dacă folderul principal nu permite scrierea, într-un folder protejat din `wp-content` (și panoul semnalează asta).
- **Fiecare backup e verificat**: arhiva bazei de date se citește până la marcajul de final, arhiva fișierelor se verifică integral.
- **Panou**: secțiunea Backup-uri pe pagina site-ului (dată, mărimi, număr de fișiere, unde e copia), butonul „Backup acum”, probleme automate în categoria nouă **Backup**: backup eșuat (critic), niciun backup reușit de 2 zile (8 la cel săptămânal), niciun backup încă. Verificare la oră și pentru site-urile care nu mai trimit nimic.
- **Clientul** vede ultimele backup-uri în raportul site-ului (fără căile de pe server) și, o dată pe săptămână, „Backup automat verificat” în Lucrări VITIM.
- Testat pe WordPress real, inclusiv restaurarea bazei de date dintr-un backup într-o bază nouă.

## 0.8.0 — auditul SEO, securitate și legal (România)

- **Audit extern al fiecărui site** (WordPress sau PHP, cu sau fără plugin), zilnic și la cerere: prima pagină, robots.txt, sitemap-ul și până la 12 pagini.
  - **SEO**: redirect http → https, www dublat, robots.txt care blochează tot, sitemap, noindex, meta viewport (mobil), titluri lipsă / prea lungi / duplicate, meta description, H1, canonical, imagini fără alt, limbă, Open Graph, date structurate pentru firmă, pagini din sitemap cu erori.
  - **Securitate**: HTTPS și certificatul (expiră în < 14 zile), antete de securitate, HSTS, versiunea PHP afișată, fișiere sensibile publice (.env, .git, backup wp-config, debug.log, phpinfo), listarea folderelor.
  - **Legal (România)**: politica de confidențialitate (GDPR), politica de cookie-uri, scripturi de urmărire fără banner de consimțământ (Legea 506/2004), termeni și condiții, linkurile ANPC – SAL și SOL (critice la magazine online), datele firmei: CUI și Nr. Reg. Com. (Legea 365/2002), contact, formulare fără informare GDPR.
  - **Viteză**: timp de răspuns, mărimea paginii, compresie.
- **„Cum rezolvi”** la fiecare problemă, cu pașii concreți (unde se setează în WordPress / cPanel / .htaccess), inclusiv pentru problemele găsite de plugin.
- **Scoruri 0–100** pe Securitate, SEO, Legal, Actualizări, Viteză; pagina site-ului e împărțită pe categorii.
- **Raportul clientului** (Panou → Site-urile tale → raport): scorurile, ce a rezolvat VITIM în ultimele 90 de zile, ce mai e de îmbunătățit și lucrările pe site.
- Auditul nu iese niciodată de pe domeniul site-ului (protecție SSRF: doar domeniul și www, IP-uri publice, redirecturi verificate).

## 0.7.0 — scanarea și remedierea site-urilor WordPress (plugin 1.1.0)

- **Scanare** (zilnic și la cerere, făcută de plugin, doar citire): actualizări WordPress / pluginuri / teme, PHP vechi, fără HTTPS, indexare blocată în Google, erori afișate vizitatorilor, debug.log public, readme.html, editorul de fișiere activ, XML-RPC, administrator „admin”, prea mulți administratori, pluginuri inactive, **fișiere WordPress modificate** (comparate cu cele oficiale de pe wordpress.org) și **fișiere PHP în uploads**.
- **Pagina site-ului** (Admin → client → click pe domeniu): problemele pe gravitate (critic / atenție / info), cu buton de remediere unde se poate; „Scanează acum”, „Actualizează toate pluginurile”; istoricul remedierilor. Problemele dispar singure când nu mai apar la scanare. Lista de site-uri arată numărul de probleme.
- **Remedieri din panou**: actualizare plugin / temă / toate pluginurile / WordPress, reinstalarea fișierelor WordPress (curăță fișierele modificate), ștergere debug.log / readme.html, dezactivare XML-RPC și editor de fișiere, blocarea PHP în uploads, permiterea indexării. Remedierile apar în jurnalul de lucrări al clientului.
- **Siguranță**: pluginul acceptă doar acțiunile din lista fixă, cereri semnate cu secretul site-ului (5 minute, fără retrimitere), adresa trebuie să fie pe domeniul site-ului; clientul poate opri remedierile din WordPress (Setări → VITIM); doar echipa VITIM le poate porni; fiecare remediere e în audit.
- **Pluginul se actualizează din WordPress**: versiunile noi publicate de platformă apar în Module → Actualizări (de la 1.1.0).

## 0.6.0 — conectarea site-urilor

- **Cod de conectare**: la adăugarea unui site (sau după „Schimbă cheile”) panoul afișează o singură dată un cod `VITIM1-…` care conține adresa platformei, cheia și secretul.
- **Plugin WordPress „VITIM Connector”** (descărcabil din fișa clientului): Setări → VITIM → lipești codul → Conectează. La fiecare oră trimite versiunea WordPress și PHP, tema, numărul de pluginuri și pluginurile / temele / WordPress cu actualizări în așteptare. Actualizările făcute în WordPress apar **automat** în jurnalul de lucrări (vizibil clientului), fără dubluri.
- **Conector pentru site-urile PHP** (`vitim-connector.php`): un fișier rulat din cron la oră; trimite PHP, versiunea aplicației și spațiul liber. Poate trimite și lucrări: `php vitim-connector.php lucrare backup "Backup verificat"`.
- **Starea site-urilor** (conectat / fără semnal, versiuni, actualizări în așteptare, adresă greșită) în fișa clientului, în lista de site-uri VITIM și în panoul clientului.
- Securitate: fiecare cerere e semnată HMAC-SHA256 cu secretul site-ului, cu fereastră de 5 minute și protecție la retrimitere; firma se deduce doar din cheie; cheile schimbate nu mai funcționează imediat.

## 0.5.0 — jurnalul de lucrări

- **Echipa VITIM** (Admin → client → „Adaugă lucrări”): înregistrează ce a făcut pentru client: data, tipul (actualizări, backup, securitate, reparație, conținut, SEO, dezvoltare, suport), site-ul, durata, detalii. Lucrările se pot marca „interne” (nu le vede clientul).
- **Clientul** (meniul „Lucrări VITIM”): vede lucrările vizibile, filtrate pe lună și pe site, cu totalul de lucrări și de timp. Ultimele lucrări apar și pe prima pagină a panoului lui.
- Adăugarea, modificarea și ștergerea apar în audit.

## 0.4.0 — actualizare din panou

- **Admin → Sistem** (doar super admin): urci arhiva `vitim-ai-x.y.z.zip`, confirmi parola și platforma se actualizează singură: backup la baza de date, fișiere noi, migrări. `.env` și `storage/` nu se ating.
- Dacă arhiva depășește limita de upload a hostingului: o urci cu File Manager în `vitim-ai/storage/app/updates/` și apeși „Aplică” în aceeași pagină.
- Se acceptă doar versiuni mai noi decât cea instalată; actualizările (reușite sau nu) apar în audit.

## 0.3.0 — Phase 2: agentul AI răspunde

- **Agentul răspunde**, din „Informații despre firmă” completate în panou (servicii, prețuri, program, politici). Nu inventează ce nu scrie acolo.
- **Salvează cereri** (`create_lead`): doar după acordul explicit al vizitatorului; contactul se deduplică după telefon / email, acordul intră în istoricul de consimțământ, lead-ul e legat de conversație.
- **Cere un om** (`request_human`): la cerere, reclamație sau întrebare fără răspuns; conversația devine „în așteptare”.
- **Testează agentul** din panou: conversații reale cu agentul, fără lead-uri sau notificări reale; se vede ce acțiuni ar fi făcut.
- **Template-uri**: general, service auto, clinică, magazin online. **Versiuni** de configurație cu istoric.
- **Cost și plafon**: costul fiecărui răspuns se calculează din tokeni; peste plafonul lunar al planului (cost sau conversații) agentul afișează datele de contact ale firmei, fără să apeleze AI-ul.
- **Set de evaluare**: 30 de întrebări (general + service auto), inclusiv tentative de manipulare: `php artisan vitim:eval <firma> [generic|auto|all]`, raport în `storage/app/private/evals/`.
- Configurare nouă în `.env`: `ANTHROPIC_API_KEY`.

## 0.2.0 — Phase 1: fundația VITIM AI Business Platform

- Roluri noi: `super_admin`, `vitim_admin` (platformă); `org_owner`, `org_admin`, `agent`, `viewer` (firmă). Conversie automată din 0.1.0.
- Organizații cu profil complet (țară, fus orar, limbă, date firmă); site-uri cu nume, platformă (WordPress/WooCommerce/custom/altele), stare de verificare.
- Agenți AI: creare și configurare validată (ton, limbi, program, reguli de transfer și de lead, acțiuni permise, fallback); fără secrete în configurație.
- Contacte cu identități (email, telefon E.164, WhatsApp, ID extern) și deduplicare; consimțământ ca istoric (cine, când, sursă, scop); liste de suprimare.
- Lead-uri cu status, intenție, scor, responsabil.
- Schema pentru conversații și mesaje (Inbox), strat de mesagerie cu interfețe de furnizor și regulă fail-safe pentru marketing.
- Evenimente de domeniu (outbox) procesate din cron; consum zilnic per firmă; audit extins.
- API intern `/api/v1` cu erori uniforme, paginare, permisiuni pe rută.
- Dashboard VITIM cu cifre reale și dashboardul firmei (prezentare, agent, contacte, lead-uri, setări); secțiunile viitoare marcate „în dezvoltare”.
- Date demo pentru dezvoltare (`php artisan db:seed`, refuză producția).

## 0.1.0

- Fundația multi-tenant, autentificare cu 2FA, dashboard VITIM minimal, chei de site, rulare pe cPanel (cron, backup pe email, arhivă zip).
