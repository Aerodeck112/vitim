# VITIM AI Cloud — reguli de dezvoltare

Platforma multi-tenant pentru agenții AI VITIM. Arhitectura: `../docs/VITIM-AI-ARCHITECTURE.md`.
Site-ul vitim.ro (rădăcina repository-ului) este o aplicație separată — nu importa cod din `../app`.

## Izolarea între clienți (non-negociabil)

- Orice model cu date ale unui client folosește `App\Tenancy\BelongsToOrganization`. `ArchitectureTest` pică altfel.
- Organizația curentă (`TenantContext`) se setează doar din: middleware-ul `org` (membership), rezolvarea cheii de site,
  sau `TenantContext::runAs()` în joburi/servicii de platformă. Niciodată din input de la client sau de la modelul AI.
- `withoutTenancy()` doar în cod de platformă, cu comentariu care explică de ce, și niciodată cu input nevalidat.
- Orice endpoint nou are un test care demonstrează că utilizatorul/cheia din firma A nu vede și nu modifică datele firmei B.
- Pentru nemembri răspundem 404, nu 403 (nu confirmăm existența resurselor altor firme).

## Securitate

- Secrete: cast `encrypted`, `#[Hidden]`, niciodată în loguri, în audit `meta` sau în prompt.
- Tool-urile agentului primesc contextul (organizație, destinatari, URL-uri) de la server, nu de la model.
- Acțiunile administrative și accesul echipei VITIM la datele unui client se scriu prin `AuditLogger`.

## Cod

- PHP 8.3, `declare(strict_types=1)`, clase `final` unde nu e nevoie de moștenire, servicii mici în `app/Services`.
- Fără dependențe noi fără motiv scris în PR. Fără abstracții „pentru viitor”.
- Înainte de commit: `vendor/bin/pint` și `php artisan test` (SQLite); pentru migrări, și pe MySQL/MariaDB.
