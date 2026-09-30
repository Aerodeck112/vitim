/* Bunătăți de la Michele — interacțiuni site (fără dependențe) */
(function () {
  'use strict';
  var B = window.BDM || { base: '' };
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var u = function (p) { return (B.base || '') + p; };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var getCookie = function (n) { var m = document.cookie.match('(?:^|; )' + n + '=([^;]*)'); return m ? decodeURIComponent(m[1]) : null; };
  var setCookie = function (n, v, days) { var s = location.protocol === 'https:' ? ';secure' : ''; document.cookie = n + '=' + encodeURIComponent(v) + ';path=' + (B.base || '') + '/;max-age=' + days * 86400 + ';samesite=lax' + s; };

  // ---------- toast ----------
  var toastT;
  function toast(msg) {
    var t = $('#toast'); if (!t) return;
    t.innerHTML = '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg><span>' + esc(msg) + '</span>';
    t.classList.add('show'); clearTimeout(toastT); toastT = setTimeout(function () { t.classList.remove('show'); }, 3200);
  }

  // ---------- antet ----------
  var header = $('.site-header');
  var onScroll = function () { if (header) header.classList.toggle('scrolled', window.scrollY > 8); };
  window.addEventListener('scroll', onScroll, { passive: true }); onScroll();

  var sBtn = $('[data-search]'), sBar = $('#search-bar');
  if (sBtn && sBar) sBtn.addEventListener('click', function () { var o = sBar.classList.toggle('open'); sBtn.setAttribute('aria-expanded', o); if (o) $('input', sBar).focus(); });

  // meniu mobil
  var mnav = $('#mnav');
  function setMnav(open) { if (!mnav) return; mnav.classList.toggle('open', open); mnav.setAttribute('aria-hidden', !open); document.body.style.overflow = open ? 'hidden' : ''; $$('[data-mnav]').forEach(function (b) { b.setAttribute('aria-expanded', open); }); }
  $$('[data-mnav]').forEach(function (b) { b.addEventListener('click', function () { setMnav(true); }); });
  $$('[data-mnav-close]').forEach(function (b) { b.addEventListener('click', function () { setMnav(false); }); });

  // ---------- coș ----------
  var countEl = $('[data-cart-count]');
  function setCount(n, bump) {
    if (!countEl) return; n = parseInt(n, 10) || 0;
    countEl.textContent = n > 99 ? '99+' : n; countEl.classList.toggle('on', n > 0);
    if (bump) { countEl.classList.remove('bump'); void countEl.offsetWidth; countEl.classList.add('bump'); }
  }
  setCount(getCookie('bdm_cn'));

  var drawer = $('#cart-drawer'), lastFocus = null;
  function renderCart(d, msg, err) {
    var body = $('[data-cart-body]'), foot = $('[data-cart-foot]');
    if (!body) return;
    var h = msg ? '<div class="msg' + (err ? ' err' : '') + '">' + esc(msg) + '</div>' : '';
    if (!d.lines || !d.lines.length) {
      h += '<div class="empty-cart"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/></svg><p>Coșul este gol.</p><a class="btn btn-sm" href="' + u('/produse') + '">Vezi produsele</a></div>';
      foot.hidden = true;
    } else {
      d.lines.forEach(function (l) {
        h += '<div class="mini">' + (l.image ? '<img src="' + esc(l.image) + '" alt="" width="72" height="72">' : '<span></span>') +
          '<div><a href="' + esc(l.url) + '">' + esc(l.name) + '</a><small>' + l.qty + ' × ' + esc(l.price) + (l.available ? '' : ' · indisponibil') + '</small></div>' +
          '<div style="text-align:right"><strong>' + esc(l.total) + '</strong><br><button class="rm" type="button" data-rm="' + l.id + '" aria-label="Elimină ' + esc(l.name) + '">Elimină</button></div></div>';
      });
      if (d.free_left) h += '<p class="small muted" style="margin:14px 0 0">Mai adaugă ' + esc(d.free_left) + ' pentru livrare gratuită.</p>';
      foot.hidden = false; $('[data-cart-subtotal]').textContent = d.subtotal;
    }
    body.innerHTML = h;
    setCount(d.count);
  }
  function openCart(data, msg, err) {
    if (!drawer) { location.href = u('/cos'); return; }
    lastFocus = document.activeElement;
    drawer.classList.add('open'); drawer.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden';
    if (data) renderCart(data, msg, err);
    else fetch(u('/api/cos'), { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) { renderCart(d); });
    setTimeout(function () { var c = $('[data-cart-close].icon-btn', drawer); if (c) c.focus(); }, 60);
  }
  function closeCart() { if (!drawer) return; drawer.classList.remove('open'); drawer.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; if (lastFocus) lastFocus.focus(); }
  $$('[data-cart-open]').forEach(function (a) {
    a.addEventListener('click', function (e) { if (location.pathname === u('/cos') || location.pathname === u('/finalizare')) return; e.preventDefault(); openCart(); });
  });
  $$('[data-cart-close]').forEach(function (b) { b.addEventListener('click', closeCart); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeCart(); setMnav(false); } });
  if (drawer) drawer.addEventListener('click', function (e) {
    var b = e.target.closest('[data-rm]'); if (!b) return;
    var fd = new FormData(); fd.append('remove', b.getAttribute('data-rm'));
    post(u('/cos/actualizeaza'), fd).then(function (d) { renderCart(d, 'Produsul a fost eliminat.'); });
  });

  function post(url, fd) {
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'A apărut o eroare. Reîncearcă.' }; }); });
  }

  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.matches('[data-add]')) return;
    var sub = e.submitter;
    if (sub && sub.hasAttribute('data-buy-now')) return; // „Cumpără acum” merge direct la finalizare
    e.preventDefault();
    var btn = sub || $('button[type=submit]', f); if (btn) btn.classList.add('loading');
    post(f.action, new FormData(f)).then(function (d) {
      if (btn) btn.classList.remove('loading');
      if (d.ok) {
        setCount(d.count, true);
        if (btn && btn.classList.contains('add-btn')) { btn.classList.add('done'); setTimeout(function () { btn.classList.remove('done'); }, 1400); }
        openCart(d, d.message);
        if (typeof gtag === 'function') gtag('event', 'add_to_cart', { currency: 'RON' });
        if (typeof fbq === 'function') fbq('track', 'AddToCart');
      } else { toast(d.message || 'Nu am putut adăuga produsul.'); }
    }).catch(function () { if (btn) btn.classList.remove('loading'); f.submit(); });
  });

  // ---------- cantitate ----------
  $$('[data-qty]').forEach(function (q) {
    var inp = $('input', q);
    var step = function (dir) {
      var s = parseInt(inp.step, 10) || 1, min = parseInt(inp.min, 10), max = parseInt(inp.max, 10) || 9999, v = (parseInt(inp.value, 10) || 0) + dir * s;
      if (!isNaN(min)) v = Math.max(min, v); v = Math.min(max, v);
      inp.value = v; inp.dispatchEvent(new Event('change', { bubbles: true }));
    };
    $('[data-dec]', q).addEventListener('click', function () { step(-1); });
    $('[data-inc]', q).addEventListener('click', function () { step(1); });
  });
  var cartForm = $('[data-cart-form]'), cartT;
  if (cartForm) cartForm.addEventListener('change', function (e) {
    if (!e.target.matches('[data-autosubmit]')) return;
    clearTimeout(cartT); cartT = setTimeout(function () { cartForm.submit(); }, 600);
  });

  // ---------- galerie ----------
  var gal = $('[data-gallery]');
  if (gal) {
    var main = $('[data-main]', gal), box = $('[data-zoom]', gal);
    $$('.thumbs button', gal).forEach(function (b) {
      b.addEventListener('click', function () {
        $$('.thumbs button', gal).forEach(function (x) { x.removeAttribute('aria-current'); }); b.setAttribute('aria-current', 'true');
        if (main) { main.src = b.getAttribute('data-src'); var ss = b.getAttribute('data-srcset'); if (ss) main.srcset = ss; else main.removeAttribute('srcset'); }
      });
    });
    if (box && main && window.matchMedia('(hover:hover)').matches) {
      box.addEventListener('mousemove', function (e) { var r = box.getBoundingClientRect(); main.style.transformOrigin = ((e.clientX - r.left) / r.width * 100) + '% ' + ((e.clientY - r.top) / r.height * 100) + '%'; });
      box.addEventListener('mouseenter', function () { box.classList.add('zoom'); });
      box.addEventListener('mouseleave', function () { box.classList.remove('zoom'); });
    }
  }
  var sticky = $('[data-sticky-buy]'), buyForm = $('[data-buy-form]');
  if (sticky && buyForm && 'IntersectionObserver' in window) {
    new IntersectionObserver(function (en) { sticky.classList.toggle('show', !en[0].isIntersecting && en[0].boundingClientRect.top < 0); }).observe(buyForm);
    $('[data-sticky-add]', sticky).addEventListener('click', function () { buyForm.requestSubmit ? buyForm.requestSubmit() : buyForm.submit(); });
  }

  // ---------- slider ----------
  var slides = $('[data-slides]'), dots = $$('[data-dots] button');
  if (slides && dots.length) {
    var idx = 0, timer;
    var go = function (i) { idx = (i + dots.length) % dots.length; slides.scrollTo({ left: slides.clientWidth * idx, behavior: 'smooth' }); };
    dots.forEach(function (d, i) { d.addEventListener('click', function () { go(i); restart(); }); });
    slides.addEventListener('scroll', function () {
      var i = Math.round(slides.scrollLeft / slides.clientWidth);
      if (i !== idx) idx = i;
      dots.forEach(function (d, j) { if (j === i) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current'); });
    }, { passive: true });
    var restart = function () { clearInterval(timer); if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) timer = setInterval(function () { go(idx + 1); }, 7000); };
    slides.addEventListener('mouseenter', function () { clearInterval(timer); });
    slides.addEventListener('mouseleave', restart);
    restart();
  }

  // ---------- apariție la derulare ----------
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (en) { en.forEach(function (x) { if (x.isIntersecting) { x.target.classList.add('in'); io.unobserve(x.target); } }); }, { rootMargin: '0px 0px -60px 0px' });
    $$('.reveal').forEach(function (el) { io.observe(el); });
  } else { $$('.reveal').forEach(function (el) { el.classList.add('in'); }); }

  $$('[data-toggle]').forEach(function (b) { b.addEventListener('click', function () { var t = $(b.getAttribute('data-toggle')); if (t) { t.hidden = !t.hidden; if (!t.hidden) { var i = $('input:not([type=hidden]):not(.hp)', t); if (i) i.focus(); } } }); });
  $$('.tabs a').forEach(function (a) { a.addEventListener('click', function () { $$('.tabs a').forEach(function (x) { x.classList.remove('on'); }); a.classList.add('on'); }); });

  // ---------- formulare AJAX (contact, recenzii) ----------
  $$('form[data-ajax]').forEach(function (f) {
    var tk = $('input[name=_t]', f);
    var loadToken = function () { fetch(u('/api/form-token'), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) { if (tk) tk.value = d.t; }); };
    loadToken();
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = $('button[type=submit]', f), msg = $('.form-msg', f);
      $$('.field.has-err', f).forEach(function (x) { x.classList.remove('has-err'); var er = $('.err', x); if (er) er.remove(); });
      btn.classList.add('loading');
      post(f.getAttribute('data-ajax'), new FormData(f)).then(function (d) {
        btn.classList.remove('loading');
        if (d.ok) { f.reset(); msg.innerHTML = '<div class="alert alert-ok">' + esc(d.message) + '</div>'; loadToken(); if (typeof gtag === 'function') gtag('event', 'generate_lead'); }
        else {
          msg.innerHTML = '<div class="alert alert-err">' + esc(d.error || 'Nu am putut trimite. Reîncearcă.') + '</div>';
          Object.keys(d.errors || {}).forEach(function (k) { var i = $('[name="' + k + '"]', f); if (i) { var fl = i.closest('.field'); if (fl) { fl.classList.add('has-err'); fl.insertAdjacentHTML('beforeend', '<span class="err">' + esc(d.errors[k]) + '</span>'); } } });
        }
      }).catch(function () { btn.classList.remove('loading'); msg.innerHTML = '<div class="alert alert-err">Conexiune întreruptă. Reîncearcă.</div>'; });
    });
  });

  // ---------- finalizare comandă ----------
  var co = $('[data-checkout]');
  if (co) {
    var cfg = JSON.parse(co.getAttribute('data-checkout'));
    var fmt = function (v) { return v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' ' + (B.currency || 'lei'); };
    var sync = function () {
      var pj = ($('[data-ctype]:checked', co) || {}).value === 'pj';
      $('[data-company]', co).hidden = !pj;
      $('[data-ship-box]', co).hidden = $('[data-ship-same]', co).checked;
      var sm = ($('input[name=shipping_method]:checked', co) || {}).value || 'curier';
      var pm = ($('input[name=payment_method]:checked', co) || {}).value || '';
      var ship = cfg.ship[sm] || 0, net = cfg.subtotal - cfg.discount;
      if ((cfg.freeOver > 0 && sm === 'curier' && net >= cfg.freeOver) || cfg.couponFreeShip) ship = 0;
      var fee = cfg.fees[pm] || 0;
      $('[data-sum-ship]', co).textContent = ship > 0 ? fmt(ship) : 'Gratuit';
      $('[data-sum-fee-row]', co).hidden = fee <= 0; $('[data-sum-fee]', co).textContent = fmt(fee);
      $('[data-sum-total]', co).textContent = fmt(Math.max(0, net) + ship + fee);
      $('[data-place-label]', co).textContent = pm === 'card' ? 'Plătește ' + fmt(Math.max(0, net) + ship + fee) : 'Plasează comanda';
      $('[data-card-note]', co).hidden = pm !== 'card';
    };
    co.addEventListener('change', sync); sync();
    co.addEventListener('submit', function (e) {
      var first = null;
      $$('[required]', co).forEach(function (i) { if (i.closest('[hidden]')) return; var ok = i.type === 'checkbox' ? i.checked : i.value.trim() !== ''; var fl = i.closest('.field') || i.closest('.check'); if (fl) fl.classList.toggle('has-err', !ok); if (!ok && !first) first = i; });
      if (first) { e.preventDefault(); first.focus(); first.scrollIntoView({ block: 'center', behavior: 'smooth' }); toast('Completează câmpurile obligatorii.'); return; }
      var b = $('[data-place]', co); b.classList.add('loading'); b.setAttribute('aria-busy', 'true');
    });
  }

  // ---------- UTM (sursa comenzii, doar prima parte) ----------
  try {
    var sp = new URLSearchParams(location.search), utm = {};
    ['utm_source', 'utm_medium', 'utm_campaign', 'gclid', 'fbclid'].forEach(function (k) { if (sp.get(k)) utm[k] = sp.get(k).slice(0, 80); });
    if (Object.keys(utm).length) { utm.landing = location.pathname; setCookie('bdm_utm', JSON.stringify(utm), 30); }
    else if (!getCookie('bdm_utm') && document.referrer && document.referrer.indexOf(location.host) < 0) setCookie('bdm_utm', JSON.stringify({ referrer: document.referrer.slice(0, 120), landing: location.pathname }), 30);
  } catch (e) {}

  // ---------- cookies (Consent Mode v2) ----------
  var cb = $('#consent');
  function applyConsent(c) {
    if (typeof gtag === 'function') gtag('consent', 'update', { analytics_storage: c.analytics ? 'granted' : 'denied', ad_storage: c.marketing ? 'granted' : 'denied', ad_user_data: c.marketing ? 'granted' : 'denied', ad_personalization: c.marketing ? 'granted' : 'denied' });
    if ((c.analytics || c.marketing) && window.bdmLoadTags) window.bdmLoadTags(c);
  }
  var saved = null; try { saved = JSON.parse(getCookie('bdm_consent') || 'null'); } catch (e) {}
  if (saved) applyConsent(saved); else if (cb) setTimeout(function () { cb.classList.add('show'); }, 900);
  function save(c) { setCookie('bdm_consent', JSON.stringify(c), 180); applyConsent(c); if (cb) cb.classList.remove('show', 'custom'); }
  if (cb) {
    cb.addEventListener('click', function (e) {
      var b = e.target.closest('[data-consent]'); if (!b) return;
      var a = b.getAttribute('data-consent');
      if (a === 'all') save({ analytics: true, marketing: true });
      else if (a === 'none') save({ analytics: false, marketing: false });
      else if (a === 'prefs') cb.classList.add('custom');
      else if (a === 'save') save({ analytics: $('[data-c=analytics]', cb).checked, marketing: $('[data-c=marketing]', cb).checked });
    });
  }
  $$('[data-open-consent]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); if (cb) { cb.classList.add('show', 'custom'); if (saved) { $('[data-c=analytics]', cb).checked = !!saved.analytics; $('[data-c=marketing]', cb).checked = !!saved.marketing; } } }); });
})();
