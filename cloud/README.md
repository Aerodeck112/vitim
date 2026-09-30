# VITIM AI Cloud

Backend-ul central al platformei VITIM AI: firme (tenanți), site-uri, agenți, knowledge, conversații, lead-uri.
Arhitectura și roadmap-ul: [`../docs/VITIM-AI-ARCHITECTURE.md`](../docs/VITIM-AI-ARCHITECTURE.md).

## Stare

| Faza | Ce există | Stare |
|---|---|---|
| 1.1 | Proiect Laravel 13, Pint, teste, CI | gata (PHPStan: de adăugat, vezi mai jos) |
| 1.3 | Izolare multi-tenant: `TenantContext`, `BelongsToOrganization`, scope fail-closed, teste de izolare + test de arhitectură | gata |
| 1.4 | Roluri și permisiuni (Gate-uri), audit log, acces al echipei VITIM auditat | gata (fără ecrane de login / 2FA încă) |
| 1.5 | Site-uri, chei publice + secrete criptate, rotație, revocare, verificare Origin, semnături HMAC anti-replay, abonament de probă și limite de plan | gata (fără UI) |
| 1.2, 1.6 | Server de staging, dashboard | următorii pași |

**Nu este gata de producție.** Lipsesc încă: autentificarea web (login, 2FA), dashboardul, agentul, knowledge, widgetul.

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
