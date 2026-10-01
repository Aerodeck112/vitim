let pw; try { pw = require('playwright'); } catch (e) { pw = require('/opt/node22/lib/node_modules/playwright'); }
const crypto = require('crypto'); const fs = require('fs');
// Test de fum pe o instalare reală (din arhivă): node tests/e2e/install-flow.cjs <url> <director-instalare>
// Cere: bază goală, SETUP_TOKEN=token-de-instalare-foarte-lung, MAIL_MAILER=log, LOG_LEVEL=debug.
const B = process.argv[2] || 'http://127.0.0.1:8095', S = process.argv[3];
function totp(secret) {
  const a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; let bits = '';
  for (const c of secret.replace(/\s/g, '')) bits += a.indexOf(c).toString(2).padStart(5, '0');
  const key = Buffer.from(bits.match(/.{8}/g).map(b => parseInt(b, 2)));
  const buf = Buffer.alloc(8); buf.writeUInt32BE(Math.floor(Date.now() / 30000), 4);
  const h = crypto.createHmac('sha1', key).update(buf).digest(); const o = h[19] & 15;
  return String(((h.readUInt32BE(o) & 0x7fffffff) % 1e6)).padStart(6, '0');
}
const ok = (c, m) => { console.log((c ? '✓ ' : '✗ ') + m); if (!c) process.exitCode = 1; };
(async () => {
  const b = await pw.chromium.launch(); const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
  const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto(B + '/'); ok(p.url().endsWith('/login'), 'vizitator → /login');
  await p.goto(B + '/setup');
  await p.fill('#token', 'token-de-instalare-foarte-lung'); await p.fill('#name', 'Admin VITIM'); await p.fill('#email', 'office@vitim.ro');
  await p.fill('#password', 'parola-admin-sigura-123'); await p.fill('#password_confirmation', 'parola-admin-sigura-123');
  await p.click('button[type=submit]'); ok(p.url().endsWith('/2fa/activare'), 'setup → activare 2FA');
  await p.screenshot({ path: S + '/s1-2fa.png' });
  const secret = (await p.textContent('pre.code')).trim();
  await p.fill('#code', totp(secret)); await p.click('button[type=submit]');
  ok(p.url().endsWith('/admin'), '2FA activat → /admin');
  await p.click('text=Client nou'); await p.fill('#name', 'Demo Auto SRL'); await p.selectOption('#plan', 'pro');
  await p.fill('#owner_name', 'Ion Popescu'); await p.fill('#owner_email', 'ion@demoauto.ro'); await p.click('main button[type=submit]');
  ok(p.url().endsWith('/admin/clienti/demo-auto-srl'), 'client creat');
  await p.fill('#domain', 'https://www.demoauto.ro'); await p.click('text=Adaugă site');
  const sec = await p.locator('.alert-warn pre.code').textContent(); ok(sec.trim().startsWith('sk_'), 'secret afișat o dată');
  await p.screenshot({ path: require('os').tmpdir() + '/vitim-ai-' + Date.now() + '.png', fullPage: true });
  await p.reload(); ok(!(await p.content()).includes(sec.trim()), 'secretul nu mai apare la reîncărcare');
  await p.goto(B + '/admin'); await p.screenshot({ path: require('os').tmpdir() + '/vitim-ai-' + Date.now() + '.png', fullPage: true });
  ok((await p.textContent('table')).includes('Demo Auto SRL'), 'lista clienților');
  await p.click('text=Ieșire');
  // proprietarul: linkul din log (MAIL_MAILER=log)
  const log = fs.readdirSync(S + '/storage/logs').map(f => fs.readFileSync(S + '/storage/logs/' + f, 'utf8')).join('');
  const m = log.match(/https?:\/\/[^\s\/]+\/parola\/[A-Za-z0-9]+\?email=[^\s\]"]+/);
  ok(!!m, 'email cu link de setare parolă');
  await p.goto(m[0].replace(/&amp;/g, '&')); await p.fill('#password', 'parola-client-sigura-1'); await p.fill('#password_confirmation', 'parola-client-sigura-1');
  await p.click('button[type=submit]'); ok(p.url().endsWith('/login'), 'parolă setată');
  await p.fill('#email', 'ion@demoauto.ro'); await p.fill('#password', 'parola-client-sigura-1'); await p.click('button[type=submit]');
  ok(p.url().endsWith('/app/demo-auto-srl'), 'proprietar → dashboardul firmei');
  await p.goto(B + '/app/demo-auto-srl/setari');
  ok((await p.content()).includes('data-site=&quot;pk_') || (await p.content()).includes('data-site="pk_'), 'setări: cod de instalare');
  // contact + lead din interfață
  await p.goto(B + '/app/demo-auto-srl/contacte/nou'); await p.fill('#first_name', 'Andrei'); await p.fill('#email', 'andrei@example.test'); await p.fill('#phone', '0700 000 001');
  await p.click('main button[type=submit]'); ok(/\/contacte\/\d+$/.test(p.url()), 'contact creat');
  await p.fill('#summary', 'Schimb distribuție'); await p.click('text=Adaugă lead'); ok((await p.content()).includes('Schimb distribuție'), 'lead creat');
  // agent din template + test din panou (fără cheie AI: răspuns sigur, cu datele de contact)
  await p.goto(B + '/app/demo-auto-srl/agent'); await p.fill('#name', 'Asistent Demo'); await p.selectOption('#template', 'auto_service');
  await p.click('text=Creează agentul'); ok(/\/agent\/\d+$/.test(p.url()), 'agent creat din template');
  await p.fill('#business_facts', 'Schimb distribuție: de la 900 lei.'); await p.fill('#contact_line', '0265 000 000'); await p.click('main button[type=submit]');
  ok((await p.content()).includes('versiunea 2'), 'configurație salvată, versiunea 2');
  await p.click('text=Testează agentul'); await p.fill('textarea[name=message]', 'Cât costă distribuția?'); await p.click('button:has-text("Trimite")');
  ok((await p.textContent('.chat')).includes('0265 000 000'), 'test agent: fără cheie AI răspunde cu datele de contact');
  await p.screenshot({ path: require('os').tmpdir() + '/vitim-ai-' + Date.now() + '.png', fullPage: true });
  await p.goto(B + '/app/demo-auto-srl/in-curand/campanii'); ok((await p.content()).includes('În dezvoltare'), 'secțiune viitoare marcată');
  await p.screenshot({ path: require('os').tmpdir() + '/vitim-ai-' + Date.now() + '.png', fullPage: true });
  const r = await p.goto(B + '/admin'); ok(r.status() === 404, 'client nu vede /admin');
  await p.setViewportSize({ width: 390, height: 844 }); await p.goto(B + '/app/demo-auto-srl'); await p.screenshot({ path: require('os').tmpdir() + '/vitim-ai-' + Date.now() + '.png', fullPage: true });
  const ov = await p.evaluate(() => document.documentElement.scrollWidth > window.innerWidth); ok(!ov, 'fără scroll orizontal pe mobil');
  ok(errs.length === 0, 'fără erori JavaScript');
  await b.close();
})();
