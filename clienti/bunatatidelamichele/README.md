# Bunătăți de la Michele – magazin online

Magazinul online **bunatatidelamichele.ro** (SC Gelateria da Michele SRL), refăcut pe o platformă proprie, fără WordPress: site public, coș, finalizare comandă, plăți **BT iPay** și ramburs, panou de control și SEO complet. Rulează pe orice hosting cu cPanel (PHP 8.1+ și MySQL), fără Node.js, Composer sau compilare.

Design premium (v1.1): negru-espresso, auriu și ivoire, titluri serif, produse decupate în vitrine întunecate, accent pe exclusivitate (unicul importator Saka & Pareo în România).

Construit pe nucleul platformei VITIM (actualizări din .zip, backup, 2FA, cache de pagini, redirecționări).

---

## 1. Ce a fost preluat de pe site-ul vechi

| Element | Situație |
|---|---|
| **Cele 7 produse** | Aceleași nume, prețuri, descrieri scurte și imagini: ACS One Coffee (2.934 lei), Cafea boabe Miscela BAR 1kg (130 lei), Cialda Clasic Bar (1,30 lei/buc), Grana Miscela CremaBar 1kg (152 lei), Pareo Miscela BAR 1kg (145 lei), Saka Decaffeinato (1,45 lei/buc), Saka Premium (1,40 lei/buc) |
| **Adresele produselor** | Identice: `/produs/<slug>` – pozițiile din Google se păstrează |
| **Categorii** | Cafea boabe, Cialde și monodoze, Espresoare de cafea (din etichetele WooCommerce) |
| **Texte** | Prima pagină (slider, „Cafea Boabe, Prăjită cu Pasiune”, cifre, „De la Boabă la Ceașcă”), Despre noi, întrebările frecvente de la Contact |
| **Livrare** | Curier rapid, 20 lei, 1–3 zile lucrătoare (ca pe WooCommerce) |
| **Plăți** | Card online prin BT iPay + ramburs (aceleași metode ca pe WooCommerce) |
| **Adrese vechi** | `/shop`, `/about-us-3`, `/contact-us`, `/categorie-produs/cafea`, `/eticheta-produs/...`, `/contul-meu`, `/cos/`… → redirecționări 301. Paginile demo (Sample Page etc.) → 410 |

Descrierile lungi ale produselor erau goale pe site-ul vechi; au fost scrise texte noi, doar pe baza informațiilor existente (prăjire la foc de lemn, Arabica/Robusta, 1 kg, 150 buc/cutie, unicii importatori Saka și Pareo). Se pot modifica din panou.

---

## 2. Instalare pe cPanel

1. **Backup** la site-ul WordPress actual (cPanel → *Backup* / *JetBackup*). Notează datele BT iPay din plugin (WooCommerce → Setări → Plăți → BT iPay), dacă le mai ai.
2. **Baza de date**: cPanel → *MySQL® Database Wizard* → bază nouă + utilizator cu *ALL PRIVILEGES*.
3. **PHP**: cPanel → *Select PHP Version* → PHP 8.2 sau 8.3, cu extensiile `pdo_mysql`, `mbstring`, `gd`, `zip`, `intl`, `sodium`, `curl`.
4. Mută fișierele WordPress într-un director de rezervă (ex. `public_html/_wordpress_vechi`).
5. Urcă `bunatatidelamichele-<versiune>.zip` în `public_html` și dezarhivează (fișierele direct în `public_html`).
6. Deschide `https://bunatatidelamichele.ro/install/` și completează formularul. Produsele, imaginile, paginile și redirecționările se încarcă automat.
7. **HTTPS**: cPanel → *SSL/TLS Status* → *Run AutoSSL*, apoi decomentează cele 4 linii „HTTPS + domeniu fără www” din `.htaccess`.
8. **Cron** (obligatoriu – anulează plățile cu cardul abandonate și reface stocul): Panou → *Sistem & backup* → copiază comanda în cPanel → *Cron Jobs*, la fiecare 5 minute.

## 3. BT iPay – reactivare

1. Panou → **Setări → Firmă**: CUI, Nr. Reg. Com., adresa sediului, telefon. Banca verifică să apară pe site (apar automat în subsol și în paginile legale).
2. Paginile cerute de bancă și de ANPC sunt deja create: Termeni și condiții, Politica de confidențialitate, Politica de cookies, Livrare, Politica de retur (cu formular de retragere), Metode de plată; siglele Visa/Mastercard/Maestro/BT iPay și insignele ANPC SAL/SOL sunt în subsol.
3. Panou → **Setări → Plăți (BT iPay)**: completează utilizatorul și parola API de **TEST** (primite de la BT), salvează, apasă **Testează conexiunea**.
4. Trimite echipei BT iPay adresa de notificare (callback) afișată pe aceeași pagină.
5. Plasează o comandă de test pe site, cu cardurile de test de la BT. Verifică în Panou → Comenzi.
6. După aprobarea băncii: completează datele de **PRODUCȚIE** și schimbă modul pe **PRODUCȚIE**.

Detalii tehnice:
- API: `register.do` (sau `registerPreAuth.do` în modul „două etape”), `getOrderStatusExtended.do`, `deposit.do`, `reverse.do`, `refund.do`; servere implicite `https://ecclients-sandbox.btrl.ro` (test) și `https://ecclients.btrl.ro` (producție), modificabile din setări.
- 3D Secure 2 forțat (`FORCE_3DS2`), `orderBundle` complet (email, telefon, adresă de livrare și facturare), sumă în bani, monedă 946 (RON).
- Starea plății se citește **mereu de la bancă**, niciodată din parametrii URL. Callback-ul e protejat cu token; opțional și checksum HMAC.
- Din pagina comenzii: verificare stare, încasare preautorizare, anulare autorizare, rambursare totală sau parțială.
- Dacă plata e refuzată, clientul vede motivul (fonduri insuficiente, 3DS etc.) și poate reîncerca sau alege ramburs.
- Parolele API se salvează criptat în baza de date.

## 4. Panoul de control (`/admin`)

- **Tablou de bord**: vânzări azi / 7 / 30 zile, grafic, cele mai vândute produse, stoc scăzut, listă de configurare.
- **Comenzi**: filtre după stare, plată, metodă și dată, căutare; detalii, schimbare stare cu email către client, AWB (Fan Courier, Sameday, Cargus, DPD, GLS, Poșta Română) cu link de urmărire, operații BT iPay, istoric, tipărire, export CSV pentru Excel.
- **Clienți**, **Coduri de reducere** (procent/sumă fixă, livrare gratuită, minim comandă, expirare, număr maxim de utilizări), **Mesaje** din formularul de contact.
- **Produse**: galerie cu mai multe imagini (reordonare prin tragere), preț și preț redus cu dată de expirare, cantitate minimă și pas (ex. cutie de 150), stoc cu scădere automată, specificații, întrebări frecvente, SEO cu previzualizare Google. Prețul și stocul se modifică și direct din listă.
- **Categorii**, **Recenzii** (clienții trimit, tu aprobi), **Prima pagină**, **Pagini**, **Blog**, **Media**.
- **SEO**: setări, audit automat pe fiecare produs și pagină, redirecționări, jurnal 404, IndexNow.
- **Setări**: magazin și livrare (cost, prag livrare gratuită, ridicare personală, minim comandă), plăți, firmă, email SMTP, integrări (GA4, GTM, Google Ads, Meta Pixel, Clarity, Merchant Center).
- **Utilizatori** cu roluri (Administrator, Comenzi și produse, Editor conținut), autentificare în doi pași, **Sistem**: actualizare din .zip, backup, jurnal erori.

## 5. SEO

- Date structurate: `Product` + `Offer` (preț, stoc, cost și termen de livrare, politică de retur), `OnlineStore`, `ItemList`, `FAQPage`, `BreadcrumbList`, `WebSite` cu căutare, recenzii (`AggregateRating`) când există.
- Sitemap cu imaginile produselor, robots.txt, llms.txt, feed Google Merchant Center (`/feed/google-merchant.xml`), Open Graph pentru produse (imagini generate automat).
- Coșul, finalizarea și paginile de comandă nu sunt indexate; variantele de sortare/căutare au canonical către pagina curată.
- Imagini WebP responsive, fonturi găzduite local, cache de pagini, dimensiuni fixe la imagini.

După lansare: verifică domeniul în Google Search Console (codul se pune în Panou → Setări SEO) și trimite `https://bunatatidelamichele.ro/sitemap.xml`; în Merchant Center adaugă feedul de produse.

## 6. Pentru dezvoltator

```
index.php            punct de intrare
install/             instalare
app/Core/            nucleu: DB, Router, Auth, Seo, Mailer, Shop (coș), Orders, BtIpay, Cache, Updater, Backup…
app/Controllers/     Site/ (magazin) și Admin/ (panou)
app/Views/           șabloane: site/, admin/, email/
app/Migrations/      schema și conținutul inițial
app/Data/            catalogul, paginile, setările implicite, imaginile originale
assets/              CSS, JS, fonturi (Jost, Cookie – licență OFL)
```

- Local: `php -S 127.0.0.1:8080 tools/dev-router.php`, apoi `/install/` (poți alege SQLite).
- Simulator BT iPay: `php -S 127.0.0.1:8091 tests/mock-btipay.php`, apoi setează `bt_url_test = http://127.0.0.1:8091`, utilizator `test_api`, parolă `test_pass` (direct în baza de date – panoul acceptă doar https).
- Teste end-to-end (pe o instalare curată; între rulări repetate șterge `storage/ratelimit/`, altfel limita de 12 comenzi/oră de pe același IP blochează testele): `node tests/e2e.cjs http://127.0.0.1:8080 admin@exemplu.ro 'parola'` (comandă cu card aprobat, refuzat → ramburs, ramburs, urmărire, contact, rambursare parțială, AWB, prețuri, cupoane).
- Arhiva pentru cPanel: `php tools/build.php` → `dist/bunatatidelamichele-<versiune>.zip` (bună și pentru instalare, și pentru actualizare din panou).
