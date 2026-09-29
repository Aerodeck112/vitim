# Istoric versiuni

## 1.1.0 — asistent AI și fotografii

- **Asistent virtual pe site**: răspunde vizitatorilor 24/7 din serviciile, zonele, întrebările frecvente și articolele site-ului. Când cineva vrea ofertă, cere acordul, salvează cererea în CRM (sursa „Asistent AI”) și trimite notificare pe email. Conversațiile se văd în Panou → Asistent AI.
- Setări noi în Setări → Asistent AI: cheie API (salvată criptat), model, nume, mesaj de întâmpinare, întrebări sugerate, instrucțiuni suplimentare, limite anti-abuz.
- 20 de fotografii profesionale (licență gratuită Unsplash/Pexels), optimizate WebP, pe prima pagină, pe servicii și pe articole. Se pot înlocui oricând din panou cu poze proprii.
- Prima pagină are o fotografie reală în loc de consola animată; titlurile nu mai folosesc gradient peste tot, ca să arate mai sobru.
- Setări → Aspect: fotografie pentru prima pagină și pentru pagina Despre noi.
- Politica de confidențialitate are o secțiune despre asistentul virtual; conversațiile mai vechi de 12 luni se șterg automat (cron).

## 1.0.0 — prima versiune

**Site public**
- Design nou 2026: temă întunecată/luminoasă, animații discrete, optimizat pentru mobil (bară de acțiuni rapide: Sună / WhatsApp / Ofertă).
- 15 pagini de servicii (IT, securitate, recuperări de date, SEO, marketing tehnic, Google Ads, Meta Ads, Google Workspace, site-uri, agenți AI, integrare AI, automatizări, AI pentru firme).
- Pagini locale pentru județele Mureș, Bistrița-Năsăud, Alba și 16 localități.
- Blog cu cuprins automat, articole programate, RSS.
- Formular de ofertă cu urmărirea sursei (Google Ads, Meta, organic, AI), anti-spam fără captcha, răspuns automat.
- Pagini legale: confidențialitate (GDPR), cookies, termeni; banner de cookies cu Google Consent Mode v2.

**SEO avansat**
- Date structurate schema.org (LocalBusiness, Service, FAQPage, BlogPosting, BreadcrumbList).
- Sitemap XML cu imagini, robots.txt, llms.txt pentru motoarele AI, IndexNow, imagini Open Graph generate automat.
- Cache de pagini (răspuns în câteva milisecunde), imagini WebP responsive.
- Redirecționări 301 pentru toate adresele vechi ale site-ului WordPress; 410 pentru conținutul demo.
- Audit SEO automat în panou, jurnal 404, redirecționări automate la schimbarea adresei unei pagini.

**Panou de control**
- Conținut: servicii, zone, blog, pagini, proiecte, testimoniale, media (conversie automată WebP).
- CRM: pipeline Kanban, contacte cu istoric, sarcini, email din fișa clientului, import/export CSV, ștergere GDPR.
- Email marketing: campanii cu segmentare (abonați, clienți, județ, etichete), trimitere în loturi, programare, rapoarte deschideri/click-uri, dezabonare cu un click, double opt-in.
- Securitate: autentificare în doi pași (TOTP), roluri, protecție CSRF, limitare încercări, parole SMTP criptate.
- Sistem: actualizare din arhivă .zip cu backup automat, backup-uri (bază de date, imagini, cod), cron.
