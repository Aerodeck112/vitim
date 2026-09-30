/* Teste end-to-end (Playwright) pentru magazin.
 *   php -S 127.0.0.1:8080 tools/dev-router.php
 *   php -S 127.0.0.1:8091 tests/mock-btipay.php     (simulator BT iPay)
 *   node tests/e2e.cjs http://127.0.0.1:8080 admin@exemplu.ro 'parola'
 * Plata cu cardul necesită BT iPay configurat către simulator (bt_url_test = http://127.0.0.1:8091, utilizator test_api / test_pass).
 */
const path = require('path');
let pw;
try { pw = require('playwright'); } catch (e) { pw = require(require('child_process').execSync('npm root -g').toString().trim() + '/playwright'); }
const [, , BASE = 'http://127.0.0.1:8080', EMAIL = 'admin@exemplu.ro', PASS = 'parola-test-123'] = process.argv;
const shots = path.join(__dirname, 'shots');
require('fs').mkdirSync(shots, { recursive: true });

let passed = 0, failed = 0;
async function step(name, fn) {
  try { await fn(); passed++; console.log('  ✓ ' + name); }
  catch (e) { failed++; console.log('  ✗ ' + name + '\n      ' + String(e.message || e).split('\n')[0]); }
}
function assert(c, m) { if (!c) throw new Error(m); }

async function fillCheckout(page, o = {}) {
  await page.fill('#c-email', o.email || 'client.test@example.com');
  await page.fill('#c-phone', '0740 123 456');
  await page.fill('#c-fn', 'Ana');
  await page.fill('#c-ln', 'Popescu');
  await page.fill('#c-addr', 'Str. Florilor nr. 10, bl. A2, ap. 5');
  await page.fill('#c-city', 'Târgu Mureș');
  await page.selectOption('#c-county', 'Mureș');
  await page.fill('#c-zip', '540001');
  await page.check(`input[name=payment_method][value=${o.pay || 'card'}]`);
  await page.check('input[name=terms]');
  await page.waitForTimeout(2200); // tokenul formularului cere minimum 2 secunde
}

(async () => {
  const browser = await pw.chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1300, height: 900 } });
  await ctx.addCookies([{ name: 'bdm_consent', value: encodeURIComponent('{"analytics":false,"marketing":false}'), url: BASE }]);
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));

  console.log('Site public');
  await step('prima pagină + 7 produse în catalog', async () => {
    await page.goto(BASE + '/');
    assert(await page.locator('h1').first().textContent().then(t => t.includes('Cafea premium')), 'H1 lipsă');
    await page.goto(BASE + '/produse');
    const n = await page.locator('.pcard').count();
    assert(n === 7, 'produse găsite: ' + n);
  });
  await step('pagina produs are Product JSON-LD cu preț', async () => {
    await page.goto(BASE + '/produs/cafea-boabe-miscela-bar-bar-blend-1kg-prajita-la-foc-de-lemn');
    const ld = await page.locator('script[type="application/ld+json"]').first().textContent();
    const g = JSON.parse(ld)['@graph'];
    const p = g.find(x => x['@type'] === 'Product');
    assert(p && p.offers.price === '130.00' && p.offers.priceCurrency === 'RON', 'Product/Offer greșit');
  });
  await step('adăugare în coș (AJAX) + sertar coș', async () => {
    await page.click('[data-buy-form] button.btn:not([data-buy-now])');
    await page.waitForSelector('#cart-drawer.open');
    const c = await page.locator('[data-cart-count]').textContent();
    assert(c.trim() === '1', 'contor coș = ' + c);
  });
  await step('cantitate în coș + cod de reducere invalid', async () => {
    await page.goto(BASE + '/cos');
    await page.click('.cart-table [data-inc]');
    await page.waitForLoadState('networkidle'); await page.waitForTimeout(1200);
    await page.fill('#cp', 'NUEXISTA'); await page.click('.coupon button');
    await page.waitForSelector('.alert-err');
    const total = await page.locator('.summary .line.total').textContent();
    assert(total.includes('280,00'), 'total coș: ' + total); // 2 × 130 + 20 livrare
  });

  console.log('Plată cu cardul (BT iPay – simulator)');
  let orderUrl = '';
  await step('comandă cu card aprobat', async () => {
    await page.goto(BASE + '/finalizare');
    await fillCheckout(page);
    await Promise.all([page.waitForURL(/pay\.html/), page.click('[data-place]')]);
    await Promise.all([page.waitForURL(/\/comanda\//), page.click('#pay-ok')]);
    orderUrl = page.url();
    const h = await page.locator('h1').first().textContent();
    assert(h.includes('Mulțumim'), 'titlu: ' + h);
    assert((await page.locator('.kv').textContent()).includes('Plătită'), 'plata nu apare ca plătită');
    await page.screenshot({ path: path.join(shots, 'comanda-platita.png'), fullPage: true });
  });
  await step('coșul este golit după comandă', async () => {
    await page.goto(BASE + '/cos');
    assert(await page.locator('main .card.empty-cart').count() === 1, 'coșul nu e gol');
  });
  await step('comandă cu card refuzat → mesaj clar → trecere la ramburs', async () => {
    await page.goto(BASE + '/produs/saka-premium-monodoza');
    await page.fill('#qty', '150');
    await page.click('[data-buy-form] button.btn:not([data-buy-now])');
    await page.waitForSelector('#cart-drawer.open');
    await page.goto(BASE + '/finalizare');
    await fillCheckout(page, { email: 'refuz@example.com' });
    await Promise.all([page.waitForURL(/pay\.html/), page.click('[data-place]')]);
    await Promise.all([page.waitForURL(/\/comanda\//), page.click('#pay-fail')]);
    const h = await page.locator('h1').first().textContent();
    assert(h.includes('Plata nu a fost finalizată'), 'titlu: ' + h);
    assert((await page.locator('.order-hero').textContent()).includes('Fonduri insuficiente'), 'lipsește motivul refuzului');
    await page.screenshot({ path: path.join(shots, 'plata-refuzata.png'), fullPage: true });
    await Promise.all([page.waitForNavigation(), page.click('button:has-text("ramburs")')]);
    assert((await page.locator('.kv').textContent()).includes('Ramburs'), 'nu a trecut la ramburs');
  });
  await step('comandă ramburs direct', async () => {
    await page.goto(BASE + '/produs/acs-one-coffee');
    await Promise.all([page.waitForURL(/\/finalizare/), page.click('[data-buy-now]')]);
    await fillCheckout(page, { pay: 'ramburs', email: 'ramburs@example.com' });
    await Promise.all([page.waitForURL(/\/comanda\//), page.click('[data-place]')]);
    assert((await page.locator('h1').first().textContent()).includes('Mulțumim'), 'fără confirmare');
  });
  await step('urmărire comandă cu număr + email', async () => {
    const num = await page.locator('.card h2').first().textContent();
    await page.goto(BASE + '/urmarire-comanda');
    await page.fill('#t-n', num.replace('Comanda', '').trim()); await page.fill('#t-e', 'ramburs@example.com');
    await Promise.all([page.waitForURL(/\/comanda\//), page.click('form.card button')]);
  });
  await step('formular contact', async () => {
    await page.goto(BASE + '/contact');
    await page.fill('#f-n', 'Ion Test'); await page.fill('#f-e', 'ion@example.com'); await page.fill('#f-m', 'Aveți și cafea măcinată?');
    await page.check('form[data-ajax] input[name=consent]');
    await page.waitForTimeout(3200);
    await page.click('form[data-ajax] button[type=submit]');
    await page.waitForSelector('.form-msg .alert-ok');
  });

  console.log('Panou de control');
  await step('autentificare', async () => {
    await page.goto(BASE + '/admin/login');
    await page.fill('#email', EMAIL); await page.fill('#password', PASS);
    await Promise.all([page.waitForURL(/dashboard/), page.click('button[type=submit]')]);
  });
  await step('comenzile apar în listă', async () => {
    await page.goto(BASE + '/admin/comenzi');
    const n = await page.locator('table.t tbody tr').count();
    assert(n >= 3, 'comenzi: ' + n);
    await page.screenshot({ path: path.join(shots, 'admin-comenzi.png'), fullPage: true });
  });
  await step('rambursare parțială BT iPay din panou', async () => {
    await page.goto(BASE + '/admin/comenzi');
    await page.click('table.t tbody tr:has(.st:text-is("Plătită")) a.row-title');
    page.once('dialog', d => d.accept());
    await page.fill('form[action$="/plata/ramburseaza"] input[name=amount]', '20.00');
    await Promise.all([page.waitForNavigation(), page.click('form[action$="/plata/ramburseaza"] button')]);
    const t = await page.locator('.alert').first().textContent();
    assert(t.includes('reușită'), t);
    assert((await page.locator('.bt-box').textContent()).includes('Rambursată parțial'), 'stare BT nu e rambursată parțial');
    await page.screenshot({ path: path.join(shots, 'admin-comanda.png'), fullPage: true });
  });
  await step('expediere cu AWB + stare „Expediată”', async () => {
    await page.selectOption('select[name=courier]', 'Fan Courier');
    await page.fill('input[name=awb]', '2345678901');
    await Promise.all([page.waitForNavigation(), page.click('form[action$="/livrare"] button')]);
    assert((await page.locator('h1').textContent()).includes('Expediată'), 'stare neschimbată');
  });
  await step('editare preț produs + pagina publică actualizată', async () => {
    await page.goto(BASE + '/admin/produse/2');
    await page.fill('input[name=sale_price]', '119.90');
    await Promise.all([page.waitForNavigation(), page.click('button:has-text("Salvează")')]);
    assert((await page.locator('.alert-ok').count()) > 0, 'nesalvat');
    await page.goto(BASE + '/produs/cafea-boabe-miscela-bar-bar-blend-1kg-prajita-la-foc-de-lemn');
    assert((await page.locator('.pinfo .price').textContent()).includes('119,90'), 'prețul redus nu apare');
    await page.goto(BASE + '/admin/produse/2');
    await page.fill('input[name=sale_price]', '');
    await Promise.all([page.waitForNavigation(), page.click('button:has-text("Salvează")')]);
  });
  await step('cod de reducere nou aplicat în coș', async () => {
    await page.goto(BASE + '/admin/c/cupoane/nou');
    await page.fill('#fld_code', 'CAFEA10'); await page.fill('#fld_value', '10');
    await Promise.all([page.waitForNavigation(), page.click('button:has-text("Salvează")')]);
    await page.goto(BASE + '/produs/grana-miscela-cremabar-1kg-prajita-la-foc-de-lemn');
    await page.click('[data-buy-form] button.btn:not([data-buy-now])');
    await page.waitForSelector('#cart-drawer.open');
    await page.goto(BASE + '/cos');
    await page.fill('#cp', 'cafea10'); await Promise.all([page.waitForNavigation(), page.click('.coupon button')]);
    assert((await page.locator('.summary').textContent()).includes('15,20'), 'reducerea de 10% nu apare');
  });
  await step('toate paginile panoului se încarcă', async () => {
    for (const p of ['/admin/dashboard', '/admin/clienti', '/admin/produse', '/admin/c/categorii', '/admin/c/pagini', '/admin/mesaje', '/admin/seo/audit', '/admin/setari/plati', '/admin/setari/prima', '/admin/sistem']) {
      const r = await page.goto(BASE + p);
      assert(r.status() === 200, p + ' → ' + r.status());
    }
    await page.goto(BASE + '/admin/dashboard');
    await page.screenshot({ path: path.join(shots, 'admin-dashboard.png'), fullPage: true });
  });
  await step('fără erori JavaScript', async () => { assert(errors.length === 0, errors.join(' | ')); });

  await browser.close();
  console.log(`\n${passed} reușite, ${failed} eșuate`);
  process.exit(failed ? 1 : 0);
})();
