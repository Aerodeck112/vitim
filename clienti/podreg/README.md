# PODREG: pachetul de reparații „Faza 0”

Rezolvă problemele urgente din [auditul podreg.ro](../../docs/AUDIT-PODREG-2026.md) fără redesign și fără să modifice tema, Elementor sau conținutul din baza de date. Se instalează ca **must-use plugin**: nu apare în lista de pluginuri, nu poate fi dezactivat din greșeală și se scoate ștergând fișierele.

Testat pe WordPress 7.1.2 (versiunea de pe podreg.ro) cu PHP 8.3.

## Ce rezolvă automat

| Problemă din audit | Ce face pachetul |
|---|---|
| Social media duce la ThemeREX | înlocuiește link-urile cu profilurile din `config.php`; rețelele fără profil dispar |
| „Have a Project? info@website.com / Go to Shop” în meniul mobil | elimină widget-ul demo |
| Link-ul GDPR din formulare duce la o pagină 404, iar `/privacy-policy/` e politica ThemeREX | servește politica PODREG la `/politica-de-confidentialitate/`; `/privacy-policy/` face redirect 301 acolo |
| „Mesajul ajunge la … office@vitim.ro” | text neutru pentru vizitator |
| `/service-plus/`, `/our-clients/`, `/shop/`, `/404-page/`, `/layouts/*` indexate | răspund 410 (Gone) vizitatorilor și ies din sitemap; administratorii le văd în continuare |
| Fără meta description, titluri lungi | titluri și descrieri scrise manual pentru paginile principale, generate din conținut pentru cele 38 de proiecte |
| Zero schema markup | `HomeAndConstructionBusiness` + `BreadcrumbList` |
| Open Graph incomplet sau duplicat | set complet de etichete `og:*` pe fiecare pagină (titlu, descriere, URL, imagine), fără duplicatele temei |
| Utilizatorul `vitim` expus (REST, `?author=1`, sitemap, oEmbed) | blocat pentru vizitatori; arhivele de autor fac redirect către homepage |
| Versiunile WordPress / WooCommerce / Elementor / RevSlider expuse | `generator` eliminat, `?ver=` al nucleului înlocuit cu un hash |
| Fără header-e de securitate | HSTS, nosniff, X-Frame-Options, Referrer-Policy, Permissions-Policy |
| Formularele pot fi abuzate | maximum 5 trimiteri la 10 minute de pe același IP |
| WooCommerce, wishlist și quick view încărcate pe toate paginile | se încarcă doar pe paginile de magazin |
| Fără CTA pe paginile de proiect | bloc „Construim pentru tine o variantă ca …” la final, cu Cere ofertă / WhatsApp / telefon, cu numele modelului transmis mai departe |
| Fără CTA sticky | bară jos pe mobil (Sună · WhatsApp · Cere ofertă), buton flotant pe desktop |
| Formularele nu știu de unde vine cererea | `?model=…` precompletează mesajul în formularul de contact și în configurator |
| Nimic nu se măsoară | GTM + Consent Mode v2 + banner de cookie-uri + evenimentele `generate_lead`, `click_tel`, `click_whatsapp`, `click_email`, `click_oferta` (după ce se completează `gtm_id`) |
| Texte în engleză în footer („Hello”, „Get in touch”, „All rights reserved”, „Scroll Down”) | traduse pe versiunea română |
| Lipsesc datele firmei | linie legală în footer (completă după ce se trec CUI și Nr. Reg. Com. în `config.php`) |

## Instalare (aproximativ 10 minute)

1. **Backup** din cPanel → JetBackup (fișiere și bază de date).
2. Completează `mu-plugins/podreg-faza0/config.php`: datele firmei, profilurile sociale reale și, dacă există, ID-ul de Google Tag Manager.
3. În cPanel → File Manager → `public_html/wp-content/`, creează directorul `mu-plugins` dacă nu există.
4. Urcă în `wp-content/mu-plugins/` **atât** fișierul `podreg-faza0.php`, **cât și** directorul `podreg-faza0/`.
5. Adaugă regulile din `htaccess-podreg.txt` la începutul fișierului `.htaccess` din `public_html` (deasupra `# BEGIN WordPress`).
6. Golește cache-ul (dacă există un plugin de cache) și verifică pe mobil și pe desktop:
   - homepage, o cabană, o scară, configuratorul, contactul;
   - `https://podreg.ro/politica-de-confidentialitate/`;
   - `https://podreg.ro/service-plus/` trebuie să dea „pagina nu există”;
   - trimite un formular de test.
7. În Google Search Console: trimite din nou `https://podreg.ro/wp-sitemap.xml`.

**Dacă ceva arată greșit:** în `config.php` → `module`, pune `false` la modulul suspect (de obicei `performanta` sau `curatenie`). Pentru dezactivarea completă, șterge `podreg-faza0.php` din `mu-plugins`.

## Ce NU poate rezolva pachetul (necesită acces în admin sau decizii)

### Decizii PODREG
- [ ] **Prețuri:** configuratorul spune 375 €/m² cu manoperă, Cabana Poșaga ~543 €/m² fără manoperă. Trebuie stabilit ce include fiecare variantă (kit / la roșu / la cheie).
- [ ] **Datele legale** (denumire, CUI, Nr. Reg. Com.) în `config.php`.
- [ ] **Profilurile sociale** reale și linkul Google Business Profile.
- [ ] **Politica de confidențialitate:** textul din `inc/politica-text.php` e un model întocmit pe baza formularelor actuale. Trebuie citit și confirmat de PODREG, ideal și de un jurist.
- [ ] **Brand:** PODREG vs. „Transilvania Case Lemn” în logo.

### Din WordPress admin (Elementor / opțiunile temei)
- [ ] **Despre noi:** pagina e goală. Trebuie scris conținut (istorie, atelier, echipă, fotografii).
- [ ] Ștergerea definitivă (la coș) a paginilor `service-plus`, `our-clients` și a paginii ThemeREX `privacy-policy`, după ce verificați că nu mai sunt legate din meniuri.
- [ ] **H1:** homepage-ul are 10 H1. Doar primul titlu rămâne H1; celelalte trec pe H2 (Elementor → widget Heading → HTML Tag). Paginile de foișoare, terase, scări și tiny house nu au niciun H1.
- [ ] **Alt text** la imaginile din Media Library (60–75% lipsesc pe proiecte).
- [ ] Elementor → Settings → Advanced → **Google Fonts Load: Swap**; Performance → **Improved CSS Loading** și **Element Caching** pe On.
- [ ] Opțiunile temei → Socials: profilurile reale (după asta curățarea din pachet devine inutilă, dar nu strică).
- [ ] Textul cardului „Cabane și case de lemn” de pe homepage („două condiții cumulative…”) trebuie reformulat pozitiv.
- [ ] Configuratorul: diacritice și eliminarea frazei „Prețul apare doar în email”, care contrazice formula afișată.
- [ ] Imaginile mari (de ex. `A7401943-Edit-2048x2048-1.jpg`, 1,3 MB): convertire în WebP/AVIF (de ex. cu un plugin de optimizare de imagini).
- [ ] Un plugin de page cache: LiteSpeed Cache dacă serverul e LiteSpeed (are HTTP/3, deci probabil e).
- [ ] Dacă nu se vinde nimic online: dezactivarea WooCommerce, TI Wishlist și WooSmart Quick View.

## Structura

```
mu-plugins/
├── podreg-faza0.php            încărcătorul
└── podreg-faza0/
    ├── config.php              SINGURUL fișier de editat
    ├── inc/
    │   ├── helpers.php         funcții comune, buffer HTML
    │   ├── securitate.php
    │   ├── seo.php
    │   ├── curatenie.php
    │   ├── conversie.php
    │   ├── performanta.php
    │   ├── legal.php
    │   └── politica-text.php   textul politicii de confidențialitate
    └── assets/podreg.css, podreg.js
htaccess-podreg.txt             reguli pentru server
```
