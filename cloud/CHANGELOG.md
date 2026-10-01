# VITIM AI Cloud — versiuni

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
