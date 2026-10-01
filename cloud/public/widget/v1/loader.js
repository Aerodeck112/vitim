/*! VITIM AI widget 1.0 — chat cu asistentul AI al firmei. Se încarcă cu:
 *  <script src="https://ai.vitim.ro/widget/v1/loader.js" data-site="pk_..." async></script> */
(function () {
  'use strict';
  var script = document.currentScript || document.querySelector('script[data-site][src*="/widget/v1/loader.js"]');
  if (!script || window.__vitimWidget) return;
  window.__vitimWidget = true;
  var KEY = script.getAttribute('data-site') || '';
  var API = script.src.replace(/\/loader\.js.*$/, '');
  var STORE = 'vitim_chat_' + KEY;
  var state = { token: null, open: false, busy: false, cfg: null };
  try { state.token = localStorage.getItem(STORE); } catch (e) {}

  function call(path, data) {
    data = data || {};
    data.key = KEY;
    // text/plain = cerere „simplă” (fără preflight CORS); serverul citește JSON-ul din corp
    return fetch(API + '/' + path, { method: 'POST', headers: { 'Content-Type': 'text/plain;charset=UTF-8' }, body: JSON.stringify(data), credentials: 'omit' })
      .then(function (r) { return r.json().then(function (j) { j.__status = r.status; return j; }); });
  }

  function el(tag, attrs, text) {
    var n = document.createElement(tag);
    for (var k in attrs || {}) n.setAttribute(k, attrs[k]);
    if (text != null) n.textContent = text;
    return n;
  }

  /* text sigur: doar **îngroșat** și [linkuri](https://...) / (/relativ); restul e text simplu */
  function rich(node, text) {
    var re = /\*\*([^*]+)\*\*|\[([^\]]{1,200})\]\(((?:https:\/\/|\/(?!\/))[^\s)]{0,500})\)/g, last = 0, m;
    while ((m = re.exec(text))) {
      node.appendChild(document.createTextNode(text.slice(last, m.index)));
      if (m[1]) node.appendChild(el('strong', {}, m[1]));
      else { var a = el('a', { href: m[3], target: '_blank', rel: 'nofollow noopener noreferrer' }, m[2]); node.appendChild(a); }
      last = re.lastIndex;
    }
    node.appendChild(document.createTextNode(text.slice(last)));
  }

  function build(cfg) {
    var host = el('div', { id: 'vitim-ai-widget' });
    host.style.cssText = 'position:fixed;z-index:2147483000;bottom:0;' + (cfg.position === 'left' ? 'left:0' : 'right:0');
    var root = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host;
    var c = /^#[0-9a-f]{6}$/i.test(cfg.color) ? cfg.color : '#2f6bff';
    var side = cfg.position === 'left' ? 'left' : 'right';
    var css = el('style');
    css.textContent =
      ':host{all:initial}*{box-sizing:border-box;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}' +
      '.btn{position:fixed;bottom:20px;' + side + ':20px;display:flex;align-items:center;gap:8px;border:0;border-radius:999px;padding:12px 18px;background:' + c + ';color:#fff;font:600 15px/1 system-ui,sans-serif;cursor:pointer;box-shadow:0 8px 24px rgba(0,0,0,.18)}' +
      '.btn:focus-visible,.send:focus-visible,.x:focus-visible{outline:3px solid #000;outline-offset:2px}' +
      '.panel{position:fixed;bottom:84px;' + side + ':20px;width:370px;max-width:calc(100vw - 24px);height:560px;max-height:calc(100vh - 110px);background:#fff;color:#111;border-radius:16px;box-shadow:0 16px 48px rgba(0,0,0,.22);display:none;flex-direction:column;overflow:hidden}' +
      '.panel.on{display:flex}.head{background:' + c + ';color:#fff;padding:14px 16px;display:flex;justify-content:space-between;align-items:center;gap:8px}' +
      '.head b{font-size:15px}.x{background:transparent;border:0;color:#fff;font-size:22px;line-height:1;cursor:pointer;padding:4px 8px;border-radius:8px}' +
      '.notice{font-size:12px;color:#555;background:#f6f7f9;padding:8px 14px;border-bottom:1px solid #eee}.notice a{color:#333}' +
      '.list{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:8px;background:#fff}' +
      '.m{max-width:85%;padding:9px 12px;border-radius:14px;font-size:14.5px;line-height:1.45;white-space:pre-wrap;word-wrap:break-word}' +
      '.a{background:#f1f3f6;align-self:flex-start;border-bottom-left-radius:4px}.v{background:' + c + ';color:#fff;align-self:flex-end;border-bottom-right-radius:4px}' +
      '.a a{color:' + c + '}.typing{align-self:flex-start;color:#777;font-size:13px;padding:4px 2px}' +
      'form{display:flex;gap:8px;padding:10px;border-top:1px solid #eee;background:#fff}' +
      'textarea{flex:1;resize:none;border:1px solid #d6d9e0;border-radius:10px;padding:9px 10px;font-size:14.5px;height:42px;max-height:110px;color:#111;background:#fff}' +
      'textarea:focus{outline:0;border-color:' + c + '}.send{border:0;border-radius:10px;background:' + c + ';color:#fff;padding:0 14px;font-weight:600;cursor:pointer}.send:disabled{opacity:.5;cursor:default}' +
      '@media (max-width:480px){.panel{bottom:0;' + side + ':0;width:100vw;max-width:100vw;height:100%;max-height:100%;border-radius:0}}';
    root.appendChild(css);

    var btn = el('button', { class: 'btn', type: 'button', 'aria-expanded': 'false', 'aria-controls': 'vitim-panel' }, cfg.launcher || 'Întreabă-ne');
    var panel = el('div', { class: 'panel', id: 'vitim-panel', role: 'dialog', 'aria-label': cfg.title || 'Chat' });
    var head = el('div', { class: 'head' });
    head.appendChild(el('b', {}, cfg.title || 'Chat'));
    var close = el('button', { class: 'x', type: 'button', 'aria-label': 'Închide' }, '×');
    head.appendChild(close);
    var notice = el('div', { class: 'notice' }, cfg.notice || '');
    if (cfg.privacy_url) { notice.appendChild(document.createTextNode(' ')); notice.appendChild(el('a', { href: cfg.privacy_url, target: '_blank', rel: 'noopener' }, 'Confidențialitate')); }
    var list = el('div', { class: 'list', 'aria-live': 'polite' });
    var form = el('form');
    var input = el('textarea', { 'aria-label': 'Mesajul tău', placeholder: 'Scrie un mesaj…', maxlength: '1000', rows: '1' });
    var send = el('button', { class: 'send', type: 'submit' }, 'Trimite');
    form.appendChild(input); form.appendChild(send);
    panel.appendChild(head); panel.appendChild(notice); panel.appendChild(list); panel.appendChild(form);
    root.appendChild(btn); root.appendChild(panel);
    document.body.appendChild(host);

    function add(role, text) {
      var m = el('div', { class: 'm ' + (role === 'visitor' ? 'v' : 'a') });
      role === 'visitor' ? (m.textContent = text) : rich(m, text);
      list.appendChild(m);
      list.scrollTop = list.scrollHeight;
    }
    var typing = el('div', { class: 'typing' }, 'scrie…');

    function toggle(open) {
      state.open = open;
      panel.classList.toggle('on', open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        if (!list.childNodes.length) {
          if (state.token) {
            call('history', { token: state.token }).then(function (j) {
              if (j.messages && j.messages.length) { add('agent', cfg.greeting); j.messages.forEach(function (m) { add(m.role, m.text); }); }
              else { state.token = null; add('agent', cfg.greeting); }
            }).catch(function () { add('agent', cfg.greeting); });
          } else add('agent', cfg.greeting);
        }
        setTimeout(function () { input.focus(); }, 50);
      } else btn.focus();
    }
    btn.addEventListener('click', function () { toggle(!state.open); });
    close.addEventListener('click', function () { toggle(false); });
    panel.addEventListener('keydown', function (e) { if (e.key === 'Escape') toggle(false); });
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit')); } });

    function ensureToken() {
      if (state.token) return Promise.resolve(state.token);
      return call('start', { page: location.href.slice(0, 250) }).then(function (j) {
        if (!j.token) throw new Error(j.error || 'start');
        state.token = j.token;
        try { localStorage.setItem(STORE, j.token); } catch (e) {}
        return j.token;
      });
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var text = input.value.trim();
      if (!text || state.busy) return;
      state.busy = true; send.disabled = true; input.value = '';
      add('visitor', text);
      list.appendChild(typing); list.scrollTop = list.scrollHeight;
      ensureToken().then(function (token) {
        return call('message', { token: token, message: text, page: location.href.slice(0, 250) });
      }).then(function (j) {
        if (j.error === 'unknown_conversation') { state.token = null; try { localStorage.removeItem(STORE); } catch (e) {} }
        add('agent', j.reply || 'Momentan nu pot răspunde. Te rugăm să ne contactezi direct.');
      }).catch(function () {
        add('agent', 'Conexiunea a eșuat. Încearcă din nou.');
      }).then(function () {
        if (typing.parentNode) typing.parentNode.removeChild(typing);
        state.busy = false; send.disabled = false; input.focus();
      });
    });
  }

  function init() {
    call('config').then(function (cfg) { if (cfg && cfg.enabled) build(cfg); }).catch(function () {});
  }
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
})();
