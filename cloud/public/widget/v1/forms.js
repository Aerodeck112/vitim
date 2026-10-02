/*! VITIM formulare 1.0 — formularele de abonare (popup, flyout, bară, încorporat). Se încarcă automat de loader.js. */
(function () {
  'use strict';
  if (window.VitimForms) return;

  var CSS = ':host{all:initial}*{box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif}'
    + '.ov{position:fixed;inset:0;background:rgba(15,23,42,.55);display:flex;align-items:center;justify-content:center;padding:16px;z-index:2147483600;animation:f .25s ease}'
    + '.box{position:relative;background:var(--bg);color:#0f172a;border-radius:16px;box-shadow:0 24px 60px rgba(0,0,0,.28);overflow:hidden;width:100%;display:flex}'
    + '.vf-popup .box{max-width:720px;min-height:320px;animation:u .3s ease}.vf-popup .box.noimg{max-width:440px}'
    + '.img{flex:0 0 44%;background-size:cover;background-position:center}.in{flex:1;padding:34px 30px 26px;display:flex;flex-direction:column;justify-content:center}'
    + '.fly{position:fixed;bottom:20px;width:340px;max-width:calc(100vw - 32px);z-index:2147483600;animation:u .3s ease}.fly.left{left:20px}.fly.right{right:20px}.fly .box{flex-direction:column}.fly .img{flex:0 0 140px;height:140px}.fly .in{padding:22px 20px 18px}'
    + '.bar{position:fixed;left:0;right:0;z-index:2147483600;background:var(--c);color:#fff;padding:10px 46px 10px 16px;display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap;animation:f .3s ease}.bar.top{top:0}.bar.bottom{bottom:0}'
    + '.bar h2{font-size:15px;margin:0;color:#fff}.bar form{display:flex;gap:8px;margin:0;flex-wrap:wrap}.bar input[type=email]{width:220px;height:38px;margin:0}.bar .btn{width:auto;height:38px;padding:0 18px;margin:0;background:#fff;color:var(--c);white-space:nowrap}.bar h2{flex:0 1 auto;min-width:0}.bar .x{color:#fff}.bar .legal{display:none}.bar .msg{color:#fff}'
    + '.emb .box{box-shadow:none;border:1px solid #e5e8f0}.emb .in{padding:24px}'
    + 'h2{margin:0 0 8px;font-size:24px;line-height:1.2;font-weight:800;letter-spacing:-.01em}p{margin:0 0 16px;font-size:15px;line-height:1.5;color:#334155}'
    + 'input[type=email],input[type=text],input[type=tel]{display:block;width:100%;height:46px;border:1px solid #cbd5e1;border-radius:10px;padding:0 14px;font-size:15px;margin:0 0 10px;background:#fff;color:#0f172a}'
    + 'input:focus{outline:2px solid var(--c);outline-offset:1px}'
    + '.btn{display:block;width:100%;height:48px;border:0;border-radius:10px;background:var(--c);color:#fff;font-size:16px;font-weight:700;cursor:pointer;margin-top:4px}.btn:hover{filter:brightness(1.08)}.btn[disabled]{opacity:.6;cursor:wait}'
    + '.chk{display:flex;gap:8px;align-items:flex-start;font-size:13px;color:#334155;margin:2px 0 10px;line-height:1.4}.chk input{margin-top:2px;accent-color:var(--c)}'
    + '.legal{font-size:11.5px;color:#64748b;margin:10px 0 0;line-height:1.45}.legal a{color:inherit}'
    + '.x{position:absolute;top:10px;right:10px;width:32px;height:32px;border:0;border-radius:50%;background:rgba(15,23,42,.06);color:#0f172a;font-size:20px;line-height:32px;cursor:pointer;padding:0}.x:hover{background:rgba(15,23,42,.12)}'
    + '.err{color:#dc2626;font-size:13px;margin:0 0 8px}.msg{font-size:15px}.coupon{display:inline-block;margin:6px 0 4px;padding:8px 14px;border:2px dashed var(--c);border-radius:10px;font-size:22px;font-weight:800;letter-spacing:.06em;color:var(--c)}'
    + '.hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}'
    + '@keyframes f{from{opacity:0}to{opacity:1}}@keyframes u{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}'
    + '@media (max-width:640px){.img{display:none}.in{padding:28px 22px 22px}h2{font-size:21px}.bar{padding-right:42px}.bar input[type=email]{width:100%}}'
    + '.pv .ov,.pv .fly,.pv .bar{position:absolute}';

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function put(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }

  function markup(f) {
    var c = f.content || {}, fields = c.fields || ['email'], bar = f.type === 'bar';
    var inputs = (fields.indexOf('first_name') >= 0 && !bar ? '<input type="text" name="first_name" autocomplete="given-name" placeholder="Prenume" aria-label="Prenume" maxlength="80">' : '')
      + '<input type="email" name="email" autocomplete="email" placeholder="Adresa de email" aria-label="Adresa de email" required maxlength="190">'
      + (fields.indexOf('phone') >= 0 && !bar ? '<input type="tel" name="phone" autocomplete="tel" placeholder="Telefon (opțional)" aria-label="Telefon" maxlength="30">' : '')
      + (c.sms && fields.indexOf('phone') >= 0 && !bar ? '<label class="chk"><input type="checkbox" name="sms"> <span>' + esc(f.sms_consent) + '</span></label>' : '')
      + '<div class="hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>';
    var legal = '<p class="legal">' + esc(f.consent) + (f.privacy_url ? ' <a href="' + esc(f.privacy_url) + '" target="_blank" rel="noopener">Politica de confidențialitate</a>' : '') + '</p>';
    if (bar) return '<h2>' + esc(c.title) + '</h2><form novalidate>' + inputs + '<button class="btn" type="submit">' + esc(c.button) + '</button></form><div class="err" hidden></div>' + legal;
    return (c.title ? '<h2 id="vf-t' + f.id + '">' + esc(c.title) + '</h2>' : '') + (c.text ? '<p>' + esc(c.text).replace(/\n/g, '<br>') + '</p>' : '')
      + '<form novalidate>' + inputs + '<div class="err" hidden></div><button class="btn" type="submit">' + esc(c.button) + '</button></form>' + legal;
  }

  function success(f, status) {
    var c = f.content || {};
    return '<h2>' + esc(c.success_title || 'Mulțumim!') + '</h2>' + (c.success_text ? '<p>' + esc(c.success_text) + '</p>' : '')
      + (status === 'confirm' ? '<p class="msg">📩 Ți-am trimis un email. Apasă pe linkul din el ca să confirmi abonarea' + (c.coupon ? ' și să primești codul' : '') + '.</p>'
        : (c.coupon ? '<p class="msg">Codul tău de reducere:</p><div class="coupon">' + esc(c.coupon) + '</div>' : ''));
  }

  function mount(f, ctx, target) {
    var host = document.createElement('div'), root = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host, c = f.content || {};
    host.setAttribute('data-vitim-form-host', f.id);
    var style = document.createElement('style'); style.textContent = CSS; root.appendChild(style);
    var wrap = document.createElement('div');
    wrap.className = 'vf-' + f.type + (ctx.preview ? ' pv' : '');
    wrap.style.setProperty('--c', c.color || '#2f6bff'); wrap.style.setProperty('--bg', c.background || '#ffffff');
    var img = c.image_url && f.type !== 'bar' ? '<div class="img" style="background-image:url(\'' + esc(c.image_url).replace(/'/g, '%27') + '\')"></div>' : '';
    var close = f.type === 'embed' ? '' : '<button type="button" class="x" aria-label="Închide">×</button>';
    if (f.type === 'popup') wrap.innerHTML = '<div class="ov"><div class="box' + (img ? '' : ' noimg') + '" role="dialog" aria-modal="true" aria-labelledby="vf-t' + f.id + '">' + img + '<div class="in">' + markup(f) + '</div>' + close + '</div></div>';
    else if (f.type === 'flyout') wrap.innerHTML = '<div class="fly ' + (c.position === 'left' ? 'left' : 'right') + '"><div class="box" role="dialog" aria-labelledby="vf-t' + f.id + '">' + img + '<div class="in">' + markup(f) + '</div>' + close + '</div></div>';
    else if (f.type === 'bar') wrap.innerHTML = '<div class="bar ' + (c.position === 'bottom' ? 'bottom' : 'top') + '" role="region" aria-label="Abonare">' + markup(f) + close + '</div>';
    else wrap.innerHTML = '<div class="emb"><div class="box">' + img + '<div class="in">' + markup(f) + '</div></div></div>';
    root.appendChild(wrap);
    (target || document.body).appendChild(host);

    function dismiss() { if (!ctx.preview) put('vitim_form_' + f.id, String(Date.now())); host.parentNode && host.parentNode.removeChild(host); document.removeEventListener('keydown', onKey); }
    function onKey(e) { if (e.key === 'Escape' && f.type !== 'embed') dismiss(); }
    var x = root.querySelector('.x'); if (x) x.addEventListener('click', dismiss);
    if (f.type === 'popup') {
      document.addEventListener('keydown', onKey);
      root.querySelector('.ov').addEventListener('click', function (e) { if (e.target === e.currentTarget) dismiss(); });
      if (!ctx.preview) { var first = root.querySelector('input:not([tabindex])'); if (first) setTimeout(function () { first.focus(); }, 50); }
    }
    var form = root.querySelector('form'), err = root.querySelector('.err'), btn = root.querySelector('.btn');
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var v = function (n) { var el = form.querySelector('[name=' + n + ']'); return el ? (el.type === 'checkbox' ? el.checked : el.value.trim()) : ''; };
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v('email'))) { err.textContent = 'Scrie o adresă de email validă.'; err.hidden = false; return; }
      err.hidden = true; btn.disabled = true;
      var done = function (status) {
        if (!ctx.preview) put('vitim_form_' + f.id, 'sub');
        var area = f.type === 'bar' ? root.querySelector('.bar') : root.querySelector('.in');
        area.innerHTML = success(f, status) + (f.type === 'bar' ? close : '');
        var x2 = area.querySelector('.x'); if (x2) x2.addEventListener('click', dismiss);
      };
      if (ctx.preview) { setTimeout(function () { done(ctx.previewStatus || 'confirm'); }, 300); return; }
      ctx.call('forms/submit', { form: f.id, email: v('email'), first_name: v('first_name'), phone: v('phone'), sms: v('sms') === true, website: v('website'), page: location.href.slice(0, 255) })
        .then(function (j) {
          if (j && j.status) done(j.status);
          else { err.textContent = (j && j.error) || 'Nu am putut trimite. Încearcă din nou.'; err.hidden = false; btn.disabled = false; }
        }, function () { err.textContent = 'Nu am putut trimite. Verifică conexiunea și încearcă din nou.'; err.hidden = false; btn.disabled = false; });
    });
    if (!ctx.preview) ctx.call('forms/view', { form: f.id }).catch(function () {});
    return host;
  }

  function allowed(f) {
    var b = f.behavior || {}, mobile = window.matchMedia && window.matchMedia('(max-width: 768px)').matches, url = location.pathname + location.search;
    if (b.devices === 'mobile' && !mobile) return false;
    if (b.devices === 'desktop' && mobile) return false;
    var lines = function (s) { return String(s || '').split('\n').map(function (l) { return l.trim(); }).filter(Boolean); };
    var inc = lines(b.include), exc = lines(b.exclude);
    if (inc.length && !inc.some(function (p) { return url.indexOf(p) >= 0; })) return false;
    if (exc.some(function (p) { return url.indexOf(p) >= 0; })) return false;
    var seen = get('vitim_form_' + f.id);
    if (seen === 'sub') return false;
    if (seen && (Date.now() - +seen) < (b.frequency_days || 0) * 86400000) return false;
    return true;
  }

  function when(f, show) {
    var b = f.behavior || {}, fired = false, fire = function () { if (!fired) { fired = true; show(); } };
    if (b.trigger === 'immediate') return fire();
    if (b.trigger === 'delay') return setTimeout(fire, (b.delay || 0) * 1000);
    if (b.trigger === 'scroll' || b.trigger === 'exit') {
      var pct = b.trigger === 'exit' ? 50 : (b.scroll || 40);
      var onScroll = function () {
        var h = document.documentElement.scrollHeight - window.innerHeight;
        if (h <= 0 || (window.scrollY / h) * 100 >= pct) { window.removeEventListener('scroll', onScroll); fire(); }
      };
      if (b.trigger === 'exit' && !(window.matchMedia && window.matchMedia('(max-width: 768px)').matches)) {
        // intenția de ieșire: cursorul pleacă spre bara de adrese / taburi
        document.addEventListener('mouseout', function (e) { if (!e.relatedTarget && e.clientY <= 0) fire(); });
        return;
      }
      window.addEventListener('scroll', onScroll, { passive: true });
    }
  }

  window.VitimForms = {
    start: function (ctx) {
      var overlayShown = false;
      (ctx.forms || []).forEach(function (f) {
        if (f.type === 'embed') {
          var spots = document.querySelectorAll('[data-vitim-form="' + f.id + '"]');
          for (var i = 0; i < spots.length; i++) if (!spots[i].getAttribute('data-done')) { spots[i].setAttribute('data-done', '1'); mount(f, ctx, spots[i]); }
          return;
        }
        if (!allowed(f)) return;
        when(f, function () {
          if (f.type !== 'bar') { if (overlayShown) return; overlayShown = true; } // un singur popup / flyout pe pagină
          mount(f, ctx);
        });
      });
    },
    preview: function (f, container, status) {
      container.innerHTML = '';
      return mount(f, { preview: true, previewStatus: status }, container);
    }
  };
})();
