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

1. Urcă noua arhivă `vitim-ai-<versiune>.zip` în `vitim-ai` → Extract (suprascrie fișierele).
   `.env` și `storage/` nu se ating.
2. În cel mult un minut, cron-ul vede versiunea nouă și rulează migrările.

## Backup și restaurare

- Zilnic la 03:17, baza de date pleacă pe `BACKUP_EMAIL` și rămâne și în `vitim-ai/storage/app/backups` (ultimele 7).
- **Păstrează separat și fișierul `.env`**: `APP_KEY` din el decriptează cheile site-urilor și codurile 2FA. Fără el, backup-ul bazei nu ajunge.
- Restaurare: bază goală → phpMyAdmin → Import → fișierul `db-....sql.gz`.

## Verificare

- `https://ai.vitim.ro/up` trebuie să răspundă „Application up”.
- Dacă ceva nu merge: `vitim-ai/storage/logs/laravel-<data>.log`.
