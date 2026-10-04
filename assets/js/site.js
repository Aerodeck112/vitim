/* VITIM — interacțiuni site (vanilla JS, fără dependențe) */
(function () {
  'use strict';
  var d = document, root = d.documentElement;
  root.classList.remove('no-js');
  var cfg = window.VITIM || {};

  function $(s, c) { return (c || d).querySelector(s); }
  function $$(s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); }
  function track(event, params) {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(Object.assign({ event: event }, params || {}));
    if (event === 'generate_lead') { try { window.dispatchEvent(new Event('vitim:lead')); } catch (e) {} }
    if (typeof window.fbq === 'function' && event === 'generate_lead') window.fbq('track', 'Lead', params || {});
  }
  window.vitimTrack = track;

  /* Temă luminoasă / întunecată */
  $$('[data-theme-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', t);
      try { localStorage.setItem('theme', t); } catch (e) {}
      var m = $('meta[name="theme-color"]'); if (m) m.setAttribute('content', t === 'dark' ? '#05070d' : '#f7f8fc');
    });
  });

  /* Header la scroll */
  var header = $('.site-header');
  function onScroll() { if (header) header.classList.toggle('scrolled', window.scrollY > 8); }
  window.addEventListener('scroll', onScroll, { passive: true }); onScroll();

  /* Mega meniu */
  $$('.menu [data-mega]').forEach(function (btn) {
    var li = btn.parentElement, t;
    function open() { clearTimeout(t); li.classList.add('open'); btn.setAttribute('aria-expanded', 'true'); }
    function close() { t = setTimeout(function () { li.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); }, 120); }
    btn.addEventListener('click', function (e) { e.preventDefault(); li.classList.contains('open') ? close() : open(); });
    li.addEventListener('mouseenter', open); li.addEventListener('mouseleave', close);
    li.addEventListener('focusout', function (e) { if (!li.contains(e.relatedTarget)) close(); });
  });
  d.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { $$('.menu li.open').forEach(function (li) { li.classList.remove('open'); }); closeDrawer(); }
  });

  /* Drawer mobil */
  var drawer = $('#drawer');
  function closeDrawer() { if (drawer) { drawer.classList.remove('open'); d.body.style.overflow = ''; } }
  $$('[data-drawer-open]').forEach(function (b) { b.addEventListener('click', function () { drawer.classList.add('open'); d.body.style.overflow = 'hidden'; var c = $('.close', drawer); if (c) c.focus(); }); });
  $$('[data-drawer-close]').forEach(function (b) { b.addEventListener('click', closeDrawer); });

  /* Reveal la scroll */
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    $$('[data-reveal]').forEach(function (el, i) { el.style.transitionDelay = (el.dataset.delay || 0) + 'ms'; io.observe(el); });
  } else { $$('[data-reveal]').forEach(function (el) { el.classList.add('in'); }); }

  /* Spotlight pe carduri */
  $$('.card').forEach(function (c) {
    c.addEventListener('pointermove', function (e) {
      var r = c.getBoundingClientRect();
      c.style.setProperty('--mx', (e.clientX - r.left) + 'px'); c.style.setProperty('--my', (e.clientY - r.top) + 'px');
    });
  });

  /* Filtre categorii servicii */
  $$('[data-cat-tabs]').forEach(function (tabs) {
    var grid = $(tabs.getAttribute('data-cat-tabs'));
    $$('button', tabs).forEach(function (b) {
      b.addEventListener('click', function () {
        $$('button', tabs).forEach(function (x) { x.setAttribute('aria-selected', 'false'); });
        b.setAttribute('aria-selected', 'true');
        var cat = b.dataset.cat;
        $$('[data-cat]', grid).forEach(function (card) { card.hidden = !(cat === 'all' || card.dataset.cat === cat); });
      });
    });
  });

  /* Consolă agent AI (hero) */
  var con = $('[data-console]');
  if (con) {
    var lines = $$('.ln', con), i = 0;
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    function next() {
      if (i < lines.length) { lines[i].classList.add('show'); i++; setTimeout(next, reduce ? 0 : 520 + Math.random() * 380); }
      else if (!reduce) { setTimeout(function () { lines.forEach(function (l) { l.classList.remove('show'); }); i = 0; setTimeout(next, 500); }, 6000); }
    }
    setTimeout(next, 400);
  }

  /* Cuprins activ */
  var tocLinks = $$('.toc a');
  if (tocLinks.length && 'IntersectionObserver' in window) {
    var map = {};
    tocLinks.forEach(function (a) { map[a.getAttribute('href').slice(1)] = a; });
    var tio = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { tocLinks.forEach(function (a) { a.classList.remove('active'); }); var a = map[en.target.id]; if (a) a.classList.add('active'); }
      });
    }, { rootMargin: '-20% 0px -70% 0px' });
    Object.keys(map).forEach(function (id) { var h = d.getElementById(id); if (h) tio.observe(h); });
  }

  /* Formulare (contact + newsletter) cu token proaspăt și trimitere AJAX */
  var tokenPromise = null;
  function getToken() {
    if (!tokenPromise) {
      tokenPromise = fetch(cfg.base + '/api/form-token', { credentials: 'same-origin', cache: 'no-store' })
        .then(function (r) { return r.json(); }).then(function (j) { return j.token; }).catch(function () { return ''; });
    }
    return tokenPromise;
  }
  $$('form[data-ajax]').forEach(function (form) {
    var started = false;
    form.addEventListener('focusin', function () { if (!started) { started = true; getToken(); } });
    // UTM + pagină de origine
    var f = function (n, v) { var i = form.querySelector('[name="' + n + '"]'); if (i && !i.value) i.value = v; };
    try {
      var p = new URLSearchParams(location.search);
      ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'].forEach(function (k) {
        var v = p.get(k) || sessionStorage.getItem('v_' + k); if (v) { sessionStorage.setItem('v_' + k, v); f(k, v); }
      });
      f('page', location.pathname);
      f('referrer', sessionStorage.getItem('v_ref') || d.referrer || '');
      if (!sessionStorage.getItem('v_ref')) sessionStorage.setItem('v_ref', d.referrer || 'direct');
    } catch (e) {}

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = $('button[type=submit]', form), msg = $('.form-msg', form);
      if (!form.checkValidity()) { form.reportValidity(); return; }
      btn.disabled = true; var label = btn.innerHTML; btn.innerHTML = 'Se trimite…';
      if (msg) { msg.className = 'form-msg'; msg.textContent = ''; }
      getToken().then(function (tok) {
        var fd = new FormData(form); fd.set('_t', tok || '');
        return fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
      }).then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Eroare de rețea.' }; }); })
        .then(function (j) {
          btn.disabled = false; btn.innerHTML = label;
          if (j.ok) {
            track(form.dataset.event || 'generate_lead', { form: form.dataset.form || 'contact', service: (form.querySelector('[name=service]') || {}).value || '' });
            if (j.redirect) { location.href = j.redirect; return; }
            form.reset();
            if (msg) { msg.className = 'form-msg ok'; msg.textContent = j.message; }
          } else if (msg) { msg.className = 'form-msg err'; msg.textContent = j.message || 'A apărut o eroare. Încearcă din nou.'; }
          tokenPromise = null;
        }).catch(function () {
          btn.disabled = false; btn.innerHTML = label;
          if (msg) { msg.className = 'form-msg err'; msg.textContent = 'Nu s-a putut trimite. Verifică conexiunea sau sună-ne direct.'; }
        });
    });
  });


  /* Abonamente: mini configurator → completează formularul de evaluare (nu trimite nimic singur) */
  var cfgForm = $('[data-configurator]');
  if (cfgForm) {
    cfgForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var target = $('#contact form[data-ajax]') || $('form[data-ajax][data-form="contact"]');
      if (!target) { location.href = cfg.base + '/contact'; return; }
      var pc = cfgForm.elements.pc.value, loc = cfgForm.elements.loc.value;
      var svc = $$('input[name=svc]:checked', cfgForm).map(function (i) { return i.value; });
      var lines = ['Aș dori o evaluare a infrastructurii pentru un abonament VITIM.'];
      if (pc) lines.push('Calculatoare: ' + pc);
      if (loc) lines.push('Locații: ' + loc);
      if (svc.length) lines.push('Servicii: ' + svc.join(', '));
      var msg = target.querySelector('[name=message]');
      if (msg && !msg.value.trim()) msg.value = lines.join('\n');
      var more = target.querySelector('.form-more');
      if (more) more.open = true;
      var pcSel = target.querySelector('[name=computers]');
      if (pcSel && pc) pcSel.value = pc;
      $$('input[name="needs[]"]', target).forEach(function (i) {
        var v = i.value === 'Mentenanță IT' ? 'IT' : i.value;
        if (svc.indexOf(v) > -1) i.checked = true;
      });
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      var first = target.querySelector('[name=name]');
      if (first) setTimeout(function () { first.focus({ preventScroll: true }); }, 450);
    });
  }
  $$('[data-focus-form]').forEach(function (a) {
    a.addEventListener('click', function () {
      var f = $(a.getAttribute('href')); var i = f && f.querySelector('[name=name]');
      if (i) setTimeout(function () { i.focus({ preventScroll: true }); }, 400);
    });
  });

  /* Chat cu asistentul */
  var chat = $('[data-chat]');
  if (chat) {
    var body = $('[data-chat-body]', chat), cform = $('[data-chat-form]', chat), cin = $('textarea', cform), fab = $('[data-chat-open]');
    var ctoken = ''; try { ctoken = localStorage.getItem('chat_token') || ''; } catch (e) {}
    var busy = false, loaded = false;
    function esc(t) { return t.replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    // markdown minimal și sigur: **bold**, liste, linkuri doar către site sau tel/mailto
    function md(t) {
      var lines = esc(t).split(/\n/), html = '', inList = false;
      lines.forEach(function (l) {
        l = l.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
          .replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function (m, txt, href) {
            var ok = /^\/(?!\/)/.test(href) || /^(tel:|mailto:)/.test(href) || href.indexOf(location.origin + '/') === 0;
            return ok ? '<a href="' + href + '">' + txt + '</a>' : txt;
          });
        var li = l.match(/^\s*(?:[-•*]|\d+\.)\s+(.*)$/);
        if (li) { if (!inList) { html += '<ul>'; inList = true; } html += '<li>' + li[1] + '</li>'; return; }
        if (inList) { html += '</ul>'; inList = false; }
        if (l.trim() !== '') html += '<p>' + l + '</p>';
      });
      if (inList) html += '</ul>';
      return html;
    }
    function add(role, text, cls) {
      var d = document.createElement('div'); d.className = 'msg ' + (role === 'user' ? 'me' : 'bot') + (cls ? ' ' + cls : '');
      if (role === 'user') d.textContent = text; else d.innerHTML = md(text);
      body.appendChild(d); body.scrollTop = body.scrollHeight; return d;
    }
    function openChat() {
      chat.hidden = false; fab.classList.add('hide'); setTimeout(function () { cin.focus(); }, 50);
      track('chat_open', {});
      getToken();
      if (!loaded && ctoken) {
        loaded = true;
        fetch(cfg.base + '/api/chat/' + encodeURIComponent(ctoken), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
          if (j.ok && j.messages && j.messages.length) { var s = $('[data-chat-sugg]', chat); if (s) s.remove(); j.messages.forEach(function (m) { add(m.role, m.text); }); }
        }).catch(function () {});
      }
    }
    function closeChat() { chat.hidden = true; fab.classList.remove('hide'); }
    fab.addEventListener('click', openChat);
    $('[data-chat-close]', chat).addEventListener('click', closeChat);
    try { if (sessionStorage.getItem('chat_open') === '1') openChat(); } catch (e) {}
    function send(text) {
      text = text.trim(); if (!text || busy) return;
      busy = true; var s = $('[data-chat-sugg]', chat); if (s) s.remove();
      add('user', text); cin.value = ''; cin.style.height = '';
      var typing = add('bot', '', 'typing'); typing.innerHTML = '<i></i><i></i><i></i>';
      try { sessionStorage.setItem('chat_open', '1'); } catch (e) {}
      getToken().then(function (tok) {
        var fd = new FormData(); fd.append('message', text); fd.append('token', ctoken); fd.append('page', location.pathname); fd.append('_t', tok || '');
        return fetch(cfg.base + '/api/chat', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
      }).then(function (r) { return r.json(); }).then(function (j) {
        typing.remove(); busy = false;
        if (j.token) { ctoken = j.token; try { localStorage.setItem('chat_token', ctoken); } catch (e) {} }
        add('bot', j.reply || j.message || 'A apărut o eroare. Încearcă din nou.', j.lead ? 'ok' : '');
        if (j.lead) track('generate_lead', { form: 'asistent-ai' });
        tokenPromise = null;
      }).catch(function () {
        typing.remove(); busy = false;
        add('bot', 'Nu am putut trimite mesajul. Verifică conexiunea sau sună-ne direct.');
      });
    }
    cform.addEventListener('submit', function (e) { e.preventDefault(); send(cin.value); });
    cin.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(cin.value); } });
    cin.addEventListener('input', function () { cin.style.height = 'auto'; cin.style.height = Math.min(cin.scrollHeight, 120) + 'px'; });
    chat.addEventListener('click', function (e) { var b = e.target.closest('[data-chat-sugg] button'); if (b) send(b.textContent); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !chat.hidden) closeChat(); });
  }

  /* Click-uri telefon / WhatsApp / email → evenimente de conversie */
  d.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]'); if (!a) return;
    var h = a.getAttribute('href');
    if (h.indexOf('tel:') === 0) track('click_phone', { location: a.dataset.loc || '' });
    else if (h.indexOf('https://wa.me') === 0) track('click_whatsapp', {});
    else if (h.indexOf('mailto:') === 0) track('click_email', {});
  });

  /* Consimțământ cookies (Google Consent Mode v2) */
  var banner = $('#consent');
  function readConsent() { try { return JSON.parse(localStorage.getItem('consent') || 'null'); } catch (e) { return null; } }
  function applyConsent(c) {
    try { localStorage.setItem('consent', JSON.stringify(c)); } catch (e) {}
    if (typeof window.gtag === 'function') {
      window.gtag('consent', 'update', {
        analytics_storage: c.analytics ? 'granted' : 'denied',
        ad_storage: c.marketing ? 'granted' : 'denied',
        ad_user_data: c.marketing ? 'granted' : 'denied',
        ad_personalization: c.marketing ? 'granted' : 'denied'
      });
    }
    if (window.vitimLoadTags) window.vitimLoadTags(c);
    if (banner) banner.classList.remove('show');
  }
  if (banner) {
    var saved = readConsent();
    if (saved) { if (window.vitimLoadTags) window.vitimLoadTags(saved); } else { setTimeout(function () { banner.classList.add('show'); }, 900); }
    $('[data-consent="all"]', banner).addEventListener('click', function () { applyConsent({ analytics: true, marketing: true, t: Date.now() }); });
    $('[data-consent="none"]', banner).addEventListener('click', function () { applyConsent({ analytics: false, marketing: false, t: Date.now() }); });
    $('[data-consent="prefs"]', banner).addEventListener('click', function () { banner.classList.toggle('expanded'); });
    $('[data-consent="save"]', banner).addEventListener('click', function () {
      applyConsent({ analytics: $('#c-an', banner).checked, marketing: $('#c-mk', banner).checked, t: Date.now() });
    });
    $$('[data-open-consent]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); banner.classList.add('show', 'expanded'); }); });
  }
})();
