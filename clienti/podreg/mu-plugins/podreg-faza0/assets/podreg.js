/* PODREG Faza 0: măsurare, consimțământ cookie-uri, precompletare formulare. */
(function () {
  'use strict';

  var dl = (window.dataLayer = window.dataLayer || []);

  function trimite(ev, date) {
    var o = { event: ev, pagina: location.pathname };
    for (var k in date || {}) o[k] = date[k];
    dl.push(o);
  }

  // ---------- Click-uri pe telefon, WhatsApp, email, ofertă ----------
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a');
    if (!a) return;
    var href = a.getAttribute('href') || '';
    var ev = a.getAttribute('data-podreg-ev');
    if (!ev) {
      if (href.indexOf('tel:') === 0) ev = 'click_tel';
      else if (href.indexOf('mailto:') === 0) ev = 'click_email';
      else if (/wa\.me|api\.whatsapp\.com/.test(href)) ev = 'click_whatsapp';
    }
    if (ev) trimite(ev, { link: href.slice(0, 120) });
  }, true);

  // ---------- Lead-uri: formularele PODREG trimit prin fetch către /wp-json/podreg/ ----------
  if (window.fetch) {
    var fetchOriginal = window.fetch;
    window.fetch = function (resursa, optiuni) {
      var url = typeof resursa === 'string' ? resursa : (resursa && resursa.url) || '';
      var metoda = ((optiuni && optiuni.method) || (resursa && resursa.method) || 'GET').toUpperCase();
      var p = fetchOriginal.apply(this, arguments);
      if (metoda === 'POST' && url.indexOf('/wp-json/podreg/') !== -1) {
        p.then(function (r) {
          if (!r.ok) return;
          var configurator = url.indexOf('estimare') !== -1;
          var date = { formular: configurator ? 'configurator' : 'contact' };
          if (configurator) {
            var mp = document.querySelector('input[name="sqm"]');
            if (mp) date.suprafata_mp = parseInt(mp.value, 10) || undefined;
            var st = document.querySelector('select[name="stage"]');
            if (st) date.stadiu = st.value;
          } else {
            var tema = document.getElementById('pcf_tema');
            if (tema) date.tema = tema.value;
          }
          trimite('generate_lead', date);
        }).catch(function () {});
      }
      return p;
    };
  }

  // ---------- Precompletare din ?model=… (venit de pe pagina unui proiect) ----------
  function precompleteaza() {
    var model = new URLSearchParams(location.search).get('model');
    if (!model) return;
    model = model.slice(0, 120);
    var text = 'Sunt interesat(ă) de o construcție similară cu „' + model + '”.';
    var tinta = null;

    var mesaj = document.getElementById('pcf_mesaj');
    if (mesaj && !mesaj.value) {
      mesaj.value = text + ' ';
      var tema = document.getElementById('pcf_tema');
      if (tema) {
        for (var i = 0; i < tema.options.length; i++) {
          if (/ofert/i.test(tema.options[i].value)) { tema.selectedIndex = i; break; }
        }
      }
      tinta = document.getElementById('podreg-contact-form');
    }
    var nota = document.querySelector('[id^="podreg-"] textarea[name="message"]');
    if (nota && !nota.value) {
      nota.value = text + ' ';
      tinta = nota.closest('[id^="podreg-"]');
    }
    if (tinta) {
      setTimeout(function () { tinta.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 300);
    }
  }

  // ---------- Bara sticky: ascunsă cât timp se scrie într-un formular ----------
  function baraSticky() {
    var bara = document.querySelector('.podreg-cta-bar');
    if (!bara) return;
    document.documentElement.classList.add('podreg-are-bara');
    document.addEventListener('focusin', function (e) {
      if (/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) bara.classList.add('podreg-ascuns');
    });
    document.addEventListener('focusout', function () { bara.classList.remove('podreg-ascuns'); });
  }

  // ---------- Banner cookie-uri (doar dacă GTM e activ) ----------
  function cookies() {
    var b = document.querySelector('.podreg-cookies');
    if (!b) return;
    var ales = null;
    try { ales = localStorage.getItem('podreg_consimtamant'); } catch (e) {}
    if (!ales) b.hidden = false;
    b.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-podreg-consimtamant]');
      if (!btn) return;
      var da = btn.getAttribute('data-podreg-consimtamant') === 'da';
      try { localStorage.setItem('podreg_consimtamant', da ? 'da' : 'nu'); } catch (e2) {}
      if (typeof window.gtag === 'function') {
        var v = da ? 'granted' : 'denied';
        window.gtag('consent', 'update', { ad_storage: v, ad_user_data: v, ad_personalization: v, analytics_storage: v });
      }
      trimite('consimtamant', { ales: da ? 'da' : 'nu' });
      b.hidden = true;
    });
  }

  function start() { precompleteaza(); baraSticky(); cookies(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
