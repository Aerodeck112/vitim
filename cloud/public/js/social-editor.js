/* Editorul foto și video al postărilor. Totul se desenează în browser, pe canvas; nimic nu pleacă din pagină. */
(function () {
  'use strict';
  var root = document.getElementById('editor');
  if (!root) { return; }
  var cfg = JSON.parse(root.dataset.config);
  var $ = function (id) { return document.getElementById(id); };
  var FONT = 'system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif';
  var defaults = {
    shape: '1080x1350', fit: 'cover', zoom: 100, x: 50, y: 50, bright: 100, contrast: 100, sat: 100, dark: 60,
    pos: 'bottom', size: 100, brand: true, bar: true, color: cfg.color,
    ratio: '720x1280', transition: 'fade', motion: 'in', scenePhotos: []
  };
  var state = Object.assign({}, defaults, cfg.design || {});
  var photos = [];   // {img, label}
  var music = null;  // HTMLAudioElement

  /* ---------- desen ---------- */
  function wrap(ctx, text, max) {
    var out = [], line = '';
    String(text).split(/\s+/).forEach(function (w) {
      var t = line ? line + ' ' + w : w;
      if (ctx.measureText(t).width > max && line) { out.push(line); line = w; } else { line = t; }
    });
    if (line) { out.push(line); }
    return out;
  }
  function drawPhoto(ctx, W, H, img, o) {
    ctx.save();
    ctx.fillStyle = state.color; ctx.fillRect(0, 0, W, H);
    if (img) {
      var contain = state.fit === 'contain';
      var base = contain ? Math.min(W * 0.9 / img.width, H * 0.72 / img.height) : Math.max(W / img.width, H / img.height);
      var s = base * (state.zoom / 100) * (o.zoom || 1), w = img.width * s, h = img.height * s;
      var px = contain ? (W - w) / 2 : (W - w) * (state.x / 100), py = contain ? (H - h) * 0.38 : (H - h) * (state.y / 100);
      if ('filter' in ctx) { ctx.filter = 'brightness(' + state.bright + '%) contrast(' + state.contrast + '%) saturate(' + state.sat + '%)'; }
      if (contain) { ctx.shadowColor = 'rgba(0,0,0,.35)'; ctx.shadowBlur = W * 0.03; ctx.shadowOffsetY = W * 0.01; }
      ctx.drawImage(img, px + (o.dx || 0), py, w, h);
      ctx.filter = 'none'; ctx.shadowColor = 'transparent';
      if (!contain && state.dark > 0) {
        var a = state.dark / 100, g;
        if (state.pos === 'center') { ctx.fillStyle = 'rgba(0,0,0,' + (a * 0.6) + ')'; ctx.fillRect(0, 0, W, H); } else {
          g = state.pos === 'top' ? ctx.createLinearGradient(0, H * 0.55, 0, 0) : ctx.createLinearGradient(0, H * 0.45, 0, H);
          g.addColorStop(0, 'rgba(0,0,0,0)'); g.addColorStop(1, 'rgba(0,0,0,' + a + ')');
          ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
        }
      }
    }
    ctx.restore();
  }
  function drawText(ctx, W, H, text, o) {
    var size = Math.round(W * 0.072 * state.size / 100), lh = size * 1.2, alpha = o.alpha === undefined ? 1 : o.alpha;
    ctx.save();
    ctx.globalAlpha = alpha;
    ctx.font = '700 ' + size + 'px ' + FONT; ctx.fillStyle = '#fff'; ctx.textBaseline = 'alphabetic';
    var lines = wrap(ctx, text, W * 0.84), block = lines.length * lh;
    var top = state.pos === 'top' ? H * 0.12 : state.pos === 'center' ? (H - block) / 2 : H - H * 0.11 - block;
    top += (1 - alpha) * size * 0.5; // textul urcă ușor când apare
    if (state.bar) { ctx.fillStyle = state.fit === 'contain' ? '#fff' : state.color; ctx.fillRect(W * 0.08, top - size * 0.45, W * 0.14, Math.max(6, size * 0.12)); ctx.fillStyle = '#fff'; }
    ctx.shadowColor = 'rgba(0,0,0,.5)'; ctx.shadowBlur = size / 3;
    lines.forEach(function (l, i) { ctx.fillText(l, W * 0.08, top + size + i * lh); });
    ctx.restore();
    if (state.brand) {
      ctx.save(); ctx.font = '600 ' + Math.round(W * 0.03) + 'px ' + FONT; ctx.fillStyle = '#fff'; ctx.shadowColor = 'rgba(0,0,0,.5)'; ctx.shadowBlur = 8;
      ctx.fillText(cfg.brand, W * 0.08, state.pos === 'top' ? H * 0.075 : H - H * 0.045); ctx.restore();
    }
  }
  function frame(canvas, img, text, o) {
    o = o || {};
    var ctx = canvas.getContext('2d');
    drawPhoto(ctx, canvas.width, canvas.height, img, o);
    drawText(ctx, canvas.width, canvas.height, text, o);
  }

  /* ---------- fotografii ---------- */
  function addPhoto(src, label, done) {
    var img = new Image();
    img.onload = function () { photos.push({ img: img, label: label }); done(); };
    img.onerror = done; img.src = src;
  }
  function loadLibrary() {
    var left = cfg.assets.length;
    if (!left) { refresh(); return; }
    cfg.assets.forEach(function (a) { addPhoto(a.url, a.title || 'Fotografie', function () { if (--left === 0) { refresh(); } }); });
  }
  function photoAt(i) { return photos.length ? photos[Math.min(i, photos.length - 1)].img : null; }
  function scenePhoto(i) {
    if (!photos.length) { return null; }
    var pick = state.scenePhotos[i];
    return photoAt(pick === undefined || pick === null || pick >= photos.length ? i % photos.length : pick);
  }

  /* ---------- editor foto ---------- */
  function drawImage() {
    var p = state.shape.split('x'), c = $('image');
    if (c.width !== +p[0] || c.height !== +p[1]) { c.width = +p[0]; c.height = +p[1]; }
    frame(c, photoAt(0), $('hook').value);
  }
  function bind() {
    root.querySelectorAll('[data-set]').forEach(function (el) {
      var key = el.dataset.set, v = state[key];
      if (el.type === 'checkbox') { el.checked = !!v; } else { el.value = v; }
      el.addEventListener('input', function () {
        state[key] = el.type === 'checkbox' ? el.checked : (el.type === 'range' ? +el.value : el.value);
        save(); refresh();
      });
    });
    var c = $('image'), drag = null;
    c.addEventListener('pointerdown', function (e) { drag = { x: e.clientX, y: e.clientY, sx: state.x, sy: state.y }; c.setPointerCapture(e.pointerId); });
    c.addEventListener('pointermove', function (e) {
      if (!drag) { return; }
      var r = c.getBoundingClientRect();
      state.x = Math.max(0, Math.min(100, drag.sx - (e.clientX - drag.x) / r.width * 100));
      state.y = Math.max(0, Math.min(100, drag.sy - (e.clientY - drag.y) / r.height * 100));
      drawImage();
    });
    c.addEventListener('pointerup', function () { drag = null; save(); });
    $('hook').addEventListener('input', refresh);
    document.querySelectorAll('[data-scene-text],[data-scene-seconds]').forEach(function (el) { el.addEventListener('input', sceneList); });
    $('reset').addEventListener('click', function () { state = Object.assign({}, defaults); root.querySelectorAll('[data-set]').forEach(function (el) { if (el.type === 'checkbox') { el.checked = !!state[el.dataset.set]; } else { el.value = state[el.dataset.set]; } }); save(); refresh(); });
  }
  function save() { var f = $('design'); if (f) { f.value = JSON.stringify(state); } }

  /* ---------- editor video ---------- */
  function scenes() {
    var texts = document.querySelectorAll('[data-scene-text]'), secs = document.querySelectorAll('[data-scene-seconds]'), out = [];
    texts.forEach(function (t, i) { if (t.value.trim()) { out.push({ text: t.value.trim(), seconds: Math.max(2, Math.min(10, parseInt(secs[i].value, 10) || 4)) }); } });
    return out.length ? out : [{ text: $('hook').value, seconds: 4 }];
  }
  function sceneList() {
    var box = $('scene-list'), list = scenes(), total = 0;
    box.textContent = '';
    list.forEach(function (s, i) {
      total += s.seconds;
      var row = document.createElement('div'); row.className = 'scene-row';
      var label = document.createElement('span'); label.textContent = (i + 1) + '. ' + s.text + ' (' + s.seconds + ' s)';
      var sel = document.createElement('select'); sel.setAttribute('aria-label', 'Fotografia scenei ' + (i + 1));
      photos.forEach(function (p, n) { var o = document.createElement('option'); o.value = n; o.textContent = (n + 1) + '. ' + p.label.slice(0, 48); sel.appendChild(o); });
      if (photos.length) { var pick = state.scenePhotos[i]; sel.value = pick === undefined || pick === null || pick >= photos.length ? i % photos.length : pick; } else { sel.disabled = true; }
      sel.addEventListener('change', function () { state.scenePhotos[i] = +sel.value; save(); still(i); });
      row.appendChild(label); row.appendChild(sel); box.appendChild(row);
    });
    $('video-length').textContent = 'Durată: ' + total + ' secunde, ' + list.length + (list.length === 1 ? ' scenă' : ' scene');
  }
  function sizeVideo() { var p = state.ratio.split('x'), c = $('video'); if (c.width !== +p[0] || c.height !== +p[1]) { c.width = +p[0]; c.height = +p[1]; } }
  function still(i) { sizeVideo(); var list = scenes(); i = Math.min(i || 0, list.length - 1); frame($('video'), scenePhoto(i), list[i].text); }
  function motion(local) { return state.motion === 'in' ? 1 + local * 0.08 : state.motion === 'out' ? 1.08 - local * 0.08 : 1; }
  var raf = null;
  function play(done) {
    sizeVideo();
    var list = scenes(), canvas = $('video'), ctx = canvas.getContext('2d'), W = canvas.width, H = canvas.height, start = performance.now(), T = 0.5;
    var total = list.reduce(function (a, s) { return a + s.seconds; }, 0);
    cancelAnimationFrame(raf);
    if (music) { music.currentTime = 0; music.play().catch(function () {}); }
    (function tick(now) {
      var t = Math.min((now - start) / 1000, total), acc = 0, i = 0;
      while (i < list.length - 1 && t >= acc + list[i].seconds) { acc += list[i].seconds; i++; }
      var inScene = t - acc, local = Math.min(1, inScene / list[i].seconds);
      var enter = i > 0 && state.transition !== 'none' ? Math.min(1, inScene / T) : 1;
      if (enter < 1) { // tranziție: scena anterioară rămâne dedesubt
        drawPhoto(ctx, W, H, scenePhoto(i - 1), { zoom: motion(1) });
        ctx.save();
        if (state.transition === 'fade') { ctx.globalAlpha = enter; drawPhoto(ctx, W, H, scenePhoto(i), { zoom: motion(local) }); }
        else if (state.transition === 'slide') { ctx.beginPath(); ctx.rect(W * (1 - enter), 0, W * enter, H); ctx.clip(); drawPhoto(ctx, W, H, scenePhoto(i), { zoom: motion(local), dx: W * (1 - enter) * 0.25 }); }
        else { ctx.globalAlpha = enter; drawPhoto(ctx, W, H, scenePhoto(i), { zoom: motion(local) * (1.25 - 0.25 * enter) }); }
        ctx.restore();
      } else { drawPhoto(ctx, W, H, scenePhoto(i), { zoom: motion(local) }); }
      drawText(ctx, W, H, list[i].text, { alpha: Math.min(1, Math.max(0, (inScene - (i > 0 ? T * 0.6 : 0)) * 3)) });
      ctx.fillStyle = 'rgba(255,255,255,.85)'; ctx.fillRect(0, H - 6, W * (t / total), 6); // bara de progres
      if (t < total) { raf = requestAnimationFrame(tick); } else { if (music) { music.pause(); } if (done) { done(); } }
    })(start);
  }
  function record() {
    var note = $('video-note'), canvas = $('video');
    if (!window.MediaRecorder || !canvas.captureStream) { note.textContent = 'Browserul acesta nu poate crea video. Încearcă în Chrome, Edge sau Safari, la zi.'; return; }
    var type = ['video/mp4;codecs=avc1,mp4a.40.2', 'video/mp4', 'video/webm;codecs=vp9,opus', 'video/webm'].filter(function (t) { return MediaRecorder.isTypeSupported(t); })[0];
    if (!type) { note.textContent = 'Browserul acesta nu poate crea video.'; return; }
    var stream = canvas.captureStream(30), audioCtx = null;
    if (music) {
      try {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        var dest = audioCtx.createMediaStreamDestination(), src = music._node || (music._node = audioCtx.createMediaElementSource(music));
        src.connect(dest); src.connect(audioCtx.destination);
        dest.stream.getAudioTracks().forEach(function (tr) { stream.addTrack(tr); });
      } catch (e) { note.textContent = 'Muzica nu a putut fi adăugată; video-ul se creează fără sunet.'; }
    }
    var chunks = [], rec = new MediaRecorder(stream, { mimeType: type, videoBitsPerSecond: 8000000 });
    rec.ondataavailable = function (e) { if (e.data.size) { chunks.push(e.data); } };
    rec.onstop = function () {
      var ext = type.indexOf('mp4') > -1 ? 'mp4' : 'webm', a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob(chunks, { type: type.split(';')[0] })); a.download = 'video-' + cfg.day + '.' + ext; a.click();
      note.textContent = 'Video creat (' + ext.toUpperCase() + (music ? ', cu muzică' : ', fără sunet') + ').' + (ext === 'webm' ? ' Pentru Instagram creează-l din Safari sau dintr-un Chrome la zi, care salvează MP4.' : '');
      $('record').disabled = false;
    };
    $('record').disabled = true; note.textContent = 'Se înregistrează… ține pagina deschisă și în față.';
    rec.start(); play(function () { setTimeout(function () { rec.stop(); }, 200); });
  }

  function refresh() { drawImage(); sceneList(); still(0); }

  $('photos').addEventListener('change', function (e) {
    var files = Array.prototype.slice.call(e.target.files, 0, 8), left = files.length, first = photos.length;
    files.forEach(function (f) { addPhoto(URL.createObjectURL(f), f.name, function () { if (--left === 0) { photos = photos.slice(first).concat(photos.slice(0, first)); refresh(); } }); });
  });
  $('music').addEventListener('change', function (e) {
    var f = e.target.files[0];
    music = f ? new Audio(URL.createObjectURL(f)) : null;
    $('video-note').textContent = f ? 'Muzică adăugată: ' + f.name + '. Folosește doar muzică pentru care ai drept de folosire.' : '';
  });
  document.querySelectorAll('[data-asset]').forEach(function (r) {
    r.addEventListener('change', function () {
      document.querySelectorAll('[data-asset]').forEach(function (o) { o.nextElementSibling.style.borderColor = o.checked ? 'var(--brand)' : 'transparent'; });
      var src = r.nextElementSibling.src, found = -1;
      photos.forEach(function (p, i) { if (p.img.src === src) { found = i; } });
      if (found > 0) { photos.unshift(photos.splice(found, 1)[0]); refresh(); } else if (found < 0) { addPhoto(src, r.parentNode.title || 'Fotografie', function () { photos.unshift(photos.pop()); refresh(); }); }
    });
  });
  $('save-image').addEventListener('click', function () {
    $('image').toBlob(function (b) { var a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = 'postare-' + cfg.day + '.png'; a.click(); }, 'image/png');
  });
  $('play').addEventListener('click', function () { play(function () {}); });
  $('record').addEventListener('click', record);
  var copy = $('copy');
  if (copy) { copy.addEventListener('click', function () { navigator.clipboard.writeText(($('caption').value + '\n\n' + $('hashtags').value).trim()).then(function () { copy.textContent = 'Copiat'; setTimeout(function () { copy.textContent = 'Copiază textul'; }, 1500); }); }); }

  bind(); save(); refresh(); loadLibrary();
})();
