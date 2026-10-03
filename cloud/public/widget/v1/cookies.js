/*! VITIM cookie-uri 1.0 — bannerul de consimțământ pentru cookie-uri (Legea 506/2004, GDPR). Se încarcă automat de loader.js.
 *  Scripturile terților așteaptă acordul dacă sunt marcate:  <script type="text/plain" data-vitim-consent="statistics|marketing|preferences" src="…"></script>
 *  Iframe-uri:  <iframe data-vitim-consent="marketing" data-src="https://www.youtube.com/embed/…"></iframe>
 *  Redeschidere:  <a href="#vitim-cookies">Setări cookie-uri</a>   ·   Politica:  <div data-vitim-cookie-policy></div>
 *  API:  window.VitimConsent.get() / .open() / .on(fn)   ·   eveniment DOM „vitim:consent”   ·   Google Consent Mode v2 */
(function () {
  'use strict';
  if (window.VitimCookies) return;
  var NAME = 'vitim_consent', CATS = ['preferences', 'statistics', 'marketing'];
  var cfg = null, ctx = null, state = null, host = null, root = null, listeners = [];

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function uuid() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) { var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16); });
  }
  function read() {
    var m = document.cookie.match(/(?:^|; )vitim_consent=v(\d+)\.([01])([01])([01])\.([a-f0-9-]{16,36})/);
    return m ? { version: +m[1], preferences: m[2] === '1', statistics: m[3] === '1', marketing: m[4] === '1', id: m[5] } : null;
  }
  function write(s) {
    var v = 'v' + cfg.version + '.' + (s.preferences ? 1 : 0) + (s.statistics ? 1 : 0) + (s.marketing ? 1 : 0) + '.' + s.id;
    document.cookie = NAME + '=' + v + '; path=/; max-age=' + (cfg.days * 86400) + '; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
  }

  // ---------- efectele alegerii ----------
  function gcm(s) {
    if (!cfg.gcm) return;
    window.dataLayer = window.dataLayer || [];
    var gtag = window.gtag || function () { window.dataLayer.push(arguments); };
    var g = function (b) { return b ? 'granted' : 'denied'; };
    gtag('consent', 'update', { ad_storage: g(s.marketing), ad_user_data: g(s.marketing), ad_personalization: g(s.marketing),
      analytics_storage: g(s.statistics), functionality_storage: g(s.preferences), personalization_storage: g(s.preferences), security_storage: 'granted' });
    window.dataLayer.push({ event: 'vitim_consent_update', vitim_consent: { preferences: s.preferences, statistics: s.statistics, marketing: s.marketing } });
  }
  function unblock(s) {
    var ok = function (cat) { return cat === 'necessary' || s[cat]; };
    document.querySelectorAll('script[type="text/plain"][data-vitim-consent]:not([data-vitim-done])').forEach(function (old) {
      if (!ok(old.getAttribute('data-vitim-consent'))) return;
      old.setAttribute('data-vitim-done', '1');
      var n = document.createElement('script');
      for (var i = 0; i < old.attributes.length; i++) { var a = old.attributes[i]; if (a.name !== 'type' && a.name.indexOf('data-vitim') !== 0) n.setAttribute(a.name, a.value); }
      if (!old.src) n.text = old.text;
      old.parentNode.insertBefore(n, old.nextSibling);
    });
    document.querySelectorAll('iframe[data-vitim-consent][data-src]:not([data-vitim-done])').forEach(function (f) {
      if (!ok(f.getAttribute('data-vitim-consent'))) return;
      f.setAttribute('data-vitim-done', '1'); f.src = f.getAttribute('data-src');
    });
  }
  // la retragerea acordului: ștergem cookie-urile cunoscute ale categoriei (pe domeniul curent și pe domeniile-părinte)
  function cleanup(s) {
    var names = [];
    cfg.categories.forEach(function (c) {
      if (c.key === 'necessary' || s[c.key]) return;
      c.cookies.forEach(function (row) {
        String(row.cookies || '').split(',').forEach(function (n) {
          n = n.replace(/\(.*?\)/g, '').trim();
          if (/^[A-Za-z0-9_\-*]+$/.test(n) && n !== '—') names.push(n);
        });
      });
    });
    if (!names.length) return;
    var parts = location.hostname.split('.'), domains = [''];
    for (var i = 0; i < parts.length - 1; i++) domains.push('.' + parts.slice(i).join('.'));
    document.cookie.split('; ').forEach(function (pair) {
      var key = pair.split('=')[0];
      var hit = names.some(function (n) { return n.slice(-1) === '*' ? key.indexOf(n.slice(0, -1)) === 0 : key === n; });
      if (!hit) return;
      domains.forEach(function (d) { document.cookie = key + '=; path=/; max-age=0' + (d ? '; domain=' + d : ''); });
    });
  }
  function apply(s, changed) {
    state = s;
    gcm(s); unblock(s);
    if (changed) cleanup(s);
    var detail = window.VitimConsent.get();
    listeners.forEach(function (fn) { try { fn(detail); } catch (e) {} });
    try { document.dispatchEvent(new CustomEvent('vitim:consent', { detail: detail })); } catch (e) {}
    if (ctx && ctx.onChange) ctx.onChange(detail);
  }
  function choose(action, picks) {
    var s = { id: (state && state.id) || uuid(), version: cfg.version,
      preferences: action === 'accept_all' || (action === 'custom' && !!picks.preferences),
      statistics: action === 'accept_all' || (action === 'custom' && !!picks.statistics),
      marketing: action === 'accept_all' || (action === 'custom' && !!picks.marketing) };
    write(s);
    if (ctx && ctx.call) ctx.call('consent', { consent_id: s.id, action: action, preferences: s.preferences, statistics: s.statistics, marketing: s.marketing, version: cfg.version, page: location.href.slice(0, 255) }).catch(function () {});
    close(); apply(s, true); reopenButton();
  }

  // ---------- interfața ----------
  var CSS = ':host{all:initial}*{box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif}'
    + '.bn{position:fixed;z-index:2147483640;background:#fff;color:#0f172a;box-shadow:0 10px 40px rgba(15,23,42,.22);font-size:14px;line-height:1.5}'
    + '.bar{left:0;right:0;bottom:0;padding:18px 20px;border-top:1px solid #e5e8f0}.bar .wrap{max-width:1180px;margin:0 auto;display:flex;gap:18px;align-items:center;flex-wrap:wrap}.bar .txt{flex:1 1 420px}'
    + '.box{bottom:16px;width:400px;max-width:calc(100vw - 32px);border-radius:14px;padding:20px}.box.left{left:16px}.box.right{right:16px}'
    + 'h2{margin:0 0 6px;font-size:16px;font-weight:700}p{margin:0;color:#334155}a{color:inherit}'
    + '.btns{display:flex;gap:8px;flex-wrap:wrap}.box .btns{margin-top:14px}.btn{flex:1 1 auto;min-width:120px;height:42px;padding:0 16px;border-radius:9px;font-size:14px;font-weight:700;cursor:pointer;border:2px solid var(--c);background:var(--c);color:#fff}'
    + '.btn.alt{background:#fff;color:var(--c)}.btn:focus-visible,.sw input:focus-visible+span{outline:3px solid #0f172a;outline-offset:2px}'
    + '.ov{position:fixed;inset:0;z-index:2147483641;background:rgba(15,23,42,.5);display:flex;align-items:center;justify-content:center;padding:16px}'
    + '.panel{background:#fff;color:#0f172a;border-radius:14px;width:620px;max-width:100%;max-height:88vh;display:flex;flex-direction:column;font-size:14px;line-height:1.5;box-shadow:0 24px 60px rgba(0,0,0,.3)}'
    + '.ph{padding:18px 20px 10px;border-bottom:1px solid #e5e8f0}.pb{padding:6px 20px;overflow:auto}.pf{padding:14px 20px;border-top:1px solid #e5e8f0}'
    + '.cat{border-bottom:1px solid #eef1f6;padding:14px 0}.cat:last-child{border-bottom:0}.row{display:flex;justify-content:space-between;gap:14px;align-items:center}.row strong{font-size:15px}'
    + '.sw{position:relative;display:inline-block;width:44px;height:24px;flex:0 0 44px}.sw input{position:absolute;opacity:0;width:100%;height:100%;margin:0;cursor:pointer}'
    + '.sw span{position:absolute;inset:0;border-radius:24px;background:#cbd5e1;transition:.15s}.sw span:after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;transition:.15s}'
    + '.sw input:checked+span{background:var(--c)}.sw input:checked+span:after{left:23px}.sw input:disabled+span{opacity:.6}'
    + '.desc{color:#475569;font-size:13px;margin-top:4px}details{margin-top:6px;font-size:13px}summary{cursor:pointer;color:#334155}'
    + 'table{width:100%;border-collapse:collapse;margin-top:6px;font-size:12.5px}th,td{text-align:left;padding:5px 6px;border-bottom:1px solid #eef1f6;vertical-align:top}th{color:#64748b;font-weight:600}'
    + '.x{background:none;border:0;font-size:22px;line-height:1;cursor:pointer;color:#475569;padding:4px}.links{margin-top:8px;font-size:12.5px;color:#64748b}'
    + '.fab{position:fixed;bottom:16px;z-index:2147483630;width:44px;height:44px;border-radius:50%;border:1px solid #e5e8f0;background:#fff;box-shadow:0 4px 14px rgba(15,23,42,.18);cursor:pointer;font-size:20px;line-height:42px;text-align:center;padding:0}.fab.left{left:16px}.fab.right{right:16px}'
    + '@media (max-width:640px){.bar{padding:14px}.btn{min-width:0;flex:1 1 30%}.box{left:8px!important;right:8px;width:auto;bottom:8px}}';

  function mount() {
    if (host) return;
    host = document.createElement('div'); host.setAttribute('data-vitim-cookies', '');
    root = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host;
    var st = document.createElement('style'); st.textContent = CSS; root.appendChild(st);
    var wrap = document.createElement('div'); wrap.className = 'w'; wrap.style.setProperty('--c', cfg.color || '#2f6bff');
    root.appendChild(wrap);
    document.body.appendChild(host);
  }
  function w() { return root.querySelector('.w'); }
  function links() {
    var a = [];
    if (cfg.policy_url) a.push('<a href="' + esc(cfg.policy_url) + '">Politica de cookie-uri</a>');
    if (cfg.privacy_url) a.push('<a href="' + esc(cfg.privacy_url) + '">Politica de confidențialitate</a>');
    return a.length ? '<div class="links">' + a.join(' · ') + '</div>' : '';
  }
  function banner() {
    mount();
    var layout = cfg.layout === 'box' ? 'box ' + (cfg.position === 'right' ? 'right' : 'left') : 'bar';
    w().innerHTML = '<div class="bn ' + layout + '" role="dialog" aria-modal="false" aria-labelledby="vc-t" aria-describedby="vc-d"><div class="wrap">'
      + '<div class="txt"><h2 id="vc-t">' + esc(cfg.title) + '</h2><p id="vc-d">' + esc(cfg.text) + '</p>' + links() + '</div>'
      + '<div class="btns"><button type="button" class="btn alt" data-a="reject_all">Refuz toate</button><button type="button" class="btn alt" data-a="settings">Setări</button><button type="button" class="btn" data-a="accept_all">Accept toate</button></div>'
      + '</div></div>';
    bind();
  }
  function settings() {
    mount();
    var cur = state || {};
    var cats = cfg.categories.map(function (c) {
      var fixed = c.key === 'necessary';
      var rows = c.cookies.map(function (r) { return '<tr><td>' + esc(r.name) + '<br><span style="color:#64748b">' + esc(r.provider) + '</span></td><td>' + esc(r.cookies) + '</td><td>' + esc(r.duration) + '</td><td>' + esc(r.purpose) + '</td></tr>'; }).join('');
      return '<div class="cat"><div class="row"><strong id="vc-' + c.key + '">' + esc(c.label) + '</strong>'
        + '<label class="sw"><input type="checkbox" data-c="' + c.key + '" aria-labelledby="vc-' + c.key + '"' + (fixed || cur[c.key] ? ' checked' : '') + (fixed ? ' disabled' : '') + '><span></span></label></div>'
        + '<div class="desc">' + esc(c.description) + (fixed ? ' <em>Mereu active.</em>' : '') + '</div>'
        + (rows ? '<details><summary>Vezi cookie-urile (' + c.cookies.length + ')</summary><table><thead><tr><th>Serviciu</th><th>Cookie</th><th>Durată</th><th>Scop</th></tr></thead><tbody>' + rows + '</tbody></table></details>' : '')
        + '</div>';
    }).join('');
    w().innerHTML = '<div class="ov"><div class="panel" role="dialog" aria-modal="true" aria-labelledby="vc-st">'
      + '<div class="ph"><div class="row"><h2 id="vc-st">Setări cookie-uri</h2><button type="button" class="x" data-a="close" aria-label="Închide">×</button></div>'
      + '<p class="desc">Alege ce cookie-uri accepți pe site-ul ' + esc(cfg.company) + '. Îți poți schimba alegerea oricând.</p></div>'
      + '<div class="pb">' + cats + '</div>'
      + '<div class="pf"><div class="btns"><button type="button" class="btn alt" data-a="reject_all">Refuz toate</button><button type="button" class="btn alt" data-a="custom">Salvează alegerile</button><button type="button" class="btn" data-a="accept_all">Accept toate</button></div>' + links() + '</div>'
      + '</div></div>';
    bind();
    var first = root.querySelector('input:not([disabled])') || root.querySelector('.btn'); if (first) first.focus();
  }
  function bind() {
    root.querySelectorAll('[data-a]').forEach(function (b) {
      b.addEventListener('click', function () {
        var a = b.getAttribute('data-a');
        if (a === 'settings') return settings();
        if (a === 'close') return state && state.version === cfg.version ? close() : banner();
        var picks = {};
        root.querySelectorAll('input[data-c]').forEach(function (i) { picks[i.getAttribute('data-c')] = i.checked; });
        choose(a, picks);
      });
    });
    var ov = root.querySelector('.ov');
    if (ov) {
      ov.addEventListener('keydown', function (e) { if (e.key === 'Escape' && state && state.version === cfg.version) close(); });
    }
  }
  function close() { if (host) { host.parentNode && host.parentNode.removeChild(host); host = null; root = null; } }
  function reopenButton() {
    if (!cfg.reopen || document.querySelector('[data-vitim-cookie-fab]')) return;
    var b = document.createElement('div'); b.setAttribute('data-vitim-cookie-fab', '');
    var r = b.attachShadow ? b.attachShadow({ mode: 'open' }) : b;
    r.innerHTML = '<style>' + CSS + '</style><button type="button" class="fab ' + (cfg.position === 'right' && cfg.layout === 'box' ? 'right' : 'left') + '" aria-label="Setări cookie-uri" title="Setări cookie-uri">🍪</button>';
    r.querySelector('button').addEventListener('click', function () { settings(); });
    document.body.appendChild(b);
  }
  // pagina de politică: tabelul complet, în pagina firmei (moștenește stilul site-ului)
  function policies() {
    document.querySelectorAll('[data-vitim-cookie-policy]:not([data-vitim-done])').forEach(function (el) {
      el.setAttribute('data-vitim-done', '1');
      var html = '<p>Această listă este actualizată automat. Ultima modificare a politicii: versiunea ' + cfg.version + '. Alegerea ta se păstrează ' + cfg.days + ' de zile.</p>';
      cfg.categories.forEach(function (c) {
        html += '<h3>' + esc(c.label) + '</h3><p>' + esc(c.description) + '</p>';
        if (c.cookies.length) {
          html += '<div style="overflow-x:auto"><table class="vitim-cookie-table"><thead><tr><th>Serviciu</th><th>Furnizor</th><th>Cookie-uri</th><th>Durată</th><th>Scop</th></tr></thead><tbody>'
            + c.cookies.map(function (r) { return '<tr><td>' + esc(r.name) + '</td><td>' + esc(r.provider) + '</td><td>' + esc(r.cookies) + '</td><td>' + esc(r.duration) + '</td><td>' + esc(r.purpose) + '</td></tr>'; }).join('')
            + '</tbody></table></div>';
        }
      });
      html += '<p><button type="button" class="vitim-cookie-settings">Schimbă setările cookie-urilor</button></p>';
      el.innerHTML = html;
      el.querySelector('.vitim-cookie-settings').addEventListener('click', function () { settings(); });
    });
  }

  window.VitimConsent = {
    get: function () { return { necessary: true, preferences: !!(state && state.preferences), statistics: !!(state && state.statistics), marketing: !!(state && state.marketing), decided: !!(state && state.version === cfg.version) }; },
    open: function () { if (cfg) settings(); },
    on: function (fn) { if (typeof fn === 'function') listeners.push(fn); }
  };
  window.VitimCookies = {
    start: function (config, context) {
      cfg = config; ctx = context;
      var s = read();
      document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('a[href="#vitim-cookies"]');
        if (a) { e.preventDefault(); settings(); }
      });
      policies();
      if (s && s.version === cfg.version) { apply(s, false); reopenButton(); }
      else { if (s) { state = { id: s.id }; } banner(); if (ctx && ctx.onChange) ctx.onChange(window.VitimConsent.get()); }
    }
  };
})();
