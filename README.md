# VITIM – site, CRM și email marketing

Site-ul companiei VITIM (vitim.ro), cu panou de control, CRM, email marketing și SEO avansat. Rulează pe orice hosting cu cPanel (PHP 8.1+ și MySQL), fără Node.js, fără Composer pe server și fără compilare.

---

## 1. Instalare pe cPanel (prima dată)

1. **Creează baza de date**: cPanel → *MySQL® Database Wizard*
   - baza de date, de exemplu `cpaneluser_vitim`
   - un utilizator cu o parolă puternică
   - bifează **ALL PRIVILEGES**
   - notează numele bazei, utilizatorul și parola.
2. **Verifică versiunea PHP**: cPanel → *Select PHP Version* (sau *MultiPHP Manager*). Alege **PHP 8.2 sau 8.3** și asigură-te că sunt bifate extensiile `pdo_mysql`, `mbstring`, `gd`, `zip`, `intl`, `sodium`.
3. **Fă backup la site-ul vechi**: cPanel → *Backup* sau *JetBackup*. Mută fișierele WordPress vechi într-un director separat, de exemplu `public_html/_vechi`, sau șterge-le după backup.
4. **Urcă arhiva**:
   - cPanel → *File Manager* → `public_html`
   - *Upload* → `vitim-1.0.0.zip`
   - click dreapta pe arhivă → *Extract*.
   - Fișierele trebuie să ajungă direct în `public_html`, adică `public_html/index.php`, nu într-un subdirector.
5. **Rulează instalatorul**: deschide `https://vitim.ro/install/` și completează formularul (baza de date, datele firmei, contul de administrator).
6. **Activează HTTPS**:
   - cPanel → *SSL/TLS Status* → *Run AutoSSL*
   - apoi, în fișierul `.htaccess`, decomentează cele 4 linii din secțiunea „HTTPS + domeniu fără www”.

După instalare, directorul `install/` se blochează automat. Poți să-l și ștergi.

### Primii pași în panou (`https://vitim.ro/admin`)

Tabloul de bord îți arată o listă de configurare. Pe scurt:

- **Setări → Firmă**: CUI, Nr. Reg. Com., adresa exactă, coordonatele sediului, linkul Google Business.
- **Setări → Email & SMTP**: contul de email din cPanel. Folosește butonul „Trimite test”.
- **Contul meu → Autentificare în doi pași**: activeaz-o.
- **Sistem**: copiază comanda de cron în cPanel → *Cron Jobs*, la fiecare 5 minute.
- **Setări → Integrări**: Google Analytics 4 sau Tag Manager, Google Ads, Meta Pixel.
- **Setări SEO**: codul de verificare Google Search Console. Apoi trimite `https://vitim.ro/sitemap.xml` în Search Console.
- **Setări → Aspect**: încarcă logo-ul.
- **Testimoniale**: adaugă păreri reale ale clienților.
- **Setări → Asistent AI** (opțional): pune cheia API de la console.anthropic.com → *API Keys*, bifează „Activat” și apasă „Testează conexiunea” din Panou → Asistent AI.

---

## 2. Actualizări (fără FTP)

1. Primești arhiva nouă, de exemplu `vitim-1.1.0.zip`.
2. Panou → **Sistem & actualizări** → *Actualizare din arhivă .zip* → alegi fișierul → **Actualizează**.
3. Sistemul face automat:
   - backup la cod și la baza de date
   - înlocuirea fișierelor
   - rularea migrărilor.
4. Conținutul, CRM-ul, imaginile și setările nu sunt atinse.

Dacă ceva nu merge, apasă **Restaurează** la backup-ul de cod din aceeași pagină.

> Dacă arhiva depășește limita de upload, mărește `upload_max_filesize` și `post_max_size` din cPanel → *MultiPHP INI Editor* (de exemplu la 64M).

---

## 3. Ce conține

| Zonă | Funcții |
|---|---|
| **Site** | Prima pagină, 15 servicii, zone (3 județe + 16 localități), blog, proiecte, despre, contact, pagini legale, 404/410 |
| **SEO** | Date structurate schema.org, sitemap cu imagini, robots.txt, llms.txt, IndexNow, imagini OG automate, canonical, cache de pagini, WebP, redirecționări, audit, jurnal 404 |
| **CRM** | Pipeline Kanban, contacte, istoric activități, sarcini, email din fișa clientului, surse lead-uri (Google Ads / Meta / organic / AI), import/export CSV, ștergere GDPR |
| **Email marketing** | Newsletter (double opt-in) și notificări către clienți, segmentare (status, județ, etichete), programare, trimitere în loturi, rapoarte de deschideri și click-uri, dezabonare cu un click |
| **Asistent AI** | Chat pe site care răspunde din conținutul site-ului, colectează cereri de ofertă în CRM (cu acord), istoric conversații în panou, limite anti-abuz, ștergere automată după 12 luni |
| **Securitate** | 2FA, roluri (admin / editor / vânzări), CSRF, rate limiting, anti-spam fără captcha (opțional Cloudflare Turnstile), parole SMTP criptate, headere de securitate |
| **Sistem** | Actualizare din .zip, backup-uri, cron, jurnal erori |

### Adresele vechi WordPress

Paginile vechi (`/suport-it-mures-telefonic/`, `/seo-optimizare-google/`, `/vitim-despre-noi/` etc.) sunt redirecționate 301 către paginile noi. Paginile demo ale temei (Sample Page, Team, Personal CV, produsele demo etc.) răspund cu **410**, ca Google să le scoată din index. Lista se poate modifica din Panou → Redirecționări.

---

## 4. Pentru dezvoltator

```
index.php            punct de intrare (toate cererile trec pe aici)
install/             asistentul de instalare
app/Core/            nucleu: DB, Router, Auth, Seo, Mailer, Newsletter, Crm, Cache, Updater, Backup…
app/Controllers/     Site/ (public) și Admin/ (panou)
app/Views/           șabloane PHP: site/, admin/, email/
app/Migrations/      migrări (rulate la instalare și după fiecare actualizare)
app/Data/            conținutul inițial și setările implicite
assets/              CSS, JS, fonturi (Geist), iconițe
uploads/             imaginile încărcate (nu se suprascriu la actualizare)
storage/             cache, jurnale, backup-uri, SQLite (nu se suprascriu)
```

- **Rulare locală**: `php -S 127.0.0.1:8080 tools/dev-router.php`, apoi deschide `/install/`. Poți alege SQLite, fără server MySQL.
- **Arhiva pentru cPanel**: `php tools/build.php` → `dist/vitim-<versiune>.zip`. Aceeași arhivă e bună și pentru instalare, și pentru actualizare.
- **Versiune nouă**:
  - crește numărul din `VERSION`
  - adaugă migrările noi în `app/Migrations/NNNN_nume.php`
  - notează schimbările în `CHANGELOG.md`
  - rulează `php tools/build.php`
  - fișierele eliminate se listează în `REMOVED.txt`, ca să fie șterse la actualizare.
- **Asistentul în teste**: `php -S 127.0.0.1:8090 tests/mock-anthropic.php` imită API-ul; adaugă `'ai_base_url' => 'http://127.0.0.1:8090'` în `app/config.php` al instalării de test.
- **Teste end-to-end** (Playwright): pornește serverul local, instalează, apoi rulează `node tests/e2e.cjs http://127.0.0.1:8080 admin@exemplu.ro 'parola'`. Testele acoperă paginile publice, formularul, newsletterul, toate paginile panoului, CRM-ul, editorul de conținut, media, setările, campaniile, auditul SEO și backup-ul.
- **Compatibilitate**: PHP 8.1+, MySQL 5.7+ / MariaDB 10.3+ sau SQLite 3.
