{{-- Ce se întâmplă acum: vizitatori pe site, mesaje noi cu sunet și notificare. Rulează pe orice pagină a portalului. --}}
<div class="live-toasts" aria-live="polite" data-live-toasts></div>
<script>
(function () {
  var URL_LIVE = @json($liveUrl), SOUND = 'vitim_live_sound', last = 0, first = true, timer = null, unseen = 0, title = document.title, ctx = null;
  var $ = function (s) { return document.querySelector(s); };
  function get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function put(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  var soundOn = get(SOUND) !== '0';

  // browserul permite sunetul doar după o interacțiune cu pagina: îl pregătim la primul click / tastă
  function audio() {
    if (!ctx) { try { ctx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { return null; } }
    if (ctx.state === 'suspended') ctx.resume();
    return ctx;
  }
  ['click', 'keydown', 'touchstart'].forEach(function (ev) { document.addEventListener(ev, function () { audio(); }, { once: true, passive: true }); });
  function chime() {
    var a = audio();
    if (!a || !soundOn) return;
    [[880, 0], [1320, .16]].forEach(function (n) {
      var o = a.createOscillator(), g = a.createGain(), t = a.currentTime + n[1];
      o.type = 'sine'; o.frequency.value = n[0];
      g.gain.setValueAtTime(.0001, t); g.gain.exponentialRampToValueAtTime(.18, t + .02); g.gain.exponentialRampToValueAtTime(.0001, t + .32);
      o.connect(g); g.connect(a.destination); o.start(t); o.stop(t + .34);
    });
  }

  var soundBtn = $('[data-live-sound]'), deskBtn = $('[data-live-desktop]');
  function paintSound() { if (!soundBtn) return; soundBtn.textContent = soundOn ? '🔔 Sunet la mesaje noi: pornit' : '🔕 Sunet la mesaje noi: oprit'; soundBtn.setAttribute('aria-pressed', soundOn ? 'true' : 'false'); }
  if (soundBtn) soundBtn.addEventListener('click', function () { soundOn = !soundOn; put(SOUND, soundOn ? '1' : '0'); paintSound(); if (soundOn) chime(); });
  paintSound();
  function paintDesk() { if (deskBtn) deskBtn.hidden = !('Notification' in window) || Notification.permission !== 'default'; }
  if (deskBtn) deskBtn.addEventListener('click', function () { Notification.requestPermission().then(paintDesk); });
  paintDesk();

  function toast(m) {
    var box = $('[data-live-toasts]'); if (!box) return;
    var t = document.createElement('a'); t.className = 'live-toast'; t.href = m.url;
    var b = document.createElement('b'); b.textContent = 'Mesaj nou · ' + m.from; t.appendChild(b);
    var p = document.createElement('span'); p.textContent = m.preview; t.appendChild(p);
    box.appendChild(t);
    setTimeout(function () { t.classList.add('out'); setTimeout(function () { t.remove(); }, 400); }, 12000);
  }
  function desktop(m) {
    if (!('Notification' in window) || Notification.permission !== 'granted' || !document.hidden) return;
    try { var n = new Notification('Mesaj nou de la ' + m.from, { body: m.preview, tag: 'vitim-conv-' + m.conversation_id }); n.onclick = function () { window.focus(); location.href = m.url; }; } catch (e) {}
  }

  function render(j) {
    var c = $('[data-live-count]'), l = $('[data-live-label]'), pill = $('[data-live-pill]');
    if (c) c.textContent = j.visitors;
    if (l) l.textContent = j.visitors === 1 ? 'vizitator pe site acum' : 'pe site acum';
    if (pill) pill.classList.toggle('has', j.visitors > 0);
    var badge = $('[data-live-badge]');
    if (badge && typeof j.waiting === 'number') { badge.hidden = j.waiting === 0; badge.textContent = j.waiting; badge.title = j.waiting + ' conversații așteaptă un om'; }
    document.dispatchEvent(new CustomEvent('vitim:live', { detail: j }));
  }

  function tick() {
    fetch(URL_LIVE + (last ? '?after=' + last : ''), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        if (!j) return;
        render(j);
        // pe conversația deschisă, pagina ei are deja sunetul și mesajele
        var open = +((document.getElementById('chat') || {}).dataset || {}).conversation || 0;
        var fresh = (j.messages || []).filter(function (m) { return m.conversation_id !== open; });
        if (!first && fresh.length) {
          chime();
          fresh.slice(-3).forEach(function (m) { toast(m); desktop(m); });
          if (document.hidden) { unseen += fresh.length; document.title = '(' + unseen + ') Mesaj nou · ' + title; }
        }
        if (typeof j.last_id === 'number') last = Math.max(last, j.last_id);
        first = false;
      })
      .catch(function () {})
      .then(function () { timer = setTimeout(tick, document.hidden ? 20000 : 8000); });
  }
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { unseen = 0; document.title = title; clearTimeout(timer); tick(); } });
  tick();
})();
</script>
