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
    ratio: '720x1280', transition: 'fade', motion: 'in', scenePhotos: [],
    music: 'none', musicVol: 35, voiceVol: 100, whoosh: true, fitVoice: true
  };
  var state = Object.assign({}, defaults, cfg.design || {});
  var photos = [];   // {img, label}

  /* ---------- sunet: voce, muzică generată sau fișier, efecte ---------- */
  var AC = window.AudioContext || window.webkitAudioContext, actx = null, master = null, recDest = null;
  var voiceEl = $('voice-audio'), voiceNode = null, fileEl = null, fileNode = null, live = [];
  var CHORDS = { calm: [[220, 261.6, 329.6], [174.6, 220, 261.6], [261.6, 329.6, 392], [196, 246.9, 293.7]],
    bright: [[261.6, 329.6, 392], [392, 493.9, 587.3], [440, 523.3, 659.3], [349.2, 440, 523.3]],
    pulse: [[220, 261.6, 329.6], [174.6, 220, 261.6], [261.6, 329.6, 392], [196, 246.9, 293.7]] };
  function audioInit() {
    if (!AC) { return false; }
    if (!actx) { actx = new AC(); master = actx.createGain(); master.connect(actx.destination); recDest = actx.createMediaStreamDestination(); master.connect(recDest); }
    if (actx.state === 'suspended') { actx.resume(); }
    return true;
  }
  function tone(out, type, freq, at, len, peak) {
    var o = actx.createOscillator(), g = actx.createGain();
    o.type = type; o.frequency.value = freq;
    g.gain.setValueAtTime(0, at); g.gain.linearRampToValueAtTime(peak, at + Math.min(0.4, len * 0.3)); g.gain.linearRampToValueAtTime(0, at + len);
    o.connect(g); g.connect(out); o.start(at); o.stop(at + len + 0.05); live.push(o);
  }
  function generated(kind, out, t0, total) {
    var chords = CHORDS[kind], bar = kind === 'calm' ? 3 : 2, n = 0, t, c;
    for (t = 0; t < total; t += bar, n++) {
      c = chords[n % chords.length];
      c.forEach(function (f) { tone(out, 'sine', f, t0 + t, bar * 1.15, 0.16); tone(out, 'triangle', f / 2, t0 + t, bar * 1.15, 0.07); });
      if (kind !== 'calm') { for (var k = 0; k < bar * 4; k++) { tone(out, 'triangle', c[k % 3] * 2, t0 + t + k * 0.25, 0.22, kind === 'pulse' ? 0.07 : 0.05); } }
      if (kind === 'pulse') { for (var b = 0; b < bar * 2; b++) { var o = actx.createOscillator(), g = actx.createGain(), at = t0 + t + b * 0.5; o.frequency.setValueAtTime(120, at); o.frequency.exponentialRampToValueAtTime(40, at + 0.18); g.gain.setValueAtTime(0.5, at); g.gain.exponentialRampToValueAtTime(0.001, at + 0.2); o.connect(g); g.connect(out); o.start(at); o.stop(at + 0.22); live.push(o); } }
    }
  }
  function whoosh(at) {
    var len = 0.4, buf = actx.createBuffer(1, actx.sampleRate * len, actx.sampleRate), d = buf.getChannelData(0), i;
    for (i = 0; i < d.length; i++) { d[i] = Math.random() * 2 - 1; }
    var src = actx.createBufferSource(), f = actx.createBiquadFilter(), g = actx.createGain();
    src.buffer = buf; f.type = 'bandpass'; f.Q.value = 1.2; f.frequency.setValueAtTime(400, at); f.frequency.exponentialRampToValueAtTime(3200, at + len);
    g.gain.setValueAtTime(0, at); g.gain.linearRampToValueAtTime(0.22, at + len * 0.5); g.gain.linearRampToValueAtTime(0, at + len);
    src.connect(f); f.connect(g); g.connect(master); src.start(at); live.push(src);
  }
  function hasVoice() { return !!(voiceEl && voiceEl.duration && isFinite(voiceEl.duration)); }
  function stopAudio() {
    live.forEach(function (n) { try { n.stop(); } catch (e) {} }); live = [];
    if (voiceEl) { voiceEl.pause(); } if (fileEl) { fileEl.pause(); }
  }
  /* pornește tot sunetul pentru un video de `total` secunde; `starts` = momentele în care încep scenele */
  function startAudio(total, starts) {
    stopAudio();
    if (!audioInit()) { return; }
    var t0 = actx.currentTime + 0.05, voice = hasVoice() && state.voiceVol > 0, level = state.musicVol / 100;
    var mg = actx.createGain(); mg.connect(master);
    // muzica stă mai încet cât vorbește vocea și se stinge la final
    mg.gain.setValueAtTime(level * (voice ? 0.35 : 1), t0);
    if (voice) { var end = t0 + 0.4 + voiceEl.duration; if (end < t0 + total - 1) { mg.gain.setValueAtTime(level * 0.35, end); mg.gain.linearRampToValueAtTime(level, end + 0.8); } }
    mg.gain.setValueAtTime(mg.gain.value, t0 + Math.max(0, total - 1)); mg.gain.linearRampToValueAtTime(0, t0 + total);
    if (CHORDS[state.music]) { generated(state.music, mg, t0, total); }
    else if (state.music === 'file' && fileEl) { if (!fileNode) { fileNode = actx.createMediaElementSource(fileEl); } fileNode.disconnect(); fileNode.connect(mg); fileEl.currentTime = 0; fileEl.play().catch(function () {}); }
    if (voice) {
      if (!voiceNode) { voiceNode = actx.createMediaElementSource(voiceEl); voiceNode._gain = actx.createGain(); voiceNode.connect(voiceNode._gain); voiceNode._gain.connect(master); }
      voiceNode._gain.gain.value = state.voiceVol / 100; voiceEl.currentTime = 0;
      setTimeout(function () { voiceEl.play().catch(function () {}); }, 400);
    }
    if (state.whoosh && state.transition !== 'none') { starts.slice(1).forEach(function (s) { whoosh(t0 + Math.max(0, s - 0.15)); }); }
  }
  function hasSound() { return (hasVoice() && state.voiceVol > 0) || CHORDS[state.music] || (state.music === 'file' && fileEl); }
  if (voiceEl) { voiceEl.addEventListener('play', function () { if (voiceNode) { audioInit(); } }); voiceEl.addEventListener('loadedmetadata', function () { sceneList(); }); }

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
      var iw = img.videoWidth || img.width, ih = img.videoHeight || img.height;
      var base = contain ? Math.min(W * 0.9 / iw, H * 0.72 / ih) : Math.max(W / iw, H / ih);
      var s = base * (state.zoom / 100) * (o.zoom || 1), w = iw * s, h = ih * s;
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
  function addPhoto(src, label, done, video) {
    if (video) { // clip video: se desenează cadru cu cadru, fără sunetul lui
      var v = document.createElement('video'), ok = false;
      v.muted = true; v.loop = true; v.playsInline = true; v.preload = 'auto';
      v.onloadeddata = function () { if (!ok) { ok = true; photos.push({ img: v, label: 'Clip: ' + label, video: true, url: src }); done(); } };
      v.onerror = function () { if (!ok) { ok = true; done(); } }; v.src = src; v.load();
      return;
    }
    var img = new Image();
    img.onload = function () { photos.push({ img: img, label: label, url: src }); done(); };
    img.onerror = done; img.src = src;
  }
  function loadLibrary() {
    var left = cfg.assets.length;
    if (!left) { refresh(); return; }
    // se încarcă în paralel, dar rămân în ordinea din materiale (materialul postării e primul)
    var urls = cfg.assets.map(function (a) { return a.url; });
    cfg.assets.forEach(function (a) { addPhoto(a.url, a.title || 'Fotografie', function () {
      if (--left === 0) { photos.sort(function (x, y) { return urls.indexOf(x.url) - urls.indexOf(y.url); }); refresh(); }
    }, a.video); });
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
    out = out.length ? out : [{ text: $('hook').value, seconds: 4 }];
    if (state.fitVoice && hasVoice()) { // video-ul durează cât vocea, cu scenele în aceeași proporție
      var sum = out.reduce(function (a, x) { return a + x.seconds; }, 0), k = (voiceEl.duration + 1.2) / sum;
      out.forEach(function (x) { x.seconds = Math.round(x.seconds * k * 10) / 10; });
    }
    return out;
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
    $('video-length').textContent = 'Durată: ' + Math.round(total) + ' secunde, ' + list.length + (list.length === 1 ? ' scenă' : ' scene');
  }
  function sizeVideo() { var p = state.ratio.split('x'), c = $('video'); if (c.width !== +p[0] || c.height !== +p[1]) { c.width = +p[0]; c.height = +p[1]; } }
  function still(i) { sizeVideo(); var list = scenes(); i = Math.min(i || 0, list.length - 1); frame($('video'), scenePhoto(i), list[i].text); }
  var playing = null;
  function clip(img) { // pornește clipul scenei curente și îl oprește pe cel dinainte
    if (playing === img) { return; }
    if (playing && playing.pause) { playing.pause(); }
    playing = img && img.play ? img : null;
    if (playing) { playing.currentTime = 0; playing.play().catch(function () {}); }
  }
  function motion(local) { return state.motion === 'in' ? 1 + local * 0.08 : state.motion === 'out' ? 1.08 - local * 0.08 : 1; }
  var raf = null;
  function play(done) {
    sizeVideo();
    var list = scenes(), canvas = $('video'), ctx = canvas.getContext('2d'), W = canvas.width, H = canvas.height, start = performance.now(), T = 0.5;
    var total = list.reduce(function (a, s) { return a + s.seconds; }, 0);
    cancelAnimationFrame(raf);
    var starts = [], acc0 = 0; list.forEach(function (x) { starts.push(acc0); acc0 += x.seconds; });
    startAudio(total, starts);
    (function tick(now) {
      var t = Math.min((now - start) / 1000, total), acc = 0, i = 0;
      while (i < list.length - 1 && t >= acc + list[i].seconds) { acc += list[i].seconds; i++; }
      var inScene = t - acc, local = Math.min(1, inScene / list[i].seconds);
      clip(scenePhoto(i));
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
      if (t < total) { raf = requestAnimationFrame(tick); } else { clip(null); setTimeout(stopAudio, 150); if (done) { done(); } }
    })(start);
  }
  function record() {
    var note = $('video-note'), canvas = $('video');
    if (!window.MediaRecorder || !canvas.captureStream) { note.textContent = 'Browserul acesta nu poate crea video. Încearcă în Chrome, Edge sau Safari, la zi.'; return; }
    var type = ['video/mp4;codecs=avc1,mp4a.40.2', 'video/mp4', 'video/webm;codecs=vp9,opus', 'video/webm'].filter(function (t) { return MediaRecorder.isTypeSupported(t); })[0];
    if (!type) { note.textContent = 'Browserul acesta nu poate crea video.'; return; }
    var stream = canvas.captureStream(30), sound = hasSound() && audioInit();
    if (sound) { recDest.stream.getAudioTracks().forEach(function (tr) { stream.addTrack(tr); }); }
    var chunks = [], rec = new MediaRecorder(stream, { mimeType: type, videoBitsPerSecond: 8000000 });
    rec.ondataavailable = function (e) { if (e.data.size) { chunks.push(e.data); } };
    rec.onstop = function () {
      var ext = type.indexOf('mp4') > -1 ? 'mp4' : 'webm', a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob(chunks, { type: type.split(';')[0] })); a.download = 'video-' + cfg.day + '.' + ext; a.click();
      note.textContent = 'Video creat (' + ext.toUpperCase() + (sound ? ', cu sunet' : ', fără sunet') + ').' + (ext === 'webm' ? ' Pentru Instagram creează-l din Safari sau dintr-un Chrome la zi, care salvează MP4.' : '');
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
    fileEl = f ? new Audio(URL.createObjectURL(f)) : null; fileNode = null;
    if (f) { state.music = 'file'; $('a-music').value = 'file'; save(); }
  });
  $('listen').addEventListener('click', function () { play(function () {}); });
  $('stop').addEventListener('click', function () { cancelAnimationFrame(raf); clip(null); stopAudio(); });
  document.querySelectorAll('[data-asset]').forEach(function (r) {
    r.addEventListener('change', function () {
      document.querySelectorAll('[data-asset]').forEach(function (o) { o.nextElementSibling.style.borderColor = o.checked ? 'var(--brand)' : 'transparent'; });
      var el = r.nextElementSibling, src = el.currentSrc || el.src, isVideo = el.tagName === 'VIDEO', found = -1;
      src = src.split('#')[0];
      photos.forEach(function (p, i) { if (p.img.src === src) { found = i; } });
      if (found > 0) { photos.unshift(photos.splice(found, 1)[0]); refresh(); } else if (found < 0) { addPhoto(src, r.parentNode.title || 'Fotografie', function () { photos.unshift(photos.pop()); refresh(); }, isVideo); }
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
