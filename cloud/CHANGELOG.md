# VITIM AI Cloud — versiuni

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
