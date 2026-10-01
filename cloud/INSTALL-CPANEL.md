# Instalare VITIM AI pe cPanel

Durează ~20 de minute, o singură dată. Nu e nevoie de terminal.

## 1. Subdomeniul și PHP

1. cPanel → **Domains** (sau Subdomains) → creează `ai.vitim.ro`.
   La **Document Root** scrie: `vitim-ai/public`. Codul stă în `vitim-ai`, în afara `public_html`.
2. cPanel → **MultiPHP Manager** (sau Select PHP Version) → pentru `ai.vitim.ro` alege **PHP 8.3** sau mai nou.
   Extensii necesare: pdo_mysql, mbstring, openssl, sodium, zip, intl, fileinfo.
3. cPanel → **SSL/TLS Status** → rulează AutoSSL pentru `ai.vitim.ro` (https e obligatoriu).

## 2. Baza de date

cPanel → **MySQL® Database Wizard**:
- bază nouă, de exemplu `vitim_ai`;
- utilizator nou, de exemplu `vitim_aiuser`, cu parolă generată;
- **ALL PRIVILEGES** doar pe această bază.

Folosește o bază separată de cea a site-ului vitim.ro.

## 3. Fișierele

1. File Manager → în directorul principal (Home Directory, de exemplu `/home/<cont>` sau `/home2/<cont>`, nu în `public_html`) creează folderul `vitim-ai`.
2. Urcă `vitim-ai-<versiune>.zip` în `vitim-ai` → clic dreapta → **Extract**.
3. Șterge arhiva zip.
4. În `vitim-ai`, copiază `.env.cpanel.example` ca `.env` și completează liniile marcate **COMPLETEAZĂ**:
   - `APP_URL`, datele bazei (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`);
   - emailul (un cont din cPanel → Email Accounts, de exemplu `noreply@vitim.ro`);
   - `SETUP_TOKEN`: minimum 16 caractere aleatorii;
   - `BACKUP_EMAIL`: o adresă **din afara hostingului** (Gmail/Outlook);
   - `ANTHROPIC_API_KEY`: cheia pentru agentul AI (de la console.anthropic.com). Se poate adăuga și mai târziu; fără ea, agentul răspunde doar cu datele de contact ale firmei.

   Fișierele care încep cu punct apar în File Manager din Settings → Show Hidden Files.

## 4. Cron-ul (o singură linie)

cPanel → **Cron Jobs** → Common Settings: **Once Per Minute** → Command:

```
cd ~/vitim-ai && /opt/cpanel/ea-php84/root/usr/bin/php artisan vitim:deploy >/dev/null 2>&1 && /opt/cpanel/ea-php84/root/usr/bin/php artisan schedule:run >/dev/null 2>&1
```

- `~` înseamnă directorul principal al contului (de exemplu `/home2/vitim`), deci comanda merge fără modificări.
- Calea PHP trebuie să fie aceeași versiune ca a site-ului (MultiPHP Manager). `/usr/local/bin/php` poate fi altă versiune, iar atunci cron-ul se oprește fără niciun mesaj. Pentru PHP 8.3: `/opt/cpanel/ea-php83/root/usr/bin/php`.
- Fără Terminal și fără cron: urcă `tools/verificare.php` în `public/` și deschide `/verificare.php?token=SETUP_TOKEN&deploy=1`, apoi șterge fișierul.

În primul minut, cron-ul generează cheia aplicației (`APP_KEY` în `.env`) și creează tabelele. Tot el rulează coada de sarcini, backup-ul zilnic (03:17) și actualizările.

## 5. Primul administrator

1. După 1–2 minute deschide **https://ai.vitim.ro/setup**.
2. Completează tokenul (`SETUP_TOKEN`), numele, emailul și parola.
3. Activează autentificarea în doi pași (obligatorie pentru echipa VITIM): Google Authenticator, Microsoft Authenticator sau 2FAS.
4. **Șterge valoarea `SETUP_TOKEN` din `.env`.** Oricum, pagina `/setup` se blochează singură după primul cont.

## Actualizări

**Din panou (de la 0.4.0):** Admin → **Sistem** → alegi arhiva `vitim-ai-<versiune>.zip` → parola ta → **Actualizează**.
Se face backup la baza de date, se înlocuiesc fișierele și se rulează migrările. `.env` și `storage/` nu se ating.
Dacă arhiva e mai mare decât limita de upload afișată în pagină, urc-o cu File Manager în
`vitim-ai/storage/app/updates/`, reîncarcă pagina Sistem și apasă **Aplică**.

**Manual (sau pentru versiuni mai vechi de 0.4.0):** urcă arhiva în `vitim-ai` → Extract (suprascrie fișierele).
În cel mult un minut, cron-ul vede versiunea nouă și rulează migrările.

## Conectarea site-urilor clienților

1. Admin → client → **Site-uri** → adaugă domeniul. Copiază **codul de conectare** (`VITIM1-…`), afișat o singură dată.
   Dacă l-ai pierdut: „Schimbă cheile” la site și primești unul nou (vechiul nu mai merge).
2. **Site WordPress**: descarcă „pluginul WordPress” din fișa clientului → în WordPress: Module → Adaugă nou → Încarcă modul →
   Activează → **Setări → VITIM** → lipește codul → **Conectează**. Starea apare în panou imediat.
3. **Site PHP** (făcut de noi): descarcă „conectorul PHP”, completează în fișier codul și adresa site-ului, urcă-l în folderul
   principal al contului de hosting (nu în `public_html`) și adaugă un cron **Once Per Hour**: `php ~/vitim-connector.php`.

**Probleme și remedieri (de la 0.7.0, plugin 1.1.0):** Admin → client → click pe domeniu. Pluginul scanează zilnic; „Scanează acum” pornește o scanare
imediat. Remedierile se aplică din aceeași pagină. Clientul le poate opri din WordPress (Setări → VITIM). Pluginul 1.0.0 trebuie înlocuit o dată
manual cu 1.1.0 (Module → Adaugă nou → Încarcă modul → „Înlocuiește versiunea curentă”); de la 1.1.0 încolo se actualizează din WordPress.

## Backup și restaurare

- Zilnic la 03:17, baza de date pleacă pe `BACKUP_EMAIL` și rămâne și în `vitim-ai/storage/app/backups` (ultimele 7).
- **Păstrează separat și fișierul `.env`**: `APP_KEY` din el decriptează cheile site-urilor și codurile 2FA. Fără el, backup-ul bazei nu ajunge.
- Restaurare: bază goală → phpMyAdmin → Import → fișierul `db-....sql.gz`.

## Verificare

- `https://ai.vitim.ro/up` trebuie să răspundă „Application up”.
- Dacă ceva nu merge: `vitim-ai/storage/logs/laravel-<data>.log`.
