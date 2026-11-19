# Conector VITIM pentru site-uri Laravel

Leagă un site făcut în Laravel de panoul VITIM AI: asistentul de chat, vizitatorii „acum pe site”, semnalul orar
(monitorizare) și cererile din formulare, care ajung în panou ca lead-uri. Un singur fișier, fără pachete în plus.

## Instalare

1. În panoul VITIM: fișa clientului → adaugă site-ul → copiază **codul de conectare** (`VITIM1-…`).
2. Copiază `Vitim.php` în `app/Support/Vitim.php` din proiectul site-ului.
3. În `.env` de pe server (nu în repository):

   ```
   VITIM_CONNECTION_CODE=VITIM1-...
   ```

4. În `config/services.php`:

   ```php
   'vitim' => ['code' => env('VITIM_CONNECTION_CODE')],
   ```

5. În layoutul public, înainte de `</body>`:

   ```blade
   {{ \App\Support\Vitim::widget() }}
   ```

6. În `routes/console.php`:

   ```php
   Schedule::call(fn () => \App\Support\Vitim::heartbeat())->hourly();
   ```

7. În controllerul fiecărui formular (contact, ofertă, programare), după ce cererea a fost salvată:

   ```php
   \App\Support\Vitim::lead([
       'name' => $data['name'], 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null,
       'message' => $data['message'] ?? null, 'intent' => 'quote_request', // sau appointment, support, other…
       // doar dacă omul a bifat explicit acordul pentru noutăți:
       'marketing_consent' => (bool) ($data['newsletter'] ?? false), 'consent_text' => 'Sunt de acord să primesc noutăți',
   ]);
   ```

## Ce trebuie știut

- Fără `VITIM_CONNECTION_CODE`, toate metodele nu fac nimic: site-ul merge la fel (și în dezvoltare, și la teste).
- O eroare de rețea spre panou nu oprește niciodată formularul sau cronul site-ului; se scrie doar în log.
- Codul de conectare conține secretul site-ului: stă doar în `.env`, niciodată în repository.
- Datele formularului pleacă spre panoul VITIM: menționează asta în politica de confidențialitate a site-ului.
- Auditul zilnic (SEO, securitate, legal, vizibilitate AI) și citirea site-ului de către asistent se fac din panou,
  fără nimic instalat. Remedierile automate și actualizările la buton există doar pentru WordPress.
