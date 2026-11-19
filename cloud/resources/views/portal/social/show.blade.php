@extends('layouts.app')
@section('title', 'Postare '.$post->day->format('d.m'))
@section('content')
@php($slug = $organization->slug)
@if (session('error'))<div class="alert alert-err" role="alert">{{ session('error') }}</div>@endif
<div class="head">
  <div><h1>Postarea din {{ $post->day->format('d.m.Y') }}</h1>
    <p><a href="{{ route('portal.social', [$slug, 's' => \App\Social\SocialPlanner::monday($post->day->toDateString())->toDateString()]) }}">← planul săptămânii</a>
      · <span class="badge {{ ['approved' => 'ok', 'published' => 'ok', 'skipped' => 'err'][$post->status] ?? 'warn' }}">{{ \App\Models\SocialPost::STATUSES[$post->status] }}</span>
      @if ($post->source === 'ai') · <span class="small muted">scrisă de agent, de verificat de un om</span>@endif</p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn" type="button" id="copy">Copiază textul</button>
    @if ($post->status !== 'approved' && $post->status !== 'published')<form method="post" action="{{ route('portal.social.status', [$slug, $post->id]) }}">@csrf<input type="hidden" name="status" value="approved"><button class="btn btn-p" type="submit">Aprobă</button></form>@endif
    @if ($post->status === 'approved')<form method="post" action="{{ route('portal.social.status', [$slug, $post->id]) }}">@csrf<input type="hidden" name="status" value="published"><button class="btn btn-p" type="submit">Am publicat-o</button></form>@endif
  </div>
</div>

<form method="post" action="{{ route('portal.social.update', [$slug, $post->id]) }}" class="card" id="post-form">
  @csrf @method('put')
  <h2>Textul</h2>
  <div class="row">
    <div class="fl"><label for="topic">Tema</label><input id="topic" type="text" name="topic" maxlength="150" required value="{{ old('topic', $post->topic) }}"></div>
    <div class="fl"><label for="day">Ziua</label><input id="day" type="date" name="day" required value="{{ old('day', $post->day->toDateString()) }}"></div>
  </div>
  <div class="fl"><label for="hook">Titlul de pe imagine (cârligul)</label><input id="hook" type="text" name="hook" maxlength="190" required value="{{ old('hook', $post->hook) }}"></div>
  <div class="fl"><label for="caption">Textul postării (Facebook, Instagram, descrierea de pe TikTok)</label><textarea id="caption" name="caption" maxlength="2200" required style="min-height:150px">{{ old('caption', $post->caption) }}</textarea></div>
  <div class="fl"><label for="hashtags">Hashtaguri</label><input id="hashtags" type="text" name="hashtags" maxlength="490" value="{{ old('hashtags', $post->hashtags) }}"></div>
  <div class="fl"><label for="visual">Ideea vizuală (ce fotografie sau filmare se potrivește)</label><textarea id="visual" name="visual" maxlength="1000">{{ old('visual', $post->visual) }}</textarea></div>
  <h2 style="margin-top:14px">Scenariul video (TikTok / Reels)</h2>
  <p class="small muted">Fiecare scenă: textul de pe ecran, ce se filmează sau ce fotografie se pune, și câte secunde durează. Scenele goale se șterg la salvare.</p>
  @php($scenes = array_pad(old('scenes', $post->scenes ?? []), max(5, count($post->scenes ?? [])), ['text' => '', 'shot' => '', 'seconds' => 4]))
  @foreach ($scenes as $i => $scene)
    <div style="display:grid;grid-template-columns:2fr 3fr 80px;gap:8px;margin-bottom:8px">
      <input type="text" name="scenes[{{ $i }}][text]" maxlength="140" value="{{ $scene['text'] ?? '' }}" placeholder="Scena {{ $i + 1 }}: text pe ecran" aria-label="Scena {{ $i + 1 }}: text pe ecran" data-scene-text>
      <input type="text" name="scenes[{{ $i }}][shot]" maxlength="300" value="{{ $scene['shot'] ?? '' }}" placeholder="ce se filmează" aria-label="Scena {{ $i + 1 }}: ce se filmează">
      <input type="number" name="scenes[{{ $i }}][seconds]" min="2" max="10" value="{{ $scene['seconds'] ?? 4 }}" aria-label="Scena {{ $i + 1 }}: secunde" data-scene-seconds>
    </div>
  @endforeach
  <button class="btn btn-p" type="submit">Salvează</button>
</form>

<div class="card">
  <h2>Cere agentului altă variantă</h2>
  <form method="post" action="{{ route('portal.social.rewrite', [$slug, $post->id]) }}" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap" onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Agentul scrie…'">@csrf
    <div class="fl" style="margin:0;flex:1;min-width:240px"><label for="instruction">Indicație (opțional)</label><input id="instruction" type="text" name="instruction" maxlength="300" placeholder="ex. mai scurt, mai mult despre livrare, fără emoji"></div>
    <button class="btn" type="submit">Rescrie</button></form>
  <p class="small muted" style="margin:8px 0 0">Rescrierea înlocuiește textul de mai sus și duce postarea înapoi la ciornă.</p>
</div>

<div class="card" id="atelier">
  <h2>Atelier: imagine și video</h2>
  <p class="small muted">Alege fotografii reale ale firmei de pe dispozitivul tău. Atelierul pune titlul peste fotografie, în culoarea firmei, și montează un video vertical din scenele de mai sus (câte o fotografie pe scenă, cu textul scenei). Fotografiile rămân pe dispozitivul tău: nu se încarcă în platformă.</p>
  <div class="row">
    <div class="fl"><label for="photos">Fotografii (una sau mai multe)</label><input id="photos" type="file" accept="image/*" multiple></div>
    <div class="fl"><label for="shape">Format imagine</label><select id="shape"><option value="1080x1350">Portret 4:5 (Instagram, Facebook)</option><option value="1080x1080">Pătrat 1:1</option><option value="1080x1920">Vertical 9:16 (story)</option></select></div>
  </div>
  <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">
    <div><canvas id="image" width="1080" height="1350" style="width:260px;height:auto;border-radius:10px;border:1px solid var(--border)"></canvas>
      <div style="margin-top:8px"><button class="btn" type="button" id="save-image">Descarcă imaginea</button></div></div>
    <div><canvas id="video" width="720" height="1280" style="width:200px;height:auto;border-radius:10px;border:1px solid var(--border)"></canvas>
      <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap"><button class="btn" type="button" id="play">Previzualizează</button><button class="btn" type="button" id="record">Creează video</button></div>
      <p class="small muted" id="video-note" style="margin:6px 0 0;max-width:260px"></p></div>
  </div>
</div>

<form method="post" action="{{ route('portal.social.destroy', [$slug, $post->id]) }}" onsubmit="return confirm('Ștergi această postare?')">@csrf @method('delete')<button class="btn btn-s" type="submit">Șterge postarea</button></form>

<script>
(function () {
  var color = @json($settings['color']), brand = @json($organization->name);
  var $ = function (id) { return document.getElementById(id); };
  var photos = [];
  $('copy').addEventListener('click', function () {
    navigator.clipboard.writeText(($('caption').value + '\n\n' + $('hashtags').value).trim()).then(function () { $('copy').textContent = 'Copiat'; setTimeout(function () { $('copy').textContent = 'Copiază textul'; }, 1500); });
  });

  function wrap(ctx, text, max) {
    var words = String(text).split(/\s+/), lines = [], line = '';
    words.forEach(function (w) { var t = line ? line + ' ' + w : w; if (ctx.measureText(t).width > max && line) { lines.push(line); line = w; } else { line = t; } });
    if (line) { lines.push(line); }
    return lines;
  }
  // desenează un cadru: fotografia (sau culoarea firmei), o bandă întunecată jos și textul; zoom = mișcare lentă pe video
  function frame(canvas, photo, text, zoom, alpha) {
    var ctx = canvas.getContext('2d'), W = canvas.width, H = canvas.height;
    ctx.globalAlpha = 1; ctx.fillStyle = color; ctx.fillRect(0, 0, W, H);
    if (photo) {
      var s = Math.max(W / photo.width, H / photo.height) * (zoom || 1), w = photo.width * s, h = photo.height * s;
      ctx.drawImage(photo, (W - w) / 2, (H - h) / 2, w, h);
      var g = ctx.createLinearGradient(0, H * 0.45, 0, H); g.addColorStop(0, 'rgba(0,0,0,0)'); g.addColorStop(1, 'rgba(0,0,0,.78)');
      ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    }
    ctx.globalAlpha = alpha === undefined ? 1 : alpha;
    var size = Math.round(W * 0.072);
    ctx.font = '700 ' + size + 'px system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif';
    ctx.fillStyle = '#fff'; ctx.textBaseline = 'alphabetic'; ctx.shadowColor = 'rgba(0,0,0,.45)'; ctx.shadowBlur = size / 3;
    var lines = wrap(ctx, text, W * 0.84), y = H - H * 0.11 - (lines.length - 1) * size * 1.2;
    lines.forEach(function (l, i) { ctx.fillText(l, W * 0.08, y + i * size * 1.2); });
    ctx.shadowBlur = 0;
    ctx.font = '600 ' + Math.round(size * 0.42) + 'px system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif';
    ctx.fillText(brand, W * 0.08, H - H * 0.045);
    ctx.fillStyle = color; ctx.fillRect(W * 0.08, y - size * 1.5, W * 0.14, Math.max(6, size * 0.12));
    ctx.globalAlpha = 1;
  }
  function drawImage() { frame($('image'), photos[0] || null, $('hook').value); }
  function scenes() {
    var texts = document.querySelectorAll('[data-scene-text]'), secs = document.querySelectorAll('[data-scene-seconds]'), out = [];
    texts.forEach(function (t, i) { if (t.value.trim()) { out.push({ text: t.value.trim(), seconds: Math.max(2, Math.min(10, parseInt(secs[i].value, 10) || 4)) }); } });
    return out.length ? out : [{ text: $('hook').value, seconds: 4 }];
  }
  var raf = null;
  function play(done) {
    var list = scenes(), canvas = $('video'), start = performance.now();
    var total = list.reduce(function (a, s) { return a + s.seconds; }, 0);
    cancelAnimationFrame(raf);
    (function tick(now) {
      var t = (now - start) / 1000, acc = 0, i = 0;
      while (i < list.length - 1 && t >= acc + list[i].seconds) { acc += list[i].seconds; i++; }
      var local = Math.min(1, (t - acc) / list[i].seconds);
      frame(canvas, photos.length ? photos[i % photos.length] : null, list[i].text, 1 + local * 0.08, Math.min(1, local * 5));
      if (t < total) { raf = requestAnimationFrame(tick); } else if (done) { done(); }
    })(start);
    return total;
  }
  $('photos').addEventListener('change', function (e) {
    photos = [];
    var files = Array.prototype.slice.call(e.target.files, 0, 8), left = files.length;
    files.forEach(function (f, i) { var img = new Image(); img.onload = function () { photos[i] = img; if (--left === 0) { photos = photos.filter(Boolean); drawImage(); play(); } }; img.onerror = function () { if (--left === 0) { photos = photos.filter(Boolean); drawImage(); } }; img.src = URL.createObjectURL(f); });
  });
  $('shape').addEventListener('change', function () { var p = this.value.split('x'); $('image').width = +p[0]; $('image').height = +p[1]; drawImage(); });
  $('hook').addEventListener('input', drawImage);
  $('save-image').addEventListener('click', function () {
    $('image').toBlob(function (b) { var a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = 'postare-{{ $post->day->toDateString() }}.png'; a.click(); }, 'image/png');
  });
  $('play').addEventListener('click', function () { play(); });
  $('record').addEventListener('click', function () {
    var note = $('video-note'), canvas = $('video');
    if (!window.MediaRecorder || !canvas.captureStream) { note.textContent = 'Browserul acesta nu poate crea video. Încearcă în Chrome, Edge sau Safari, la zi.'; return; }
    var type = ['video/mp4;codecs=avc1', 'video/mp4', 'video/webm;codecs=vp9', 'video/webm'].filter(function (t) { return MediaRecorder.isTypeSupported(t); })[0];
    if (!type) { note.textContent = 'Browserul acesta nu poate crea video.'; return; }
    var chunks = [], rec = new MediaRecorder(canvas.captureStream(30), { mimeType: type, videoBitsPerSecond: 6000000 });
    rec.ondataavailable = function (e) { if (e.data.size) { chunks.push(e.data); } };
    rec.onstop = function () {
      var ext = type.indexOf('mp4') > -1 ? 'mp4' : 'webm', a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob(chunks, { type: type })); a.download = 'video-{{ $post->day->toDateString() }}.' + ext; a.click();
      note.textContent = 'Video creat (' + ext.toUpperCase() + ', fără sunet). ' + (ext === 'webm' ? 'TikTok acceptă WebM; pentru Instagram creează-l din Safari sau dintr-un Chrome la zi, care salvează MP4.' : 'Adaugă muzica din aplicația rețelei, la publicare.');
      $('record').disabled = false;
    };
    $('record').disabled = true; note.textContent = 'Se înregistrează… ține pagina deschisă.';
    rec.start(); play(function () { setTimeout(function () { rec.stop(); }, 150); });
  });
  drawImage(); frame($('video'), null, scenes()[0].text);
})();
</script>
@endsection
