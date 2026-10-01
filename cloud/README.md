# VITIM AI Cloud

Backend-ul central al platformei VITIM AI: firme (tenanți), site-uri, agenți, knowledge, conversații, lead-uri.
Arhitectura și roadmap-ul: [`../docs/VITIM-AI-ARCHITECTURE.md`](../docs/VITIM-AI-ARCHITECTURE.md).

## Stare (0.3.0 — Phase 2)

Fundația (Phase 1) și agentul (Phase 2) sunt gata și testate (106 teste, SQLite + MariaDB).
Ce există și ce nu: [`../docs/VITIM-AI-ARCHITECTURE.md` §0](../docs/VITIM-AI-ARCHITECTURE.md),
schema: [`VITIM-AI-DATABASE.md`](../docs/VITIM-AI-DATABASE.md), API: [`VITIM-AI-API.md`](../docs/VITIM-AI-API.md),
securitate: [`VITIM-AI-SECURITY.md`](../docs/VITIM-AI-SECURITY.md). Instalare: [`INSTALL-CPANEL.md`](INSTALL-CPANEL.md).

**Nu este încă un produs vândabil**: agentul răspunde și se poate testa din panou, dar nu e încă pe site-urile clienților (widget, Faza 4); knowledge din pagini web vine în Faza 3.

Evaluarea agentului (API real, costă): `php artisan vitim:eval <slug-firmă> all`.

Date demo (doar în afara producției): `php artisan db:seed` → organizația „VITIM Demo Auto”, parola proprietarului se afișează în consolă.
Test de fum pe o instalare din arhivă: `tests/e2e/install-flow.cjs`.

## Rulare locală

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan test
vendor/bin/pint --test
```

Testele rulează implicit pe SQLite în memorie. Pentru MySQL/MariaDB:

```bash
DB_CONNECTION=mariadb DB_HOST=127.0.0.1 DB_DATABASE=vitim_ai_test DB_USERNAME=... DB_PASSWORD=... php artisan test
```

## De făcut (cunoscut)

- PHPStan/Larastan: nu a putut fi instalat în mediul în care s-a creat proiectul (descărcare blocată); de adăugat în CI.
- `APP_KEY` criptează secretele cheilor de site: pierderea lui = chei de site inutilizabile (trebuie rotite). Backup separat, în afara serverului.
