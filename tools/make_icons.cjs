/**
 * Generează iconițele VITIM (favicon, apple-touch, PWA, logo pentru Google) din simbolul SVG.
 *   node tools/make_icons.cjs
 * Folosește Chromium din Playwright pentru randare exactă a SVG-ului.
 */
const fs = require('fs');
const path = require('path');
let pw; try { pw = require('playwright'); } catch (e) { pw = require('/opt/node22/lib/node_modules/playwright'); }
const out = path.join(__dirname, '..', 'assets', 'img');
const mark = (rx = 14) => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="${rx}" fill="#0b1020"/><path d="M17.5 17.5 32 44 46.5 17.5" fill="none" stroke="#eef1f8" stroke-width="6"/><circle cx="17.5" cy="17.5" r="5" fill="#0b1020" stroke="#eef1f8" stroke-width="3"/><circle cx="46.5" cy="17.5" r="5" fill="#0b1020" stroke="#eef1f8" stroke-width="3"/><circle cx="32" cy="45" r="6.5" fill="#4d82ec"/></svg>`;
(async () => {
  fs.writeFileSync(path.join(out, 'favicon.svg'), mark());
  const b = await pw.chromium.launch();
  const p = await b.newPage();
  for (const [f, size, rx] of [['favicon-32.png', 32, 14], ['apple-touch-icon.png', 180, 0], ['icon-192.png', 192, 14], ['icon-512.png', 512, 0], ['logo.png', 512, 14]]) {
    await p.setViewportSize({ width: size, height: size });
    await p.setContent(`<html><body style="margin:0;background:transparent">${mark(rx).replace('<svg ', `<svg width="${size}" height="${size}" `)}</body></html>`);
    await p.screenshot({ path: path.join(out, f), omitBackground: true, clip: { x: 0, y: 0, width: size, height: size } });
    console.log(f);
  }
  await b.close();
})();
