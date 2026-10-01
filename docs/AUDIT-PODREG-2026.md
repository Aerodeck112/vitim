# Audit tehnic și strategic: podreg.ro

**Data:** 1 octombrie 2026 · **Metodă:** crawl complet al celor 58 de URL-uri din sitemap, analiza HTML/CSS/JS publice, măsurători în Chromium (mobil Pixel 7 și desktop 1440 px), verificări pasive de securitate (doar cereri GET, nimic modificat pe site).

> **Rezumat în 5 rânduri**
> 1. Site-ul are **probleme critice de încredere și juridice**: politica de confidențialitate e a firmei ThemeREX (Cipru), iconițele de social media duc la ThemeREX, iar pagini demo cu prețuri în dolari și lorem ipsum sunt publice și indexate.
> 2. **Prețurile se contrazic**: configuratorul spune 375 €/m² cu manoperă inclusă, dar Cabana Poșaga costă 49.970 € + TVA pentru 92 m², adică ~543 €/m² **fără** manoperă și fără învelitoare.
> 3. **Performanța e slabă**: pe mobil LCP ≈ 8,1 s, CLS 0,45, 118 cereri și ~10 MB decodați pe homepage. Cauza principală: tema „Plank” (ThemeREX) + Elementor + Slider Revolution + WooCommerce încărcat degeaba.
> 4. **SEO tehnic de bază lipsește**: nicio meta description pe niciun URL, zero schema markup, 10 H1 pe homepage, 0 H1 pe 16 pagini de proiect, pagini de template indexate.
> 5. **Nu se măsoară nimic**: nu există GA4, Tag Manager, Meta Pixel sau Clarity. Nu știm câte cereri vin de pe site și din ce surse.
>
> Fundamentul e bun: proiecte reale spectaculoase (inclusiv în Alpii francezi), 30 de ani de experiență, atelier propriu și un configurator funcțional cu formulare custom. Problema e ambalajul, nu produsul.

---

## ETAPA 1: Ce există acum

### 1.1 Tehnologii detectate

| Componentă | Versiune / detaliu | Observație |
|---|---|---|
| WordPress | 7.1.2 | la zi; versiunea apare public în `generator`, `readme.html`, `?ver=` |
| Temă | **Plank** (ThemeREX), skin `default` + plugin `trx_addons` | temă de tip „multipurpose” cu foarte mult cod neutilizat |
| Page builder | Elementor 4.2.2 (CSS „internal”, Google Fonts activ, `font-display: auto`) | CSS inline de 82 KB pe homepage |
| Slider | Slider Revolution 6.7.53 | e elementul LCP (text animat „split chars”) și cauza principală a CLS |
| E-commerce | WooCommerce 11.0.1 + TI Wishlist + WooSmart Quick View | **nu se vinde nimic online**; `/shop/` afișează „Magazinul este în lucru” |
| Multilingv | TranslatePress (`/en/`, hreflang ro/en corect) | traducerea EN există, dar meta și titlurile sunt tot în română |
| Pop-up | Advanced Popups | |
| Formulare | custom: `wp-json/podreg/v2/estimare` (configurator) și `podreg/v1/contact-submit` (contact), cu honeypot și nonce | bine construite, dar etichetele nu sunt legate de câmpuri (lipsește `for`/`id`), deci accesibilitate slabă |
| Server | HTTP/2 + HTTP/3, Brotli activ; fără page cache (`cache-control: max-age=0`) | TTFB 0,65–1,9 s |
| Analytics / pixeli | **niciunul** | |
| SEO plugin | **niciunul** (sitemap-ul e cel nativ WordPress) | |

### 1.2 Arhitectura informației și URL-urile

Din sitemap:

- **Pagini de categorie:** `/cabane-case-lemn/`, `/casute-de-lemn/` (titlu „Cabane sub 40 m2”), `/scari-interioare-lemn/`, `/terase-foisoare/`.
- **Pagini de proiect (38):** structura URL e **inconsecventă**:
  - cabanele sunt sub părinte: `/cabane-case-lemn/cabana-posaga/`
  - scările, foișoarele, terasele, tiny house-urile, carportul și Cabana Comandău sunt **la rădăcină**: `/scara-gornesti/`, `/terasa-budiu/`, `/cabana-comandau/`
  - meniul „Tiny House” conține și o căsuță de grădină și un observator de vânătoare.
- **Pagini rămase din template, publice și indexate:**
  - `/service-plus/`: oferte ThemeREX în **dolari** („Full Website Package $699”, „contact our support at themerex.net”)
  - `/our-clients/`: lorem ipsum („Adipiscing elit, sed do euismod…”)
  - `/privacy-policy/`: politica de confidențialitate **a ThemeREX**
  - `/shop/`: magazin gol
  - `/404-page/`: răspunde 200 și apare în sitemap
  - `/layouts/*` (10 URL-uri): header-e și footer-e de template indexabile
  - `/author/vitim/`: expune utilizatorul de administrare
- **Blog:** nu există (`/blog/` dă 404, feed-ul RSS e gol). Deci zero conținut informațional pentru SEO.
- **Despre noi:** pagina **e goală** (doar titlul). Homepage-ul trimite de 4 ori spre ea.

### 1.3 Header, meniu și footer

- **Logo „Transilvania Case Lemn”**, dar brandul de pe site, din titluri și din formulare e **PODREG**. Vizitatorul vede două nume diferite. Logo-ul verde închis are contrast slab pe fotografia din hero și pe footer-ul negru.
- **Meniu desktop:** se văd doar „PODREG / CABANE SI CASE DIN LEMN / TINY HOUSE” și un „⋮”. Contact, Configurator, Scări și Terase sunt ascunse. Mega-meniul listează **toate cele 38 de proiecte**, iar meniul e duplicat de 3 ori în DOM (desktop, mobil, panou).
- **Lipsesc din header** butonul de ofertă și telefonul.
- **Panoul lateral (iconița cu 9 puncte)** conține texte de demo: „Have a Project? **info@website.com**”, „Where to Find Us? Look Here”, „Want to Stock Up? Go to Shop”.
- **Footer:** titluri în engleză („Hello”, „Get in touch”, „All rights reserved”); social media cu link spre **facebook.com/ThemeRexStudio, instagram.com/themerex_net, x.com/ThemerexThemes, dribbble.com/ThemeREX**. Lipsesc datele de identificare ale firmei (denumire, CUI, Nr. Reg. Com.), obligatorii conform Legii 365/2002. Imaginea „Google Reviews” nu are link.
- Texte fără diacritice în meniu și în configurator („Configureaza”, „Suna”, „Scari”).

### 1.4 Configuratorul (`/configurator-cabane-lemn/` și pe homepage)

- Un singur parametru real: **suprafața (10–300 m²)**. Restul e formular de lead (nume, telefon, email, localitate, stadiu, mesaj, GDPR).
- Textul afișat: „Estimarea este calculată la 375 EUR/m², fără TVA, cu manopera inclusă. **Prețul apare doar în email.**” Asta e o contradicție: formula e publică, deci promisiunea „îl afli doar pe email” pare un truc.
- **Conflict de preț** cu paginile de produs (Poșaga ~543 €/m² în automontaj, fără manoperă).
- **Conflict cu pagina /proces/**: acolo scrie că estimarea folosește „suprafața, numărul de niveluri și tipul de structură”. Configuratorul cere doar suprafața.
- După trimitere apar WhatsApp cu mesaj precompletat și un buton de apel. Asta e bine.
- Pe homepage, configuratorul are **logo propriu și header propriu în interiorul paginii**, adică arată ca un site în site.

### 1.5 Formularul de contact

- 7 câmpuri obligatorii (nume, prenume, email, telefon, temă, mesaj, GDPR). E prea mult pentru un prim contact.
- Textul de sub buton: „Mesajul ajunge la office@podreg.ro, **office@vitim.ro**…”. Expune public adresa agenției și rutarea internă.
- Linkul GDPR duce la politica ThemeREX, deci **consimțământul colectat nu e valid**.

### 1.6 Pagini de proiect (exemplu: Cabana Poșaga, Scara Gornești)

- Structură: titlu, un paragraf de text, apoi **10–40 de fotografii stivuite pe toată lățimea**. Paginile ajung la 13.000+ px înălțime pe desktop. Nu există galerie, lightbox sau navigare.
- **Niciun CTA** pe pagina de proiect (nici „Cere ofertă pentru acest model”, nici telefon).
- **Lipsesc** fișa tehnică structurată, suprafața ca filtru, prețul „de la” și proiectele similare.
- Fotografii de telefon mărite, unele neclare (scările). Pe paginile de foișoare, terase și scări **nu există H1**.
- Textul de preț e scris neîngrijit: „Cabana posaga pret 49970 euro + tva livrare in automontaj. Invelitoarea… nu montam noi. Asiguram insa know how”.

### 1.7 Performanță (măsurat în Chromium, fără cache)

| Pagină | TTFB | FCP | **LCP** | **CLS** | Cereri | Resurse decodate |
|---|---|---|---|---|---|---|
| Homepage mobil | 1,44 s | 5,2 s | **8,1 s** | **0,449** | 118 | 10,2 MB (CSS 4,4 MB · JS 1,9 MB · imagini 3,2 MB · fonturi 0,6 MB) |
| Homepage desktop | 1,44 s | 4,6 s | **8,0 s** | 0,163 | 119 | 10,0 MB |
| Cabana Poșaga mobil | 0,66 s | 3,1 s | 3,1 s | 0 | 101 | 8,9 MB |
| Configurator mobil | 0,66 s | 2,9 s | 3,0 s | 0 | 83 | 5,7 MB |

Pragurile Google „bun” sunt LCP ≤ 2,5 s și CLS ≤ 0,1. Homepage-ul pică pe ambele.

Cauze:

- **52 de fișiere CSS** externe + 15 blocuri `<style>` + **39 de scripturi** externe + 24 inline. jQuery, WooCommerce, wishlist, quick view și Slider Revolution se încarcă pe fiecare pagină.
- Elementul LCP e textul animat din Slider Revolution (`rs_splitted_chars`). Textul apare abia după ce rulează JS-ul sliderului.
- **Imagini:** 23 JPG + 55 PNG și **o singură WebP** pe homepage. Exemple: `A7401943-Edit-2048x2048-1.jpg` are **1,3 MB**, `podreg_case_Lemn_transilvania_2019-12.jpg` are 700 KB. Nu există AVIF/WebP și nici `fetchpriority` pe imaginea din hero.
- Animațiile de intrare ascund conținutul până la scroll. În capturile de ecran apar secțiuni întregi albe (inclusiv H1-ul „Construim din lemn…”).
- Fonturile Google se încarcă de pe alt domeniu, cu `font-display: auto` (text invizibil cât se încarcă fontul).
- Nu există page cache (`max-age=0` pe HTML). Redirectul `www → non-www` și `http → https` îl face WordPress (PHP), nu serverul.
- Consola nu are erori JS. Asta e bine.

### 1.8 Securitate (verificări pasive)

| Constatare | Risc | Remediere |
|---|---|---|
| `/wp-json/wp/v2/users` și `/?author=1` expun utilizatorul **`vitim`** (admin) | mediu: îi dă atacatorului jumătate din credențiale | blocare endpoint users pentru vizitatori anonimi, redirect al arhivei de autor, admin cu alt slug |
| `readme.html`, `license.txt`, `generator`, `?ver=` | scăzut: dezvăluie versiunile exacte | ștergere / eliminare |
| **Lipsesc toate header-ele de securitate** (HSTS, X-Content-Type-Options, X-Frame-Options/`frame-ancestors`, Referrer-Policy, Permissions-Policy, CSP) | mediu | se adaugă în `.htaccess` / server |
| `/wp-json/` returnează **1,5 MB** public | scăzut, dar e și o problemă de încărcare | limitare rute publice |
| `/wp-content/plugins/` răspunde 200 (gol) | scăzut | `Options -Indexes` + 403 |
| `wp-login.php` ascuns (404), `xmlrpc.php` blocat (405), `.env` / `debug.log` → 403 | ✅ bine | rămân așa |
| Endpoint-urile formularelor nu par să aibă rate limiting vizibil (doar honeypot) | mediu (spam, abuz de email) | limitare pe IP + Cloudflare Turnstile invizibil |
| Teme și pluginuri premium cu istoric de vulnerabilități (Slider Revolution, `trx_addons`) | mediu-ridicat pe termen lung | eliminate prin refacere |

---

## ETAPA 2: Audit UI/UX

### 2.1 Ce arată învechit

1. **Slider-ul full-screen** cu text uriaș pe benzi semi-transparente gri și săgeți. Estetica e din 2015–2018, iar pe mobil hero-ul ocupă tot ecranul fără nicio informație utilă.
2. Butoanele **verde-lime (#a9b93e)** cu text alb mic, bold și cu majuscule spațiate. Contrastul e insuficient (WCAG) și aspectul e generic de temă.
3. Separatoarele „frunză” decorative și titlurile cu „eyebrow” mic deasupra („CONTACTS”, „PODREG CONFIGURATOR”). Sunt tipice ThemeREX.
4. Emoji folosite ca iconițe în paginile Servicii și Proces (🏡📋💬📐). Pe un site de construcții premium, efectul e amator.
5. Pagina de proiect ca un perete de fotografii stivuite, fără galerie.
6. „Scroll Down” și săgețile sliderului, în engleză.

### 2.2 Ce e aglomerat

- **Mega-meniul cu 38 de proiecte.** Vizitatorul nu caută „Cabana Les Thuiles” după nume. Caută o cabană de ~50 m² la cheie.
- Secțiunea „Produsele noastre” de pe homepage: 4 blocuri zig-zag cu paragrafe lungi, fără scanare rapidă.
- Configuratorul pe homepage: un întreg „mini-site” (logo, telefon, hero, 3 badge-uri, formular cu 8 câmpuri, 3 carduri laterale) la mijlocul paginii.
- Pe homepage există **două formulare complete** (configurator și contact), unul după altul, iar vizitatorul nu știe pe care să-l folosească.

### 2.3 Ce nu inspiră încredere

| Problemă | De ce contează |
|---|---|
| Social media care duce la ThemeREX | vizitatorul care dă click pleacă de pe site și vede că Podreg nu are profiluri. Pare un site abandonat. |
| `info@website.com`, „Want to Stock Up? Go to Shop” | semn clar de template neterminat |
| Pagina „Despre noi” goală | e **cea mai vizitată pagină de încredere** într-un business de 50.000 € |
| `/service-plus/` cu prețuri în dolari, `/our-clients/` lorem ipsum | Google le poate afișa în rezultate |
| Politica de confidențialitate a altei firme | juridic, consimțământul GDPR nu e valid |
| Două branduri (Transilvania Case Lemn / PODREG) | confuzie: care e firma? |
| Lipsesc CUI și denumirea legală | obligație legală și semnal de încredere în B2C |
| Fără recenzii reale (doar o imagine „Google Reviews” fără link) | |
| Fără garanții, certificări, specii de lemn, umiditate, tratamente | e exact ce verifică un cumpărător rațional |
| Prețuri contradictorii (375 €/m² vs ~543 €/m²) | clientul se simte manipulat când află prețul real |
| Textul „Deși avem rădăcini în județul Mureș, realizăm lucrări pentru clienți din toată Europa” fără dovezi vizibile | dovezile există (Enchastrayes, Les Thuiles, Pont de Fosse, Saint Denis, Carcassonne, Saissac sunt în Franța), dar nu sunt valorificate |

### 2.4 Unde abandonează utilizatorul

1. **Hero:** butonul „Descoperă mai mult” nu spune unde duce. Pe mobil, primul ecran nu are niciun beneficiu concret (preț, timp, garanție).
2. **Cardul „Cabane și case de lemn” de pe homepage** începe cu condiții de respingere: „pentru a realiza o estimare… sunt necesare **două condiții cumulative**: să dețineți terenul… să aveți o schiță”. Asta îi elimină din start pe cei aflați în faza de documentare, adică majoritatea vizitatorilor.
3. **Click pe „Despre noi”** duce la o pagină goală. Utilizatorul iese.
4. **Pagina de proiect:** după 40 de fotografii nu există pas următor. Utilizatorul apasă „Back”.
5. **Configuratorul:** cere 6 câmpuri obligatorii înainte de a arăta orice cifră, deși formula e scrisă deasupra. Utilizatorul face calculul singur și pleacă fără să lase datele.
6. **Pagina /proces/:** taxa de 500–1000 RON pentru studiu apare fără context de valoare (ce primește clientul concret, în cât timp).
7. **Contact:** 7 câmpuri obligatorii.
8. **Mobil:** meniul hamburger cu 38+ intrări, fără buton de apel sau ofertă vizibil permanent.

### 2.5 Unde lipsesc CTA-uri

- Header (desktop și mobil): lipsește „Cere ofertă” și telefonul.
- **Fiecare pagină de proiect:** nu are niciun CTA.
- Paginile de categorie: doar „Vezi produsele”, fără „Cere ofertă pentru o cabană similară”.
- Pagina Servicii (fiecare serviciu) și finalul paginii Proces (are doar linkul spre configurator la pasul 1).
- Nu există bară sticky pe mobil.

### 2.6 Prea mult / prea puțin

| Prea mult | Prea puțin |
|---|---|
| Fotografii nestructurate pe proiect | Specificații: suprafață, niveluri, sistem constructiv, secțiune bârnă, specie lemn, termen de execuție, locație, an |
| Paragrafe de marketing generic („extraordinare… experiență unică și de neuitat”) | Cifre: proiecte livrate, ani, țări, m² construiți, termen mediu |
| Meniuri | Despre noi: echipă, atelier, utilaje, istorie (30 de ani), fotografii reale din fabrică |
| Formulare duplicate | FAQ: autorizație, fundație, transport, durată, garanție, întreținere, kit vs. la cheie |
| Câmpuri obligatorii | Recenzii, studii de caz cu „înainte/după”, video |

### 2.7 De mutat

- Configuratorul: de pe homepage-ul „mini-site” într-un **teaser de 1 ecran** (slider de m², preț „de la… până la…” afișat live, buton „Primește detaliile pe email”). Versiunea completă rămâne pe pagina dedicată.
- Condițiile pentru ofertare: de pe homepage pe pagina „Cum lucrăm”, reformulate pozitiv („Ca să primești o ofertă exactă, te ajutăm să pregătești…”).
- Prețurile: din textul liber de la finalul paginilor, într-o **fișă tehnică** standard, sus, lângă galerie.
- Scările și foișoarele: în URL-uri sub categoria lor (cu redirect 301).

### 2.8 De eliminat

`/service-plus/`, `/our-clients/` (sau rescrisă cu clienți reali), `/shop/`, `/404-page/` din sitemap, `/layouts/*` din index, panoul lateral demo, iconițele ThemeREX, WooCommerce + Wishlist + Quick View (dacă nu se vinde online), Slider Revolution, iconițele emoji, mesajul cu `office@vitim.ro`, H1-urile multiple, animațiile de intrare pe text.

### 2.9 Secțiuni noi de introdus

1. **Bandă de dovezi:** „30+ ani · 300+ construcții · 6 țări · atelier propriu în Răstolița”. Cifrele trebuie confirmate de client.
2. **„Alege tipul de construcție”:** 5 carduri vizuale cu „de la X €” și suprafețe tipice: Case din lemn · Cabane < 40 m² · Tiny house · Terase și foișoare · Scări interioare.
3. **Hartă de proiecte în Europa** (România + Franța): diferențiator puternic, aproape nimeni din piață nu o are.
4. **„De la pădure la cheie”:** atelierul propriu (lemnul e prelucrat intern: corni, stâlpi, lambriu, balustrade). E un USP real, acum îngropat în text.
5. **Kit (automontaj) vs. La roșu vs. La cheie:** tabel clar cu ce include fiecare variantă. Rezolvă confuzia de preț.
6. **Cum lucrăm în 5 pași**, cu durate și cu taxa de studiu explicată ca beneficiu (se deduce din avans).
7. **Testimoniale reale** (Google Reviews integrate) + video scurte de la beneficiari.
8. **FAQ** cu schema FAQPage.
9. **Ghid descărcabil** („Cabana din lemn: costuri reale, autorizații, pași. Ghid 2026”) ca lead magnet pentru vizitatorii care nu sunt încă pregătiți.

### 2.10 Structura propusă pentru homepage

Ordinea e gândită pentru un produs scump, cu decizie lungă (3–12 luni). Mai întâi clarificăm „ce faceți și cât costă”, apoi construim încrederea și abia apoi cerem datele.

```
1. HEADER fix, subțire
   Logo PODREG (un singur brand) · Case din lemn · Cabane & Tiny · Terase & Foișoare · Scări · Proiecte · Despre · [☎ 0752 675 675] [Cere ofertă →]

2. HERO (o singură imagine statică optimizată sau un video scurt, fără slider)
   H1: „Case și cabane din lemn masiv, construite de la pădure la cheie”
   Sub: „Atelier propriu în Mureș · 30 de ani · proiecte în România și Franța”
   CTA primar: „Calculează costul cabanei tale” (deschide configuratorul)
   CTA secundar: „Vezi proiectele realizate”
   Micro-dovezi: ★ 4,9 Google (dacă e cazul) · Garanție X ani · Răspuns în 24h

3. BANDĂ DE CIFRE: ani · construcții · țări · m² construiți

4. CE CONSTRUIM: 5 carduri cu imagine, interval de suprafață, „de la X €”, link spre categorie

5. CONFIGURATOR-TEASER (1 ecran)
   Tip construcție + slider m² + nivel de finisare → interval de preț live
   CTA: „Trimite-mi devizul detaliat pe email” (abia aici cerem email + telefon)

6. PROIECTE SEMNĂTURĂ: 6 proiecte, filtrabile (Case / Cabane / Tiny / Terase / Scări), + hartă România–Franța

7. DE CE PODREG: 4 USP-uri cu dovezi
   Atelier propriu, nu revindem · Lemn uscat și tratat (specificații) · Sisteme constructive verificate · Echipă proprie de montaj

8. KIT vs. LA ROȘU vs. LA CHEIE: tabel comparativ

9. CUM LUCRĂM: 5 pași cu durate (Discuție → Studiu tehnico-financiar → Contract → Producție în atelier → Montaj și predare)

10. TESTIMONIALE: Google Reviews + 1–2 video

11. FAQ: 6–8 întrebări (autorizație, fundație, transport, durată, garanție, întreținere)

12. CTA FINAL: „Spune-ne ce vrei să construiești”, formular scurt de 3 câmpuri + WhatsApp + apel

13. FOOTER: date legale complete, social media reale, ANPC, link-uri SEO pe categorii și localități
```

**Direcția vizuală:** paletă caldă, naturală (lemn de molid, verde de brad închis ca accent, alb cald, antracit). Tipografie serif elegantă la titluri, sans curat la text. Fotografii mari, decupate generos, mult spațiu alb. Butoane cu contrast real. Micro-animații doar la hover, nu la intrarea textului. Un singur brand, PODREG, cu „Transilvania Case Lemn” eventual ca nume istoric pe pagina Despre.

---

## ETAPA 3: Conversie și generare de lead-uri

### 3.1 Situația actuală

- **Nu se măsoară nimic** (niciun tag). Prima acțiune e instalarea GTM + GA4 + Consent Mode v2 + banner de cookies, cu evenimente pe: `generate_lead` (configurator/contact), `click_whatsapp`, `click_tel`, `click_email`, `config_slider_used`, `scroll_75`. Opțional Microsoft Clarity pentru hărți de click.
- Fără date reale de bază, țintele de mai jos sunt estimative: o rată tipică de conversie pentru construcții de valoare mare e de 1–3% din vizite.

### 3.2 Propuneri concrete

| # | Element | Ce propun concret | Prioritate |
|---|---|---|---|
| 1 | **CTA sticky** | Mobil: bară jos cu 3 butoane (☎ Sună · WhatsApp · Cere ofertă). Desktop: buton „Cere ofertă” fix în header + „Calculează costul” vizibil după primul scroll. Pe pagina de proiect, bară sticky cu „Vreau o cabană ca **Poșaga**” (modelul se transmite automat). | P0 |
| 2 | **Cere ofertă rapid** | Formular în 3 câmpuri: nume, telefon, „ce vrei să construiești” (chips). Emailul devine opțional. Se deschide ca drawer de oriunde, cu contextul paginii precompletat. | P0 |
| 3 | **Formular inteligent (multi-step)** | Pasul 1: tip construcție (carduri vizuale). Pasul 2: suprafață și niveluri. Pasul 3: ai teren? ai proiect? când vrei să începi? buget. Pasul 4: contact. Bară de progres, salvare automată între pași, întrebări condiționale (ex.: „scări” → dimensiuni gol + esență). Datele de contact se cer la final, după ce utilizatorul a investit deja timp. | P0 |
| 4 | **WhatsApp** | Buton cu mesaj precompletat **contextual** („Bună ziua, mă interesează un model ca Cabana Poșaga, ~90 m², în jud. Cluj”). Ideal pe WhatsApp Business API, ca să ajungă direct în CRM. Program de răspuns afișat. | P0 |
| 5 | **Callback request** | „Te sunăm noi în 15 minute (L–V 8–17)”: doar telefon + interval. În afara programului: „Te sunăm mâine dimineață între 9 și 10”. Notificare push/SMS către consultant. | P1 |
| 6 | **Configurator v2** | Parametri: tip (casă / cabană / tiny / foișor), suprafață, niveluri, sistem constructiv (bârnă masivă 150×200 / lemn profilat / structură cu izolație), nivel de livrare (kit automontaj / la roșu / la cheie), finisaj, locație → **cost transport calculat automat din distanța față de Răstolița**. Rezultatul e un **interval de preț afișat pe ecran** (nu ascuns), iar detaliile merg pe email. Prețurile se calibrează pe proiectele reale, ca să nu mai contrazică paginile de produs. | P0 |
| 7 | **Salvare configurație** | Link unic (`/configurator/?c=abc123`) și „Salvează și continuă mai târziu” prin email (magic link). Configurațiile salvate devin lead-uri calde. | P1 |
| 8 | **Trimitere configurație pe email** | **PDF generat automat**, cu brand: rezumatul opțiunilor, intervalul de preț, ce include fiecare variantă, 3 proiecte similare cu poze, următorii pași și link de programare a unui apel. Copie internă către consultant. | P1 |
| 9 | **Ofertare automată** | Pentru produsele standardizabile (foișoare, terase, căsuțe de grădină, tiny house pe modelele existente), **ofertă preliminară automată** cu valabilitate de 14 zile, generată din tabelul de prețuri. Pentru case: pre-deviz, apoi validare de către consultant într-un clic (aprobă / ajustează) înainte de trimitere. | P2 |
| 10 | **Lead scoring** | Vezi tabelul de mai jos. Scorul decide prioritatea apelului și secvența de email. | P1 |

#### Model de lead scoring (punctaj 0–100)

| Semnal | Puncte |
|---|---|
| Are teren | +20 |
| Are proiect / autorizație | +15 |
| Vrea să înceapă în < 6 luni | +15 |
| Buget declarat compatibil cu suprafața (± 20%) | +15 |
| Casă / cabană > 40 m² (valoare mare) | +10 |
| Locație în aria de livrare principală (≤ 300 km) sau Franța | +5 |
| A folosit configuratorul + a deschis PDF-ul | +5 |
| A revenit pe site de 2+ ori / a vizitat „Cum lucrăm” | +5 |
| A cerut callback sau a scris pe WhatsApp | +10 |
| Doar „analizez opțiunile”, fără teren | −10 |
| Email temporar / telefon invalid | −30 |

**Praguri:** ≥ 60 = **fierbinte** (apel în aceeași zi) · 30–59 = **cald** (apel în 48h + secvență email) · < 30 = **în documentare** (nurturing automat: ghid, studii de caz, invitație la vizită în atelier).

### 3.3 Funcționalități NOI care ar diferenția Podreg

1. **Jurnal de șantier pentru clienți:** fiecare client primește o pagină privată cu etapele, fotografiile săptămânale din atelier și de pe șantier și documentele proiectului. Reduce telefoanele de tipul „unde am rămas?” și produce automat conținut pentru studii de caz (cu acordul clientului).
2. **Hartă interactivă a proiectelor** (România + Franța), filtrabilă după tip, cu fiecare pin legat de studiul de caz.
3. **„Cabana în terenul tău”:** utilizatorul încarcă o fotografie a terenului și primește o vizualizare orientativă a modelului ales (AI image + revizuire umană). Oferă un motiv puternic de a lăsa datele de contact.
4. **Vizită în atelier / la o cabană livrată:** programare online (calendar), cu confirmări automate. Pentru produse de 50.000 €+, o vizită închide vânzarea.
5. **Asistent AI pe site** antrenat strict pe conținutul Podreg (FAQ, procese, specificații, prețuri „de la”). Răspunde 24/7, califică lead-ul cu aceleași întrebări din scoring și predă conversația pe WhatsApp/CRM. Infrastructura există deja în VITIM Cloud (agentul AI din `cloud/`).
6. **Simulator de cost total**, nu doar pentru construcție: fundație estimată, transport, racorduri, autorizație, ca ordine de mărime. Transparența asta lipsește în piață și construiește încredere.
7. **Comparator de sisteme constructive** (bârnă masivă vs. profilat vs. cadre), cu avantaje, întreținere, performanță termică și preț relativ.
8. **Ghid PDF + mini-curs pe email** („7 lucruri de știut înainte să construiești o cabană din lemn”) pentru vizitatorii aflați în documentare.
9. **Versiune în franceză** a paginilor-cheie: piața din Alpii francezi e deja validată de proiectele existente, cu tickete mari și concurență mică din partea producătorilor români.
10. **Cerere automată de recenzie Google** la 7 zile după predare, cu link direct.

### 3.4 Automatizarea fluxului de lead-uri

```
Formular / Configurator / WhatsApp / Callback / Asistent AI
        │
        ▼
 Endpoint unic WordPress (validare, Turnstile, rate-limit, UTM + gclid/fbclid)
        │
        ├──► CRM (VITIM CRM din acest repo sau alt CRM): contact + deal + scor + sursă
        ├──► Notificare consultant (email + WhatsApp/SMS dacă scor ≥ 60)
        ├──► Email către client: confirmare + PDF configurație
        ├──► GA4 / Meta CAPI / Google Ads Enhanced Conversions (conversie cu valoare estimată)
        └──► Secvență automată în funcție de scor:
              Fierbinte: apel azi → reamintire internă la 4h dacă lead-ul nu a fost contactat
              Cald: Z+1 studii de caz similare · Z+3 „Cum lucrăm” · Z+7 invitație la apel
              Documentare: ghid · Z+7 costuri reale · Z+14 vizită atelier · lunar: proiecte noi
```

**Indicatori urmăriți:** lead-uri pe lună și pe sursă, timp până la primul contact (țintă < 2h în program), rată lead → studiu plătit, rată studiu → contract, valoare medie de contract, cost per lead pe canal.

---

## ETAPA 4 (bonus): SEO tehnic și de conținut

### 4.1 Probleme de reparat

| Problemă | Situație | Rezolvare |
|---|---|---|
| Meta description | **0 din 58** URL-uri | scrise manual pentru toate paginile importante |
| Titluri | „X – PODREG \| CASE DE LEMN \| CABANE \| FOISOARE” (lungi, cu majuscule, cuvântul-cheie trunchiat) | „Cabana Poșaga, 92 m², lemn masiv \| PODREG” |
| H1 | 10 pe homepage, 11 pe `/cabane-case-lemn/`, **0** pe 16 pagini de proiect | un singur H1 pe pagină, ierarhie H2/H3 corectă |
| Schema | zero | `HomeAndConstructionBusiness` (adresă, telefon, geo, program, `areaServed` RO+FR), `Product` + `Offer` pe modelele cu preț, `BreadcrumbList`, `FAQPage`, `ImageObject` |
| Open Graph | doar logo-ul ca imagine, fără `og:title`/`og:url` per pagină | OG complet, cu imaginea reprezentativă a proiectului |
| Alt la imagini | ~60–75% lipsă pe paginile de proiect | alt descriptiv, cu nume de fișier relevant (`cabana-posaga-lemn-masiv-interior-dormitor.webp`) |
| Index „murdar” | layouts, author, 404-page, service-plus, our-clients, shop, privacy ThemeREX | noindex / ștergere + 410 |
| URL-uri inconsecvente | `/scara-gornesti/` vs. `/cabane-case-lemn/cabana-posaga/` | structură `/proiecte/{categorie}/{proiect}/`, cu 301 de pe URL-urile vechi |
| Diacritice și copywriting | lipsă în meniu, configurator, texte de preț | revizie completă de text |
| EN | titluri și meta netraduse | traducere SEO a meta; adăugare **FR** |
| Blog | inexistent | hub de conținut (vezi mai jos) |
| Google Business Profile | nelegat de pe site | link, recenzii integrate, NAP identic peste tot |

### 4.2 Clustere de conținut (cuvinte-cheie de validat cu Search Console / Keyword Planner)

- **Bani:** „case din lemn masiv preț”, „cabană din lemn preț pe mp”, „tiny house preț România”, „foișor din lemn preț”, „scări interioare din lemn stejar preț”.
- **Informațional:** „autorizație construire cabană din lemn”, „fundație pentru casă din lemn”, „casă din lemn vs. cărămidă”, „cât durează construcția unei case din lemn”, „întreținerea unei case din lemn masiv”.
- **Local:** „case din lemn Mureș / Cluj / Brașov / Harghita”, „cabane din lemn Apuseni”, cu pagini de proiect care demonstrează experiența locală.
- **FR:** „chalet en bois massif Roumanie”, „construction chalet bois Alpes prix”, cu proiectele din Franța ca dovadă.

Ritm recomandat: 2 articole pe lună + 1 studiu de caz pe lună, generate din „jurnalul de șantier”.

---

## ETAPA 5 (bonus): Recomandare tehnică de refacere

**Recomandare: păstrăm WordPress, schimbăm tot ce stă deasupra lui.**

- **Temă:** ieșim din Plank/ThemeREX + Elementor + Slider Revolution. Trecem pe o **temă custom de blocuri (FSE)** sau pe o temă-bază minimală, cu componente proprii. Clientul poate edita conținutul, dar nu poate „strica” designul.
- **Proiectele** devin un **Custom Post Type „Proiect”** cu câmpuri structurate (tip, suprafață, niveluri, sistem constructiv, lemn, locație, țară, an, preț de la, variantă de livrare, galerie). Din aceste câmpuri se generează automat fișa tehnică, schema Product, filtrele, harta și proiectele similare.
- **Eliminăm** WooCommerce, Wishlist, Quick View, Advanced Popups, Slider Revolution și `trx_addons`. Din ~50 de fișiere CSS și ~40 de scripturi ajungem la sub 5 CSS / 5 JS. **Ținte:** LCP < 2 s pe mobil, CLS < 0,05, homepage < 1 MB, Lighthouse ≥ 95.
- **Imagini:** AVIF/WebP cu `srcset`, `fetchpriority="high"` pe hero, lazy loading pe restul, redimensionare la încărcare (maxim 2400 px).
- **Infrastructură:** page cache (LiteSpeed Cache dacă serverul e LiteSpeed, altfel cache static), Cloudflare (CDN, WAF, Turnstile), redirecturi la nivel de server, fonturi găzduite local.
- **Securitate:** header-ele de mai sus, blocare enumerare utilizatori, 2FA pe admin, backup zilnic în afara serverului, actualizări automate pentru minore, monitorizare uptime.
- **Formulare:** endpoint unic, cu Turnstile și rate limiting, care trimite în CRM. Dispare mesajul public cu adresele interne.

---

## Plan de acțiune

### Faza 0: Urgent, în această săptămână (fără redesign)

1. Politica de confidențialitate reală a PODREG (+ politica de cookies) și relegarea checkbox-urilor GDPR. **Risc juridic.**
2. Înlocuire linkuri social media cu profilurile reale (sau eliminare) și ștergerea panoului lateral demo (`info@website.com`).
3. Ștergere sau noindex: `/service-plus/`, `/our-clients/`, `/shop/`, `/layouts/*`, `/author/vitim/`, `/404-page/` din sitemap.
4. Conținut minim pe „Despre noi” (istorie, atelier, echipă, fotografii) sau scoaterea linkurilor până e gata.
5. **Alinierea prețurilor** între configurator și paginile de produs. Decizie de business: interval pe variante (kit / la roșu / la cheie), nu un singur „375 €/m²”.
6. Eliminarea textului „Mesajul ajunge la … office@vitim.ro”.
7. Date legale complete în footer (denumire, CUI, Nr. Reg. Com.).
8. GTM + GA4 + Consent Mode v2 + evenimente de conversie, ca să avem o bază de comparație înainte de redesign.
9. Header-e de securitate, blocare `/wp-json/wp/v2/users` pentru anonimi, ștergere `readme.html`.
10. Buton „Cere ofertă” + telefon în header și CTA la finalul fiecărei pagini de proiect.

### Faza 1: Refacere (4–6 săptămâni)

Temă nouă + CPT Proiect + homepage nou + pagini de categorie cu filtre + pagină de proiect cu galerie și fișă tehnică + formular multi-step + configurator v2 + sticky CTA + SEO tehnic complet + 301-uri + migrare imagini.

### Faza 2: Automatizare (2–4 săptămâni, în paralel cu finalul Fazei 1)

CRM + lead scoring + PDF configurație + secvențe de email + WhatsApp Business + callback + cerere automată de recenzii.

### Faza 3: Creștere (continuu)

Blog și studii de caz, versiunea FR, asistent AI, hartă de proiecte, jurnal de șantier, „cabana în terenul tău”, campanii Google Ads / Meta cu conversii offline importate din CRM.

---

## Decizii necesare de la Podreg

1. **Prețurile:** ce include exact fiecare variantă (kit / la roșu / la cheie) și care sunt intervalele reale pe m², pe sistem constructiv.
2. **Brandul:** rămâne doar PODREG? Ce rol mai are „Transilvania Case Lemn”?
3. **Cifrele de încredere:** câte construcții, din ce an, în câte țări, ce garanție.
4. **Social media și Google Business Profile:** există profiluri reale?
5. **Magazin online:** se va vinde ceva online (lambriu, scări standard)? Dacă nu, WooCommerce se elimină.
6. **Piața franceză:** este o direcție strategică? De ea depinde dacă facem versiunea FR.
7. **Capacitate:** câte lead-uri pe lună poate procesa echipa? Asta stabilește cât de agresiv calificăm.
