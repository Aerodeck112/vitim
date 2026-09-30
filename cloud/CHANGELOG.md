# VITIM AI Cloud — versiuni

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
