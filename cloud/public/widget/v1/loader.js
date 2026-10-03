/*! VITIM chat 2.0 — asistentul AI și echipa firmei, într-o singură fereastră de chat. Se încarcă cu:
 *  <script src="https://ai.vitim.ro/widget/v1/loader.js" data-site="pk_..." async></script> */
(function () {
  'use strict';
  var script = document.currentScript || document.querySelector('script[data-site][src*="/widget/v1/loader.js"]');
  if (!script || window.__vitimWidget) return;
  window.__vitimWidget = true;
  var KEY = script.getAttribute('data-site') || '';
  var API = script.src.replace(/\/loader\.js.*$/, '');
  var STORE = 'vitim_chat_' + KEY, SEEN = 'vitim_seen_' + KEY, MUTE = 'vitim_mute_' + KEY, PRO = 'vitim_pro_' + KEY;
  var EMOJI = ['😀', '😂', '😊', '😍', '🙂', '😉', '🤔', '😮', '😢', '😎', '👍', '👏', '🙏', '💪', '👋', '🎉', '❤️', '🔥', '✅', '⭐', '🚀', '📞', '📧', '🏠'];
  var ICON = {
    chat: '<svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><path fill="currentColor" d="M12 3C6.5 3 2 6.6 2 11c0 2.4 1.3 4.6 3.4 6.1L4.6 21l4.3-2.3c1 .2 2 .3 3.1.3 5.5 0 10-3.6 10-8s-4.5-8-10-8zm-4 9.3a1.3 1.3 0 110-2.6 1.3 1.3 0 010 2.6zm4 0a1.3 1.3 0 110-2.6 1.3 1.3 0 010 2.6zm4 0a1.3 1.3 0 110-2.6 1.3 1.3 0 010 2.6z"/></svg>',
    down: '<svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>',
    send: '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M3.4 20.4l17.5-7.5a1 1 0 000-1.8L3.4 3.6a.9.9 0 00-1.3 1l2 6.4 9.4 1-9.4 1-2 6.4a.9.9 0 001.3 1z"/></svg>',
    smile: '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><circle cx="12" cy="12" r="9.2" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="9" cy="10" r="1.2" fill="currentColor"/><circle cx="15" cy="10" r="1.2" fill="currentColor"/><path d="M8 14.2c1 1.6 2.4 2.4 4 2.4s3-.8 4-2.4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
    dots: '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><circle cx="5" cy="12" r="2" fill="currentColor"/><circle cx="12" cy="12" r="2" fill="currentColor"/><circle cx="19" cy="12" r="2" fill="currentColor"/></svg>',
    x: '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path stroke="currentColor" stroke-width="2.4" stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>'
  };
  var state = { token: null, open: false, busy: false, cfg: null, lastId: 0, seenId: 0, ids: {}, live: false, operator: null, hasContact: false,
    unread: 0, sent: 0, pending: [], lastAv: null, timer: null, polling: false, activeAt: 0, started: false, muted: false, captureShown: false, lastRole: null, lastTime: null };
  function get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function put(k, v) { try { v === null ? localStorage.removeItem(k) : localStorage.setItem(k, v); } catch (e) {} }
  state.token = get(STORE);
  state.seenId = +(get(SEEN) || 0);
  state.muted = get(MUTE) === '1';

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
  function svg(node, name) { node.innerHTML = ICON[name]; return node; } // doar iconițele fixe de mai sus

  /* text sigur: doar **îngroșat** și [linkuri](https://...) / (/relativ); restul e text simplu */
  function rich(node, text) {
    var re = /\*\*([^*]+)\*\*|\[([^\]]{1,200})\]\(((?:https:\/\/|\/(?!\/))[^\s)]{0,500})\)/g, last = 0, m;
    while ((m = re.exec(text))) {
      node.appendChild(document.createTextNode(text.slice(last, m.index)));
      if (m[1]) node.appendChild(el('strong', {}, m[1]));
      else node.appendChild(el('a', { href: m[3], target: '_blank', rel: 'nofollow noopener noreferrer' }, m[2]));
      last = re.lastIndex;
    }
    node.appendChild(document.createTextNode(text.slice(last)));
  }

  function hhmm(iso) {
    var d = iso ? new Date(iso) : new Date();
    return isNaN(d) ? '' : ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
  }

  function ding() {
    if (state.muted || !state.cfg.sound) return;
    try {
      var a = new (window.AudioContext || window.webkitAudioContext)(), t = a.currentTime;
      [[660, 0], [880, 0.12]].forEach(function (n) {
        var o = a.createOscillator(), g = a.createGain();
        o.type = 'sine'; o.frequency.value = n[0];
        g.gain.setValueAtTime(0.0001, t + n[1]); g.gain.exponentialRampToValueAtTime(0.12, t + n[1] + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, t + n[1] + 0.3);
        o.connect(g); g.connect(a.destination); o.start(t + n[1]); o.stop(t + n[1] + 0.32);
      });
    } catch (e) {}
  }

  function build(cfg) {
    state.cfg = cfg;
    var c = /^#[0-9a-f]{6}$/i.test(cfg.color) ? cfg.color : '#2f6bff';
    var side = cfg.position === 'left' ? 'left' : 'right';
    var host = el('div', { id: 'vitim-ai-widget' });
    host.style.cssText = 'position:fixed;z-index:2147483000;bottom:0;' + side + ':0;width:0;height:0';
    var root = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host;
    var css = el('style');
    css.textContent =
      ':host{all:initial}*{box-sizing:border-box;font-family:"Mulish",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;-webkit-tap-highlight-color:transparent}' +
      'button{font:inherit}button:focus-visible,textarea:focus-visible,input:focus-visible,a:focus-visible{outline:3px solid ' + c + ';outline-offset:2px}' +
      // butonul rotund
      '.launch{position:fixed;bottom:20px;' + side + ':20px;width:62px;height:62px;border-radius:50%;border:0;background:' + c + ';color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 22px rgba(0,0,0,.24);transition:transform .2s}' +
      '.launch:hover{transform:scale(1.07)}.launch .i{display:flex;transition:transform .25s,opacity .2s}.launch .i2{position:absolute;opacity:0;transform:rotate(-90deg)}' +
      '.launch.on .i1{opacity:0;transform:rotate(90deg)}.launch.on .i2{opacity:1;transform:none}' +
      '.badge{position:absolute;top:-3px;right:-3px;min-width:22px;height:22px;padding:0 6px;border-radius:11px;background:#ff3b5c;color:#fff;font:700 12px/22px system-ui,sans-serif;text-align:center;border:2px solid #fff;display:none}.badge.on{display:block}' +
      // mesajul de lângă buton (proactiv / previzualizare)
      '.pop{position:fixed;bottom:94px;' + side + ':20px;max-width:300px;background:#fff;color:#111;border-radius:16px;padding:14px 34px 14px 14px;box-shadow:0 10px 34px rgba(0,0,0,.18);display:none;gap:10px;align-items:flex-start;cursor:pointer;animation:up .35s ease}' +
      '.pop.on{display:flex}.pop p{margin:0;font-size:14.5px;line-height:1.45;color:#111}.pop .pn{display:block;font-size:12px;color:#6b7280;margin-bottom:2px}.pop .av{width:34px;height:34px;font-size:14px;border:0;background:' + c + '1f;color:' + c + '}.pop .av.op{background:#10b981;color:#fff}' +
      '.pop .px{position:absolute;top:6px;right:6px;width:24px;height:24px;border:0;border-radius:50%;background:#f1f3f6;color:#555;cursor:pointer;display:flex;align-items:center;justify-content:center}' +
      // fereastra
      '.panel{position:fixed;bottom:96px;' + side + ':20px;width:380px;max-width:calc(100vw - 24px);height:640px;max-height:calc(100vh - 116px);background:#fff;color:#111;border-radius:18px;box-shadow:0 18px 60px rgba(0,0,0,.25);display:none;flex-direction:column;overflow:hidden;transform-origin:bottom ' + side + '}' +
      '.panel.on{display:flex;animation:pop .22s ease}@keyframes pop{from{opacity:0;transform:scale(.92) translateY(10px)}to{opacity:1;transform:none}}@keyframes up{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}' +
      '.hd{background:linear-gradient(135deg,' + c + ' 0%,' + c + 'cc 100%);color:#fff;padding:16px 16px 18px;position:relative;flex-shrink:0;transition:padding .2s}' +
      '.bar{display:flex;align-items:center;gap:10px}.bar .tt{flex:1;min-width:0}.bar b{display:block;font-size:16px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
      '.st{display:flex;align-items:center;gap:6px;font-size:12.5px;opacity:.92;margin-top:2px}.st i{width:8px;height:8px;border-radius:50%;background:#34d399;box-shadow:0 0 0 2px rgba(255,255,255,.35)}.st i.off{background:#cbd5e1}' +
      '.hb{width:36px;height:36px;border:0;border-radius:10px;background:transparent;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center}.hb:hover{background:rgba(255,255,255,.18)}' +
      '.welcome{margin-top:18px}.welcome h2{margin:0 0 6px;font-size:28px;line-height:1.15;font-weight:800;color:#fff}.welcome p{margin:0;font-size:15px;opacity:.95;line-height:1.4}' +
      '.hd.small .welcome{display:none}' +
      '.av{width:38px;height:38px;border-radius:50%;flex-shrink:0;background:#fff;color:' + c + ';display:flex;align-items:center;justify-content:center;font-weight:800;font-size:16px;overflow:hidden;border:2px solid rgba(255,255,255,.7)}.av img{width:100%;height:100%;object-fit:cover}' +
      '.avs{display:flex}.avs .av+.av{margin-left:-10px}.av.op{background:#10b981;color:#fff}' +
      '.menu{position:absolute;top:56px;right:14px;background:#fff;color:#111;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);padding:6px;display:none;z-index:3;min-width:210px}.menu.on{display:block}' +
      '.menu button{display:block;width:100%;text-align:left;border:0;background:none;padding:10px 12px;border-radius:8px;font-size:14px;cursor:pointer;color:#111}.menu button:hover{background:#f1f3f6}' +
      // conversația
      '.list{flex:1;overflow-y:auto;padding:16px 14px 8px;display:flex;flex-direction:column;gap:3px;background:#fff;scroll-behavior:smooth}' +
      '.note{font-size:11.5px;color:#6b7280;text-align:center;margin:0 10px 12px;line-height:1.45}.note a{color:#374151}' +
      '.row{display:flex;align-items:flex-end;gap:8px;max-width:100%}.row.v{justify-content:flex-end}.row .av{width:28px;height:28px;font-size:12px;border:0;background:' + c + '1f;color:' + c + '}.row .av.op{background:#10b981;color:#fff}.row .av.ghost{visibility:hidden}' +
      '.m{max-width:78%;padding:10px 14px;border-radius:18px;font-size:14.5px;line-height:1.45;white-space:pre-wrap;word-wrap:break-word;animation:up .2s ease}' +
      '.a .m{background:#f0f2f7;color:#111;border-bottom-left-radius:6px}.v .m{background:' + c + ';color:#fff;border-bottom-right-radius:6px}.m a{color:inherit;text-decoration:underline}' +
      '.who{font-size:11.5px;color:#6b7280;margin:8px 0 2px 36px}.time{font-size:11px;color:#9ca3af;margin:2px 36px 8px}.v+.time,.time.r{text-align:right;margin-right:2px}' +
      '.sys{align-self:center;font-size:12px;color:#6b7280;background:#f6f7f9;border-radius:10px;padding:6px 10px;margin:8px 0;text-align:center;max-width:90%}' +
      '.qr{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:6px;margin:8px 0 4px}.qr button{border:1.5px solid ' + c + ';background:#fff;color:' + c + ';border-radius:18px;padding:8px 14px;font-size:14px;font-weight:600;cursor:pointer}.qr button:hover{background:' + c + ';color:#fff}' +
      '.typing .m{display:flex;gap:4px;align-items:center;padding:14px 16px}.typing span{width:7px;height:7px;border-radius:50%;background:#9ca3af;animation:b 1.2s infinite}.typing span:nth-child(2){animation-delay:.15s}.typing span:nth-child(3){animation-delay:.3s}' +
      '@keyframes b{0%,60%,100%{transform:none;opacity:.5}30%{transform:translateY(-5px);opacity:1}}' +
      '.card{border:1px solid #e5e7eb;border-radius:14px;padding:14px;margin:8px 0 8px 36px;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.05)}.card p{margin:0 0 10px;font-size:14px;line-height:1.4}' +
      '.card input[type=text],.card input[type=email]{width:100%;border:1px solid #d6d9e0;border-radius:10px;padding:10px;font-size:14px;margin-bottom:8px;color:#111;background:#fff}' +
      '.card label{display:flex;gap:8px;font-size:12.5px;color:#4b5563;line-height:1.35;margin-bottom:10px}.card button{width:100%;border:0;border-radius:10px;background:' + c + ';color:#fff;padding:10px;font-weight:700;font-size:14px;cursor:pointer}.card .err{color:#dc2626;font-size:12.5px;margin:-4px 0 8px}' +
      // scrierea
      '.foot{border-top:1px solid #eef0f4;background:#fff;flex-shrink:0;position:relative}.inp{display:flex;align-items:flex-end;gap:4px;padding:10px 10px 6px 14px}' +
      'textarea{flex:1;resize:none;border:0;padding:9px 0;font-size:15px;line-height:1.4;height:40px;max-height:120px;color:#111;background:transparent}textarea:focus{outline:0}textarea::placeholder{color:#9ca3af}' +
      '.ib{width:38px;height:38px;border:0;border-radius:10px;background:transparent;color:#6b7280;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}.ib:hover{color:' + c + ';background:#f3f4f6}' +
      '.ib.send{color:' + c + '}.ib.send:disabled{color:#d1d5db;cursor:default;background:transparent}' +
      '.emo{position:absolute;bottom:58px;right:10px;background:#fff;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.18);padding:8px;display:none;grid-template-columns:repeat(6,1fr);gap:2px;z-index:2}.emo.on{display:grid}' +
      '.emo button{border:0;background:none;font-size:22px;width:40px;height:40px;border-radius:8px;cursor:pointer}.emo button:hover{background:#f1f3f6}' +
      '.by{text-align:center;font-size:11px;color:#9ca3af;padding:0 0 8px}.by a{color:#6b7280;text-decoration:none;font-weight:700}' +
      '@media (max-width:480px){.panel{bottom:0;' + side + ':0;width:100vw;max-width:100vw;height:100%;max-height:100%;border-radius:0}.panel.on~.launch{display:none}.pop{max-width:calc(100vw - 110px)}}';
    root.appendChild(css);

    function avatar(cls, letter) {
      var a = el('div', { class: 'av' + (cls ? ' ' + cls : '') });
      if (!cls && cfg.avatar_url) a.appendChild(el('img', { src: cfg.avatar_url, alt: '' }));
      else a.textContent = (letter || cfg.title || 'A').trim().charAt(0).toUpperCase();
      return a;
    }

    /* ---- elementele ---- */
    var launch = el('button', { class: 'launch', type: 'button', 'aria-label': cfg.launcher || 'Deschide chatul', 'aria-expanded': 'false', title: cfg.launcher || '' });
    var i1 = svg(el('span', { class: 'i i1' }), 'chat'), i2 = svg(el('span', { class: 'i i2' }), 'down');
    var badge = el('span', { class: 'badge', 'aria-hidden': 'true' });
    launch.appendChild(i1); launch.appendChild(i2); launch.appendChild(badge);

    var pop = el('div', { class: 'pop', role: 'status' });
    var popX = svg(el('button', { class: 'px', type: 'button', 'aria-label': 'Închide mesajul' }), 'x');
    var popBody = el('div');
    var popAv = el('div');
    pop.appendChild(popAv); pop.appendChild(popBody); pop.appendChild(popX);

    var panel = el('div', { class: 'panel', role: 'dialog', 'aria-label': cfg.title || 'Chat' });
    var hd = el('div', { class: 'hd' });
    var bar = el('div', { class: 'bar' });
    var avs = el('div', { class: 'avs' });
    var tt = el('div', { class: 'tt' });
    var title = el('b', {}, cfg.title || 'Chat');
    var st = el('div', { class: 'st' });
    var dot = el('i', cfg.online ? {} : { class: 'off' });
    var stText = el('span', {}, cfg.status_text || '');
    st.appendChild(dot); st.appendChild(stText);
    tt.appendChild(title); tt.appendChild(st);
    var menuBtn = svg(el('button', { class: 'hb', type: 'button', 'aria-label': 'Opțiuni', 'aria-haspopup': 'true' }), 'dots');
    var minBtn = svg(el('button', { class: 'hb', type: 'button', 'aria-label': 'Minimizează chatul' }), 'down');
    bar.appendChild(avs); bar.appendChild(tt); bar.appendChild(menuBtn); bar.appendChild(minBtn);
    var welcome = el('div', { class: 'welcome' });
    welcome.appendChild(el('h2', {}, cfg.welcome_title || 'Salut 👋'));
    welcome.appendChild(el('p', {}, cfg.welcome_text || ''));
    hd.appendChild(bar); hd.appendChild(welcome);
    var menu = el('div', { class: 'menu', role: 'menu' });
    var muteBtn = el('button', { type: 'button', role: 'menuitem' });
    var endBtn = el('button', { type: 'button', role: 'menuitem' }, 'Începe o conversație nouă');
    if (cfg.sound) menu.appendChild(muteBtn);
    menu.appendChild(endBtn);
    if (cfg.privacy_url) { var pv = el('button', { type: 'button', role: 'menuitem' }, 'Politica de confidențialitate'); pv.addEventListener('click', function () { window.open(cfg.privacy_url, '_blank', 'noopener'); }); menu.appendChild(pv); }
    hd.appendChild(menu);

    var list = el('div', { class: 'list', 'aria-live': 'polite' });
    var foot = el('div', { class: 'foot' });
    var form = el('form', { class: 'inp' });
    var input = el('textarea', { 'aria-label': 'Mesajul tău', placeholder: 'Scrie un mesaj…', maxlength: '1000', rows: '1' });
    var emoBtn = svg(el('button', { class: 'ib', type: 'button', 'aria-label': 'Emoji' }), 'smile');
    var send = svg(el('button', { class: 'ib send', type: 'submit', 'aria-label': 'Trimite', disabled: '' }), 'send');
    form.appendChild(input); form.appendChild(emoBtn); form.appendChild(send);
    var emo = el('div', { class: 'emo', role: 'listbox', 'aria-label': 'Emoji' });
    EMOJI.forEach(function (e) {
      var b = el('button', { type: 'button', role: 'option' }, e);
      b.addEventListener('click', function () { insert(e); emo.classList.remove('on'); });
      emo.appendChild(b);
    });
    var by = el('div', { class: 'by' });
    by.appendChild(document.createTextNode('Oferit de '));
    by.appendChild(el('a', { href: 'https://vitim.ro/?utm_source=chat&utm_medium=widget', target: '_blank', rel: 'noopener' }, 'VITIM'));
    foot.appendChild(emo); foot.appendChild(form); foot.appendChild(by);
    panel.appendChild(hd); panel.appendChild(list); panel.appendChild(foot);
    root.appendChild(pop); root.appendChild(panel); root.appendChild(launch);
    document.body.appendChild(host);

    var typing = el('div', { class: 'row a typing' });
    typing.appendChild(avatar()); var td = el('div', { class: 'm', 'aria-label': 'scrie…' });
    td.appendChild(el('span')); td.appendChild(el('span')); td.appendChild(el('span')); typing.appendChild(td);

    function header() {
      avs.textContent = '';
      avs.appendChild(avatar());
      if (state.live && state.operator) avs.appendChild(avatar('op', state.operator));
      title.textContent = state.live && state.operator ? state.operator : (cfg.title || 'Chat');
      stText.textContent = state.live ? 'Din echipa ' + (cfg.title || '') + ' · online' : (cfg.status_text || '');
      if (state.live) { var card = list.querySelector('.card'); if (card) card.parentNode.removeChild(card); }
      dot.className = state.live || cfg.online ? '' : 'off';
      hd.classList.toggle('small', state.started);
      muteBtn.textContent = state.muted ? 'Pornește sunetul' : 'Oprește sunetul';
    }

    /* ---- mesajele ---- */
    function scroll() { list.scrollTop = list.scrollHeight; }
    function hideTyping() { if (typing.parentNode) typing.parentNode.removeChild(typing); }
    function showTyping() {
      if (!typing.parentNode) {
        typing.replaceChild(state.live && state.operator ? avatar('op', state.operator) : avatar(), typing.firstChild);
        list.appendChild(typing);
      }
      scroll();
    }

    function add(role, text, opt) {
      opt = opt || {};
      if (opt.id) { if (state.ids[opt.id]) return false; state.ids[opt.id] = 1; }
      hideTyping();
      if (role === 'system') { list.appendChild(el('div', { class: 'sys' }, text)); state.lastRole = null; state.lastTime = null; return true; }
      var who = role === 'operator' ? 'op:' + (opt.name || '') : role;
      var same = state.lastRole === who;
      if (same && state.lastTime) state.lastTime.parentNode && state.lastTime.parentNode.removeChild(state.lastTime);
      if (!same && role === 'operator') list.appendChild(el('div', { class: 'who' }, opt.name || 'Echipa'));
      var row = el('div', { class: 'row ' + (role === 'visitor' ? 'v' : 'a') });
      var m = el('div', { class: 'm' });
      role === 'visitor' ? (m.textContent = text) : rich(m, text);
      if (role !== 'visitor') {
        var av = role === 'operator' ? avatar('op', opt.name) : avatar();
        // avatarul apare doar la ultimul mesaj dintr-un șir; cele de deasupra îl păstrează ascuns
        if (same && state.lastAv) state.lastAv.classList.add('ghost');
        state.lastAv = av;
        row.appendChild(av);
      }
      row.appendChild(m);
      list.appendChild(row);
      state.lastTime = el('div', { class: 'time' + (role === 'visitor' ? ' r' : '') }, hhmm(opt.at));
      list.appendChild(state.lastTime);
      state.lastRole = who;
      scroll();
      return true;
    }

    function quickReplies() {
      if (!cfg.quick_replies || !cfg.quick_replies.length || state.started) return;
      var box = el('div', { class: 'qr' });
      cfg.quick_replies.forEach(function (q) {
        var b = el('button', { type: 'button' }, q);
        b.addEventListener('click', function () { box.parentNode && box.parentNode.removeChild(box); submit(q); });
        box.appendChild(b);
      });
      list.appendChild(box); scroll();
    }

    function emailCard() {
      if (state.captureShown || state.hasContact || !cfg.email_capture || cfg.online || state.live) return;
      state.captureShown = true;
      var card = el('form', { class: 'card' });
      card.appendChild(el('p', {}, 'Echipa nu e online acum. Lasă-ne emailul și îți răspundem acolo, cât de repede putem.'));
      var name = el('input', { type: 'text', placeholder: 'Numele tău', maxlength: '80', 'aria-label': 'Numele tău', autocomplete: 'name' });
      var mail = el('input', { type: 'email', placeholder: 'Adresa de email', maxlength: '190', required: '', 'aria-label': 'Adresa de email', autocomplete: 'email' });
      var lab = el('label'), ok = el('input', { type: 'checkbox', required: '' });
      lab.appendChild(ok); lab.appendChild(document.createTextNode('Sunt de acord să fiu contactat pe email în legătură cu întrebarea mea.'));
      var err = el('div', { class: 'err' });
      var btn = el('button', { type: 'submit' }, 'Trimite emailul');
      [name, mail, lab, err, btn].forEach(function (n) { card.appendChild(n); });
      card.addEventListener('submit', function (e) {
        e.preventDefault();
        err.textContent = '';
        if (!ok.checked) { err.textContent = 'Bifează acordul ca să te putem contacta.'; return; }
        btn.disabled = true;
        call('contact', { token: state.token, name: name.value.trim(), email: mail.value.trim(), consent: true }).then(function (j) {
          if (j.ok) { state.hasContact = true; card.parentNode.removeChild(card); add('system', 'Mulțumim! Îți răspundem la ' + mail.value.trim() + '.'); }
          else { err.textContent = j.error === 'invalid_email' ? 'Adresa de email nu pare corectă.' : 'Nu am putut salva. Încearcă din nou.'; btn.disabled = false; }
        }).catch(function () { err.textContent = 'Conexiunea a eșuat. Încearcă din nou.'; btn.disabled = false; });
      });
      list.appendChild(card); scroll();
    }

    function render(messages) {
      var fresh = 0;
      (messages || []).forEach(function (m) {
        var p = m.role === 'visitor' ? state.pending.indexOf(m.text) : -1;
        if (p > -1) { state.pending.splice(p, 1); state.ids[m.id] = 1; if (m.id > state.lastId) state.lastId = m.id; return; } // deja afișat la trimitere
        if (add(m.role, m.text, { id: m.id, name: m.name, at: m.at }) && m.role !== 'visitor') fresh++;
        if (m.id > state.lastId) state.lastId = m.id;
      });
      return fresh;
    }

    function markSeen() {
      state.unread = 0; badge.classList.remove('on');
      if (state.lastId > state.seenId) { state.seenId = state.lastId; put(SEEN, String(state.seenId)); }
    }

    function unreadBump(n, preview, who) {
      state.unread += n;
      badge.textContent = state.unread > 9 ? '9+' : String(state.unread);
      badge.classList.add('on');
      showPop(preview, who);
      ding();
    }

    /* ---- verificarea periodică (mesaje de la echipă) ---- */
    function schedule() {
      clearTimeout(state.timer);
      if (!state.token) return;
      var idle = Date.now() - state.activeAt;
      var delay = state.open ? (document.hidden ? 15000 : 4000) : ((state.live || idle < 600000) ? 15000 : 0);
      if (!delay || idle > 1800000) return;
      state.timer = setTimeout(poll, delay);
    }
    function poll() {
      if (!state.token || state.polling || state.busy) return schedule();
      state.polling = true;
      call('history', { token: state.token, after: state.lastId }).then(function (j) {
        if (j.error === 'unknown_conversation') return reset();
        if (!j.messages) return;
        var wasLive = state.live;
        state.live = !!j.live; state.operator = j.operator || null; state.hasContact = !!j.has_contact;
        var fresh = render(j.messages);
        if (fresh) {
          state.activeAt = Date.now();
          var lastMsg = j.messages[j.messages.length - 1];
          if (state.open && !document.hidden) { markSeen(); ding(); } else unreadBump(fresh, lastMsg.text, lastMsg.name);
        }
        if (j.typing && state.open) showTyping(); else if (!state.busy) hideTyping();
        if (wasLive !== state.live || fresh) header();
      }).catch(function () {}).then(function () { state.polling = false; schedule(); });
    }

    function reset() {
      state.token = null; state.lastId = 0; state.ids = {}; state.live = false; state.operator = null; state.started = false;
      state.hasContact = false; state.captureShown = false; state.lastRole = null; state.lastTime = null; state.sent = 0; state.pending = [];
      put(STORE, null);
      list.textContent = '';
      intro();
      header();
    }

    function intro() {
      var n = el('div', { class: 'note' }, cfg.notice || '');
      if (cfg.privacy_url) { n.appendChild(document.createTextNode(' ')); n.appendChild(el('a', { href: cfg.privacy_url, target: '_blank', rel: 'noopener' }, 'Confidențialitate')); }
      list.appendChild(n);
      add('agent', cfg.greeting || 'Bună! Cu ce te putem ajuta?');
      quickReplies();
    }

    /* ---- deschidere / închidere ---- */
    function showPop(text, who) {
      if (state.open || !text) return;
      popBody.textContent = ''; popAv.textContent = '';
      popAv.appendChild(state.live && state.operator ? avatar('op', state.operator) : avatar());
      if (who) popBody.appendChild(el('span', { class: 'pn' }, who));
      var p = el('p'); p.textContent = text.length > 140 ? text.slice(0, 137) + '…' : text;
      popBody.appendChild(p);
      pop.classList.add('on');
    }
    function toggle(open) {
      state.open = open;
      panel.classList.toggle('on', open);
      launch.classList.toggle('on', open);
      launch.setAttribute('aria-expanded', open ? 'true' : 'false');
      launch.setAttribute('aria-label', open ? 'Închide chatul' : (cfg.launcher || 'Deschide chatul'));
      if (open) {
        pop.classList.remove('on');
        try { sessionStorage.setItem(PRO, '1'); } catch (e) {}
        markSeen(); scroll();
        if (state.token) { state.activeAt = Date.now(); poll(); }
        if (window.innerWidth > 480) setTimeout(function () { input.focus(); }, 60);
      } else { menu.classList.remove('on'); emo.classList.remove('on'); launch.focus(); schedule(); }
    }
    launch.addEventListener('click', function () { toggle(!state.open); });
    minBtn.addEventListener('click', function () { toggle(false); });
    pop.addEventListener('click', function () { toggle(true); });
    popX.addEventListener('click', function (e) { e.stopPropagation(); pop.classList.remove('on'); try { sessionStorage.setItem(PRO, '1'); } catch (x) {} });
    panel.addEventListener('keydown', function (e) { if (e.key === 'Escape') { menu.classList.contains('on') || emo.classList.contains('on') ? (menu.classList.remove('on'), emo.classList.remove('on')) : toggle(false); } });
    menuBtn.addEventListener('click', function (e) { e.stopPropagation(); menu.classList.toggle('on'); });
    panel.addEventListener('click', function (e) { if (!menu.contains(e.target) && e.target !== menuBtn) menu.classList.remove('on'); });
    muteBtn.addEventListener('click', function () { state.muted = !state.muted; put(MUTE, state.muted ? '1' : null); header(); menu.classList.remove('on'); });
    endBtn.addEventListener('click', function () { menu.classList.remove('on'); reset(); input.focus(); });
    emoBtn.addEventListener('click', function () { emo.classList.toggle('on'); });

    function insert(t) {
      var s = input.selectionStart || input.value.length;
      input.value = input.value.slice(0, s) + t + input.value.slice(input.selectionEnd || s);
      input.focus(); input.selectionStart = input.selectionEnd = s + t.length; grow();
    }
    function grow() {
      input.style.height = '40px'; input.style.height = Math.min(input.scrollHeight, 120) + 'px';
      if (input.value.trim()) send.removeAttribute('disabled'); else send.setAttribute('disabled', '');
    }
    input.addEventListener('input', grow);
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submit(input.value); } });
    form.addEventListener('submit', function (e) { e.preventDefault(); submit(input.value); });

    function ensureToken() {
      if (state.token) return Promise.resolve(state.token);
      return call('start', { page: location.href.slice(0, 250) }).then(function (j) {
        if (!j.token) throw new Error(j.error || 'start');
        state.token = j.token;
        put(STORE, j.token);
        return j.token;
      });
    }

    function submit(raw) {
      var text = String(raw || '').trim().slice(0, 1000);
      if (!text || state.busy) return;
      state.busy = true; input.value = ''; grow(); emo.classList.remove('on');
      var q = list.querySelector('.qr'); if (q) q.parentNode.removeChild(q);
      if (!state.started) { state.started = true; header(); }
      add('visitor', text);
      state.pending.push(text); state.sent++;
      state.activeAt = Date.now();
      var wait = state.live ? null : setTimeout(showTyping, 350);
      ensureToken().then(function (token) {
        return call('message', { token: token, message: text, after: state.lastId, page: location.href.slice(0, 250) });
      }).then(function (j) {
        if (j.error === 'unknown_conversation') { reset(); add('system', 'Conversația a expirat. Scrie din nou mesajul, te rog.'); return; }
        if (j.status === 'human') { state.live = true; header(); }
        var shown = j.messages && j.messages.length ? render(j.messages) : 0;
        if (!shown && j.reply) add('agent', j.reply);
        if (!shown && !j.reply && j.status !== 'human') add('agent', 'Momentan nu pot răspunde. Te rugăm să ne contactezi direct.');
        if (j.last_id && j.last_id > state.lastId) state.lastId = j.last_id;
        state.pending = [];
        if (j.has_contact) { state.hasContact = true; var card = list.querySelector('.card'); if (card) card.parentNode.removeChild(card); }
        markSeen();
        if (state.sent >= 2 || j.status === 'human') emailCard();
      }).catch(function () {
        add('system', 'Conexiunea a eșuat. Verifică internetul și încearcă din nou.');
      }).then(function () {
        clearTimeout(wait); hideTyping();
        state.busy = false; schedule();
        if (window.innerWidth > 480) input.focus();
      });
    }

    /* ---- pornire ---- */
    header();
    intro();
    if (state.token) {
      call('history', { token: state.token }).then(function (j) {
        if (!j.messages || !j.messages.length) { if (j.error) { state.token = null; put(STORE, null); } return; }
        state.live = !!j.live; state.operator = j.operator || null; state.hasContact = !!j.has_contact;
        var q = list.querySelector('.qr'); if (q) q.parentNode.removeChild(q);
        state.started = true;
        render(j.messages);
        state.activeAt = j.messages[j.messages.length - 1].at ? Date.parse(j.messages[j.messages.length - 1].at) || 0 : 0;
        var news = j.messages.filter(function (m) { return m.id > state.seenId && m.role !== 'visitor'; });
        if (state.seenId && news.length) unreadBump(news.length, news[news.length - 1].text, news[news.length - 1].name);
        else if (!state.seenId) markSeen();
        header(); schedule();
      }).catch(function () {});
    }
    var seenPro = false;
    try { seenPro = sessionStorage.getItem(PRO) === '1'; } catch (e) {}
    if (cfg.proactive_delay > 0 && cfg.proactive_text && !state.token && !seenPro) {
      setTimeout(function () {
        if (state.open || state.token) return;
        showPop(cfg.proactive_text, cfg.title);
        state.unread = 1; badge.textContent = '1'; badge.classList.add('on');
        ding();
        try { sessionStorage.setItem(PRO, '1'); } catch (e) {}
      }, cfg.proactive_delay * 1000);
    }
    document.addEventListener('visibilitychange', function () { if (!document.hidden && state.open) { markSeen(); poll(); } });
  }

  // identificarea în magazin: tokenul din linkurile emailurilor (?vtm=) sau de la abonare ajunge într-un cookie al site-ului,
  // pe care pluginul VITIM Connector îl citește la evenimentele WooCommerce (produs văzut, coș, comandă).
  // Cu bannerul VITIM de cookie-uri activ, cookie-ul se pune doar după acordul pentru marketing.
  var consent = { managed: false, marketing: false, decided: false };
  function valid(token) { return /^\d+\.\d+\.[a-f0-9]{16}$/.test(token || ''); }
  function setCt(token) { document.cookie = 'vitim_ct=' + token + '; path=/; max-age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : ''); }
  function identify(token) {
    if (!valid(token)) return;
    if (consent.managed && !consent.marketing) { try { sessionStorage.setItem('vitim_vtm', token); } catch (e) {} return; }
    setCt(token);
  }
  var waitingForms = null;
  function onConsent(c) {
    consent.marketing = !!c.marketing; consent.decided = !!c.decided;
    if (consent.decided && waitingForms) { var f = waitingForms; waitingForms = null; forms(f); } // formularele nu apar peste bannerul de cookie-uri
    if (c.marketing) { var p = null; try { p = sessionStorage.getItem('vitim_vtm'); sessionStorage.removeItem('vitim_vtm'); } catch (e) {} if (valid(p)) setCt(p); }
    else if (c.decided) { document.cookie = 'vitim_ct=; path=/; max-age=0'; }
  }
  var pendingVtm = null;
  try {
    var qs = new URLSearchParams(location.search), vtm = qs.get('vtm');
    if (vtm) { pendingVtm = vtm; qs.delete('vtm'); history.replaceState(history.state, '', location.pathname + (qs.toString() ? '?' + qs : '') + location.hash); }
  } catch (e) {}

  function cookies(c) {
    consent.managed = true;
    var start = function () { window.VitimCookies.start(c, { call: call, onChange: onConsent }); if (pendingVtm) identify(pendingVtm); };
    if (window.VitimCookies) return start();
    var s = document.createElement('script'); s.src = API + '/cookies.js?v=1'; s.async = true; s.onload = start; document.head.appendChild(s);
  }

  function init() {
    call('config').then(function (cfg) {
      if (!cfg) return;
      if (cfg.cookies) cookies(cfg.cookies); else if (pendingVtm) identify(pendingVtm);
      if (cfg.enabled && script.getAttribute('data-chat') !== '0') build(cfg);
      if (cfg.forms && cfg.forms.length) forms(cfg.forms);
    }).catch(function () {});
  }

  // formularele de abonare se încarcă doar dacă firma are cel puțin unul activ pe site
  function forms(list) {
    if (consent.managed && !consent.decided) { waitingForms = list; return; }
    var start = function () { window.VitimForms.start({ forms: list, call: call, identify: identify }); };
    if (window.VitimForms) return start();
    var s = document.createElement('script'); s.src = API + '/forms.js?v=1'; s.async = true; s.onload = start; document.head.appendChild(s);
  }
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
})();
