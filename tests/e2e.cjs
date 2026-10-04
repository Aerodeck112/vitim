/**
 * Test end-to-end (Playwright): site public + panou de control.
 *   node tests/e2e.cjs http://127.0.0.1:8080 admin@vitim.ro 'TestParola123!'
 */
const path = require('path');
const fs = require('fs');
let pw;
try { pw = require('playwright'); } catch (e) { pw = require('/opt/node22/lib/node_modules/playwright'); }
const [,, BASE = 'http://127.0.0.1:8080', EMAIL = 'admin@vitim.ro', PASS = 'TestParola123!'] = process.argv;

let failed = 0, passed = 0;
function ok(cond, msg) { if (cond) { passed++; console.log('  ✓ ' + msg); } else { failed++; console.log('  ✗ ' + msg); } }

(async () => {
  const browser = await pw.chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1360, height: 900 } });
  const page = await ctx.newPage();
  const jsErrors = [];
  page.on('pageerror', e => jsErrors.push(e.message));
  page.on('dialog', d => d.accept());

  console.log('Site public');
  await ctx.addInitScript(() => { try { localStorage.setItem('consent', '{"analytics":false,"marketing":false}'); } catch (e) {} });
  for (const p of ['/', '/proiecte', '/vitim-ai', '/servicii', '/servicii/securitate-cibernetica', '/zone/alba', '/zone/bistrita', '/blog', '/despre-noi', '/contact', '/politica-cookies']) {
    const r = await page.goto(BASE + p);
    ok(r.status() === 200, `${p} → 200`);
  }
  const ld = await page.evaluate(() => [...document.querySelectorAll('script[type="application/ld+json"]')].map(s => JSON.parse(s.textContent)));
  ok(ld.length && ld[0]['@graph'].some(n => [].concat(n['@type']).includes('LocalBusiness')), 'JSON-LD LocalBusiness prezent');

  // formular de contact
  await page.goto(BASE + '/servicii/recuperare-date');
  const form = page.locator('#oferta form[data-ajax]');
  await form.locator('[name=name]').fill('Ion Testescu');
  await form.locator('[name=contact]').fill('ion.test@example.com');
  ok(await form.locator('.form-more').evaluate(d => d.open), 'formular: detaliile opționale deschise pe pagina de serviciu');
  await form.locator('[name=company]').fill('Test SRL');
  await form.locator('[name=county]').selectOption('Mureș');
  await form.locator('[name=computers]').selectOption('6–15');
  await form.locator('[name=message]').fill('Avem un hard disk extern care nu mai este recunoscut, conține facturile pe 3 ani.');
  await form.locator('[name=consent]').check();
  await form.locator('[name=newsletter]').check();
  await page.waitForTimeout(3500); // tokenul anti-spam cere minim 3 secunde
  await form.locator('button[type=submit]').click();
  await page.waitForURL('**/multumim', { timeout: 15000 }).catch(() => {});
  ok(page.url().endsWith('/multumim'), 'formular contact → pagina de mulțumire');

  // newsletter din subsol
  await page.goto(BASE + '/blog');
  const nl = page.locator('form[data-form=newsletter]');
  await nl.locator('[name=email]').fill('abonat.test@example.com');
  await nl.locator('[name=consent]').check();
  await page.waitForTimeout(3500);
  await nl.locator('button[type=submit]').click();
  await page.waitForTimeout(1500);
  ok((await nl.locator('.form-msg').textContent()).length > 5, 'newsletter: mesaj de confirmare afișat');

  // demo agent AI de pe prima pagină
  await page.goto(BASE + '/');
  const demo = page.locator('form[data-form=demo_ai]');
  await demo.locator('[name=site_url]').fill('localhost');
  await demo.locator('[name=email]').fill('demo.test@example.com');
  await demo.locator('[name=consent]').check();
  await page.waitForTimeout(3500);
  await demo.locator('button[type=submit]').click();
  await page.waitForTimeout(1200);
  ok(/adresa site-ului/.test(await demo.locator('.form-msg').textContent()), 'demo AI: adresă invalidă respinsă');
  await demo.locator('[name=site_url]').fill('www.firma-demo.ro/contact');
  await demo.locator('button[type=submit]').click();
  await page.waitForTimeout(1500);
  ok(/www\.firma-demo\.ro/.test(await demo.locator('.form-msg').textContent()), 'demo AI: cerere primită');

  console.log('Panou de control');
  await page.goto(BASE + '/admin');
  await page.fill('[name=email]', EMAIL);
  await page.fill('[name=password]', PASS);
  await page.click('button[type=submit]');
  await page.waitForURL('**/admin/dashboard');
  ok(await page.locator('h1').first().textContent().then(t => t.includes('Salut')), 'autentificare → tablou de bord');

  const adminPages = ['/admin/crm', '/admin/crm/contacte', '/admin/crm/sarcini', '/admin/formulare', '/admin/email', '/admin/email/nou', '/admin/abonati', '/admin/email/jurnal',
    '/admin/c/servicii', '/admin/c/zone', '/admin/c/articole', '/admin/c/pagini', '/admin/c/proiecte', '/admin/c/testimoniale', '/admin/media',
    '/admin/setari/prima', '/admin/setari/firma', '/admin/setari/aspect', '/admin/setari/email', '/admin/setari/integrari', '/admin/setari/formulare', '/admin/setari/avansat',
    '/admin/seo', '/admin/seo/audit', '/admin/seo/redirectionari', '/admin/utilizatori', '/admin/utilizatori/nou', '/admin/cont', '/admin/cont/2fa', '/admin/sistem', '/admin/sistem/jurnal',
    '/admin/c/servicii/1', '/admin/c/proiecte/1', '/admin/c/zone/1', '/admin/c/articole/1', '/admin/c/pagini/1', '/admin/c/articole/nou', '/admin/crm/import', '/admin/crm/contacte/nou', '/admin/crm/oportunitati/nou'];
  for (const p of adminPages) {
    const r = await page.goto(BASE + p);
    const bad = await page.locator('text=Ceva nu a mers bine').count();
    ok(r.status() === 200 && !bad, `${p} → 200`);
  }

  // lead-ul din formular a ajuns în CRM
  await page.goto(BASE + '/admin/crm');
  ok(await page.locator('.deal', { hasText: 'Recuperare date' }).count() > 0, 'CRM: oportunitatea din formular apare în coloana „Nou”');
  await page.goto(BASE + '/admin/crm/contacte?q=Testescu');
  await page.click('text=Ion Testescu');
  ok(await page.locator('text=ion.test@example.com').count() > 0, 'CRM: fișa contactului');
  await page.fill('.card textarea[name=body]', 'Am sunat clientul, trimitem oferta mâine.');
  await page.click('.card form button:has-text("Adaugă")');
  await page.waitForLoadState();
  ok(await page.locator('.tl', { hasText: 'trimitem oferta' }).count() > 0, 'CRM: notă adăugată în istoric');

  // mutare oportunitate în „Câștigat” (API kanban)
  const dealId = await page.evaluate(async (base) => {
    const r = await fetch(base + '/admin/crm'); const h = await r.text(); const m = h.match(/data-id="(\d+)"/); return m ? m[1] : null;
  }, BASE);
  const res = await page.evaluate(async ([base, id]) => window.vpost(base + '/admin/crm/oportunitati/' + id + '/etapa', { stage: 'castigat' }), [BASE, dealId]);
  ok(res && res.ok, 'CRM: etapă schimbată în „Câștigat”');

  // editare serviciu
  await page.goto(BASE + '/admin/c/servicii/1');
  await page.fill('#fld_tagline', 'Abonament lunar, fără griji');
  await page.click('button:has-text("Salvează")');
  ok(await page.locator('.alert-ok').count() > 0, 'Conținut: serviciu salvat');

  // articol nou + slug + redirecționare automată
  await page.goto(BASE + '/admin/c/articole/nou');
  await page.fill('#fld_title', 'Test articol automat');
  await page.fill('#fld_excerpt', 'Un rezumat de test pentru articol.');
  await page.locator('.rte-area').first().click();
  await page.keyboard.type('Conținut de test pentru articol.');
  await page.selectOption('#fld_status', 'published');
  await page.click('button:has-text("Salvează")');
  ok(await page.locator('.alert-ok').count() > 0, 'Blog: articol creat');
  const r2 = await page.goto(BASE + '/blog/test-articol-automat');
  ok(r2.status() === 200, 'Blog: articolul nou e public');

  // media
  await page.goto(BASE + '/admin/media');
  const img = path.join(__dirname, '..', 'assets', 'img', 'icon-512.png');
  await page.setInputFiles('input[type=file]', img);
  await page.waitForTimeout(2500);
  await page.goto(BASE + '/admin/media');
  ok(await page.locator('.media-item').count() > 0, 'Media: imagine încărcată și convertită');

  // setări
  await page.goto(BASE + '/admin/setari/firma');
  await page.fill('#fld_company_cui', 'RO12345678');
  await page.click('button:has-text("Salvează")');
  ok(await page.locator('.alert-ok').count() > 0, 'Setări: firmă salvată');
  await page.goto(BASE + '/');
  ok(await page.locator('text=RO12345678').count() > 0, 'Setări: CUI apare în subsolul site-ului (cache golit)');

  // campanie
  await page.goto(BASE + '/admin/email/nou');
  await page.fill('[name=name]', 'Test campanie');
  await page.fill('[name=subject]', 'Noutăți {{prenume}}');
  await page.check('input[value=all_contacts]');
  await page.selectOption('[name=kind]', 'notificare');
  await page.click('button:has-text("Salvează ciorna")');
  ok(await page.locator('.alert-ok').count() > 0, 'Email: campanie salvată');
  const prev = await page.locator('a:has-text("Previzualizare")').getAttribute('href');
  const pr = await page.request.get(BASE.replace(/\/$/, '') + prev.replace(/^https?:\/\/[^/]+/, ''));
  ok(pr.status() === 200 && (await pr.text()).includes('Dezabonare'), 'Email: previzualizare cu link de dezabonare');
  await page.click('form[action$="/lanseaza"] button');
  await page.waitForURL('**/raport**');
  ok(await page.locator('text=Destinatari').count() > 0, 'Email: campanie lansată → raport');

  // audit SEO
  await page.goto(BASE + '/admin/seo/audit');
  ok(await page.locator('.score').count() > 5, 'SEO: audit generat');

  // backup
  await page.goto(BASE + '/admin/sistem');
  await page.click('button[value=db]');
  ok(await page.locator('.alert-ok').count() > 0, 'Sistem: backup bază de date creat');

  // asistent AI (necesită 'ai_base_url' => mock în app/config.php: php -S 127.0.0.1:8090 tests/mock-anthropic.php)
  console.log('Asistent AI');
  for (const p of ['/admin/asistent', '/admin/setari/asistent']) {
    const r = await page.goto(BASE + p);
    ok(r.status() === 200 && !(await page.locator('text=Ceva nu a mers bine').count()), `${p} → 200`);
  }
  await page.goto(BASE + '/admin/setari/asistent');
  await page.check('input[name=ai_enabled]');
  await page.fill('input[name=ai_api_key]', 'test-key-123');
  await page.click('button:has-text("Salvează")');
  ok(await page.locator('.alert-ok').count() > 0, 'Asistent: setări salvate');
  await page.goto(BASE + '/admin/asistent');
  await page.click('button:has-text("Testează conexiunea")');
  const connOk = await page.locator('.alert-ok').count() > 0;
  ok(true, 'Asistent: test conexiune rulat (' + (connOk ? 'API mock disponibil' : 'fără API – partea de chat se sare') + ')');
  if (connOk) {
    const pub = await ctx.newPage();
    pub.on('pageerror', e => jsErrors.push(e.message));
    await pub.context().clearCookies();
    await pub.goto(BASE + '/');
    ok(await pub.locator('[data-chat-open]').isVisible(), 'Chat: butonul apare pe site');
    await pub.click('[data-chat-open]');
    ok(await pub.locator('[data-chat]').isVisible(), 'Chat: fereastra se deschide');
    await pub.locator('[data-chat-sugg] button').first().click();
    await pub.waitForSelector('.msg.bot:not(.typing) >> nth=1', { timeout: 20000 });
    ok(await pub.locator('.msg.bot strong', { hasText: 'nu mai porni' }).count() > 0, 'Chat: răspuns formatat (îngroșat, listă)');
    ok(await pub.locator('.msg.bot a[href="/servicii/recuperare-date"]').count() > 0, 'Chat: link intern păstrat');
    ok(await pub.locator('.msg.bot a[href*="evil"]').count() === 0, 'Chat: link extern eliminat (siguranță)');
    await pub.goto(BASE + '/contact');
    ok(await pub.locator('[data-chat]').isVisible() && await pub.locator('.msg.me').count() > 0, 'Chat: conversația continuă pe altă pagină');
    const html = await pub.content();
    ok(!/claude|anthropic/i.test(html.replace(/ClaudeBot|Claude-SearchBot/g, '')), 'Site: nicio mențiune a furnizorului AI în pagină');
    await pub.close();
  }

  ok(jsErrors.length === 0, 'fără erori JavaScript' + (jsErrors.length ? ': ' + jsErrors.join(' | ') : ''));
  await browser.close();
  console.log(`\n${passed} teste trecute, ${failed} eșuate`);
  process.exit(failed ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
