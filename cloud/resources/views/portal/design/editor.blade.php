@extends('layouts.app')
@section('title', 'Editor email · '.$title)
@section('content')
@php($slug = $organization->slug)
@include('partials.marketing-tabs')
<div class="head"><div><h1>Editor vizual · {{ $title }}</h1>
  <p>Trage blocurile din stânga în email, apoi apasă pe un bloc ca să-l modifici. Logo-ul, culoarea și rețelele sociale vin din <a href="{{ route('portal.brand', $slug) }}">Brand</a>.</p></div>
  <a class="btn" href="{{ $backUrl }}">Înapoi</a></div>
@if (! $editable)<div class="alert alert-warn">Campania a fost aprobată: designul nu se mai poate modifica.</div>@endif

<form method="post" action="{{ $saveUrl }}" id="ed-form">
  @csrf @method('put')
  <input type="hidden" name="blocks" id="ed-blocks">
  <div class="ed">
    <aside class="ed-side">
      <div class="card">
        <h2>Blocuri</h2>
        <div class="ed-palette">
          @foreach ($types as $type => $label)
            <button type="button" class="ed-pal" draggable="true" data-add="{{ $type }}" @disabled(! $editable)>{{ ['logo' => '🏷️', 'heading' => '🔠', 'text' => '¶', 'image' => '🖼️', 'button' => '🔘', 'columns' => '◫', 'product' => '🛍️', 'divider' => '—', 'spacer' => '↕', 'social' => '🔗', 'cart' => '🛒'][$type] ?? '' }} {{ $label }}</button>
          @endforeach
        </div>
      </div>
      <div class="card">
        <h2>Designuri gata făcute</h2>
        @foreach ($library as $key => $t)
          <button type="button" class="ed-lib" data-lib="{{ $key }}" @disabled(! $editable)><strong>{{ $t['name'] }}</strong><span class="small muted">{{ $t['description'] }}</span></button>
        @endforeach
        @if ($saved->isNotEmpty())
          <div class="lbl" style="margin-top:12px">Salvate de voi</div>
          @foreach ($saved as $t)<button type="button" class="ed-lib" data-saved="{{ $t->id }}" @disabled(! $editable)><strong>{{ $t->name }}</strong></button>@endforeach
        @endif
        @if ($editable)<button type="button" class="btn btn-s" id="ed-save-tpl" style="margin-top:10px">Salvează designul ca șablon</button>@endif
      </div>
    </aside>

    <section class="ed-main">
      <div class="card">
        <div class="fl"><label for="preheader">Text de previzualizare (apare lângă subiect în inbox)</label>
          <input id="preheader" type="text" name="preheader" maxlength="150" value="{{ old('preheader', $preheader) }}" @disabled(! $editable) placeholder="ex. Doar până duminică: 20% la toată gama"></div>
        <div id="ed-canvas" class="ed-canvas" aria-label="Blocurile emailului"></div>
        <p class="small muted" id="ed-empty" hidden>Emailul e gol. Trage aici un bloc din stânga sau alege un design gata făcut.</p>
      </div>
    </section>

    <aside class="ed-preview">
      <div class="card">
        <h2>Cum arată</h2>
        <div class="small muted">Subiect: <strong>{{ $subject ?: '—' }}</strong></div>
        <div class="ed-device"><button type="button" class="btn btn-s" data-w="100%">Desktop</button><button type="button" class="btn btn-s" data-w="380px">Telefon</button></div>
        <iframe id="ed-frame" title="Previzualizare email" sandbox style="width:100%;height:640px;border:1px solid var(--border);border-radius:10px;background:#fff"></iframe>
        <p class="small muted" style="margin-bottom:0">Exemplu pentru contactul „Maria Popescu”. Variabile: @{{prenume}}, @{{nume}}, @{{firma}}. **text** = îngroșat.</p>
      </div>
    </aside>
  </div>
  @if ($editable)<div class="ed-bar"><span class="small muted" id="ed-status">Modificările nu sunt salvate până apeși „Salvează”.</span><button class="btn btn-p" type="submit">Salvează designul</button></div>@endif
</form>

<input type="file" id="ed-file" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
<script>
(function () {
  var blocks = @json($blocks), editable = @json($editable), library = @json(collect($library)->map(fn ($t) => $t['blocks'])),
      saved = @json($saved->mapWithKeys(fn ($t) => [$t->id => $t->blocks])), products = @json($products),
      labels = @json($types), token = @json(csrf_token()),
      urls = {preview: @json(route('portal.design.preview', $slug)), upload: @json(route('portal.design.upload', $slug)), tpl: @json(route('portal.design.templates.store', $slug))},
      subject = @json($subject), canvas = document.getElementById('ed-canvas'), frame = document.getElementById('ed-frame'),
      selected = -1, dragFrom = null, timer = null, dirty = false;

  var defaults = {
    logo: {align: 'center'}, heading: {text: 'Titlul tău', size: 'h1', align: 'left'}, text: {text: 'Scrie aici textul.', align: 'left'},
    image: {url: '', alt: '', link: '', width: 100}, button: {label: 'Vezi oferta', url: '', align: 'center', color: ''},
    columns: {left_image: '', left_text: '**Titlu**\nText scurt.', left_link: '', right_image: '', right_text: '**Titlu**\nText scurt.', right_link: ''},
    product: {product_id: null, name: 'Numele produsului', price: '', image: '', url: '', label: 'Cumpără acum', description: ''},
    divider: {}, spacer: {height: 24}, social: {}, cart: {title: 'Produsele tale', label: 'Finalizează comanda'}
  };
  var fields = {
    logo: [['align', 'Aliniere', 'align']],
    heading: [['text', 'Text', 'text'], ['size', 'Mărime', 'size'], ['align', 'Aliniere', 'align']],
    text: [['text', 'Text', 'area'], ['align', 'Aliniere', 'align']],
    image: [['url', 'Imagine', 'image'], ['alt', 'Descriere (pentru cine nu vede imaginile)', 'text'], ['link', 'Link la click (https://…)', 'url'], ['width', 'Lățime (%)', 'range']],
    button: [['label', 'Textul butonului', 'text'], ['url', 'Link (https://…)', 'url'], ['align', 'Aliniere', 'align'], ['color', 'Culoare (gol = culoarea brandului)', 'color']],
    columns: [['left_image', 'Stânga: imagine', 'image'], ['left_text', 'Stânga: text', 'area'], ['left_link', 'Stânga: link', 'url'],
      ['right_image', 'Dreapta: imagine', 'image'], ['right_text', 'Dreapta: text', 'area'], ['right_link', 'Dreapta: link', 'url']],
    product: [['product_id', 'Produs din catalog', 'product'], ['name', 'Nume', 'text'], ['price', 'Preț', 'text'], ['image', 'Imagine', 'image'],
      ['url', 'Link (https://…)', 'url'], ['label', 'Textul butonului', 'text'], ['description', 'Descriere', 'area']],
    divider: [], spacer: [['height', 'Înălțime (px)', 'number']], social: [],
    cart: [['title', 'Titlu', 'text'], ['label', 'Textul butonului (duce la coșul salvat)', 'text']]
  };

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function summary(b) {
    var s = b.text || b.name || b.label || b.left_text || b.alt || (b.url ? b.url.split('/').pop() : '') || '';
    if (b.type === 'spacer') s = b.height + ' px';
    if (b.type === 'social') s = 'linkurile din Brand';
    if (b.type === 'logo') s = 'logo-ul din Brand';
    if (b.type === 'cart') s = 'se completează din coșul / comanda contactului';
    if ((b.type === 'image' || b.type === 'columns') && !b.url && !b.left_image) s = s || 'fără imagine încă';
    return String(s).replace(/\*\*/g, '').slice(0, 90);
  }

  function input(b, i, f) {
    var key = f[0], label = f[1], kind = f[2], v = b[key] == null ? '' : b[key], id = 'f' + i + key, dis = editable ? '' : ' disabled', h = '';
    if (kind === 'area') h = '<textarea id="' + id + '" data-f="' + key + '" rows="5"' + dis + '>' + esc(v) + '</textarea>';
    else if (kind === 'align') h = '<select id="' + id + '" data-f="' + key + '"' + dis + '>' + [['left', 'stânga'], ['center', 'centru'], ['right', 'dreapta']].map(function (o) { return '<option value="' + o[0] + '"' + (v === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select>';
    else if (kind === 'size') h = '<select id="' + id + '" data-f="' + key + '"' + dis + '><option value="h1"' + (v === 'h1' ? ' selected' : '') + '>mare</option><option value="h2"' + (v === 'h2' ? ' selected' : '') + '>mediu</option></select>';
    else if (kind === 'range') h = '<input id="' + id + '" type="range" min="20" max="100" step="5" data-f="' + key + '" value="' + esc(v) + '"' + dis + '>';
    else if (kind === 'number') h = '<input id="' + id + '" type="number" min="8" max="80" data-f="' + key + '" value="' + esc(v) + '"' + dis + '>';
    else if (kind === 'color') h = '<input id="' + id + '" type="text" maxlength="7" placeholder="#2f6bff" data-f="' + key + '" value="' + esc(v) + '"' + dis + '>';
    else if (kind === 'image') h = '<div style="display:flex;gap:6px"><input id="' + id + '" type="url" placeholder="https://…" data-f="' + key + '" value="' + esc(v) + '"' + dis + '>' + (editable ? '<button type="button" class="btn btn-s" data-upload="' + key + '">Încarcă</button>' : '') + '</div>';
    else if (kind === 'product') {
      if (!products.length) return '<p class="small muted">Catalogul de produse apare aici după conectarea magazinului WooCommerce. Până atunci completează câmpurile de mai jos.</p>';
      h = '<select id="' + id + '" data-product' + dis + '><option value="">— alege —</option>' + products.map(function (p) { return '<option value="' + p.id + '"' + (+v === p.id ? ' selected' : '') + '>' + esc(p.name) + '</option>'; }).join('') + '</select>';
    } else h = '<input id="' + id + '" type="' + (kind === 'url' ? 'url' : 'text') + '" data-f="' + key + '" value="' + esc(v) + '"' + dis + (kind === 'url' ? ' placeholder="https://…"' : '') + '>';
    return '<div class="fl"><label for="' + id + '">' + label + '</label>' + h + '</div>';
  }

  function render() {
    canvas.innerHTML = blocks.map(function (b, i) {
      return '<div class="ed-block' + (i === selected ? ' on' : '') + '" data-i="' + i + '"' + (editable ? ' draggable="true"' : '') + '>'
        + '<div class="ed-head"><span class="ed-grip" aria-hidden="true">⠿</span><button type="button" class="ed-title" data-select="' + i + '"><strong>' + esc(labels[b.type] || b.type) + '</strong> <span class="small muted">' + esc(summary(b)) + '</span></button>'
        + (editable ? '<span class="ed-tools"><button type="button" class="btn btn-s" data-move="-1" data-i="' + i + '" aria-label="Mută în sus">↑</button><button type="button" class="btn btn-s" data-move="1" data-i="' + i + '" aria-label="Mută în jos">↓</button><button type="button" class="btn btn-s" data-copy="' + i + '" aria-label="Duplică">⧉</button><button type="button" class="btn btn-s btn-d" data-del="' + i + '" aria-label="Șterge">×</button></span>' : '')
        + '</div>' + (i === selected ? '<div class="ed-insp" data-i="' + i + '">' + (fields[b.type] || []).map(function (f) { return input(b, i, f); }).join('') + ((fields[b.type] || []).length ? '' : '<p class="small muted">Blocul nu are setări.</p>') + '</div>' : '')
        + '</div>';
    }).join('');
    document.getElementById('ed-empty').hidden = blocks.length > 0;
    schedule();
  }

  function schedule() { clearTimeout(timer); timer = setTimeout(preview, 350); }
  function preview() {
    var data = new FormData(); data.append('_token', token); data.append('blocks', JSON.stringify(blocks));
    data.append('preheader', document.getElementById('preheader').value); data.append('subject', subject);
    fetch(urls.preview, {method: 'POST', body: data, headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
      .then(function (r) { return r.json(); }).then(function (j) { frame.srcdoc = j.html || ''; }).catch(function () {});
  }
  function changed() { dirty = true; var st = document.getElementById('ed-status'); if (st) st.textContent = 'Ai modificări nesalvate.'; }
  function add(type, at) {
    var b = Object.assign({type: type}, JSON.parse(JSON.stringify(defaults[type] || {})));
    at = at == null ? blocks.length : at; blocks.splice(at, 0, b); selected = at; changed(); render();
  }

  canvas.addEventListener('click', function (e) {
    var t = e.target.closest('button'); if (!t) return;
    if (t.dataset.select != null) { selected = +t.dataset.select === selected ? -1 : +t.dataset.select; render(); }
    else if (t.dataset.move) { var i = +t.dataset.i, j = i + +t.dataset.move; if (j < 0 || j >= blocks.length) return; blocks.splice(j, 0, blocks.splice(i, 1)[0]); selected = j; changed(); render(); }
    else if (t.dataset.copy != null) { var c = +t.dataset.copy; blocks.splice(c + 1, 0, JSON.parse(JSON.stringify(blocks[c]))); selected = c + 1; changed(); render(); }
    else if (t.dataset.del != null) { blocks.splice(+t.dataset.del, 1); selected = -1; changed(); render(); }
    else if (t.dataset.upload) { upload(+t.closest('.ed-insp').dataset.i, t.dataset.upload); }
  });
  canvas.addEventListener('input', function (e) {
    var insp = e.target.closest('.ed-insp'); if (!insp) return;
    var b = blocks[+insp.dataset.i];
    if (e.target.dataset.f) { var v = e.target.value; b[e.target.dataset.f] = (e.target.type === 'range' || e.target.type === 'number') ? +v : v; }
    var title = insp.parentNode.querySelector('.ed-title .muted'); if (title) title.textContent = summary(b);
    changed(); schedule();
  });
  canvas.addEventListener('change', function (e) {
    if (!e.target.hasAttribute('data-product')) return;
    var i = +e.target.closest('.ed-insp').dataset.i, p = products.find(function (x) { return x.id === +e.target.value; });
    if (p) { Object.assign(blocks[i], {product_id: p.id, name: p.name, price: p.price ? (Number(p.price).toLocaleString('ro-RO', {minimumFractionDigits: 2}) + ' ' + (p.currency || 'lei')) : '', image: p.image || '', url: p.url || ''}); changed(); render(); }
  });

  // drag & drop: din paletă (blocuri noi) și reordonare în email
  document.querySelectorAll('[data-add]').forEach(function (b) {
    b.addEventListener('click', function () { add(b.dataset.add); });
    b.addEventListener('dragstart', function (e) { dragFrom = {add: b.dataset.add}; e.dataTransfer.effectAllowed = 'copy'; e.dataTransfer.setData('text/plain', b.dataset.add); });
  });
  canvas.addEventListener('dragstart', function (e) { var el = e.target.closest('.ed-block'); if (!el) return; dragFrom = {move: +el.dataset.i}; e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', el.dataset.i); });
  function dropIndex(e) {
    var els = canvas.querySelectorAll('.ed-block');
    for (var k = 0; k < els.length; k++) { var r = els[k].getBoundingClientRect(); if (e.clientY < r.top + r.height / 2) return k; }
    return els.length;
  }
  canvas.addEventListener('dragover', function (e) {
    if (!dragFrom || !editable) return; e.preventDefault();
    canvas.querySelectorAll('.ed-drop').forEach(function (x) { x.classList.remove('ed-drop'); });
    var els = canvas.querySelectorAll('.ed-block'), at = dropIndex(e);
    if (els[at]) els[at].classList.add('ed-drop'); else canvas.classList.add('ed-drop');
  });
  canvas.addEventListener('dragleave', function () { canvas.classList.remove('ed-drop'); });
  canvas.addEventListener('drop', function (e) {
    if (!dragFrom || !editable) return; e.preventDefault(); canvas.classList.remove('ed-drop');
    var at = dropIndex(e);
    if (dragFrom.add) add(dragFrom.add, at);
    else { var from = dragFrom.move; if (at > from) at--; blocks.splice(at, 0, blocks.splice(from, 1)[0]); selected = at; changed(); render(); }
    dragFrom = null;
  });
  document.addEventListener('dragend', function () { dragFrom = null; canvas.classList.remove('ed-drop'); canvas.querySelectorAll('.ed-drop').forEach(function (x) { x.classList.remove('ed-drop'); }); });

  document.querySelectorAll('[data-lib],[data-saved]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (blocks.length && !confirm('Înlocuiești designul actual cu „' + b.querySelector('strong').textContent + '”?')) return;
      blocks = JSON.parse(JSON.stringify(b.dataset.lib ? library[b.dataset.lib] : saved[b.dataset.saved])); selected = -1; changed(); render();
    });
  });
  var saveTpl = document.getElementById('ed-save-tpl');
  if (saveTpl) saveTpl.addEventListener('click', function () {
    var name = prompt('Numele șablonului:'); if (!name) return;
    var data = new FormData(); data.append('_token', token); data.append('name', name); data.append('blocks', JSON.stringify(blocks));
    fetch(urls.tpl, {method: 'POST', body: data, headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
      .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
      .then(function () { alert('Șablonul „' + name + '” a fost salvat. Îl găsești în lista „Salvate de voi”.'); }, function () { alert('Șablonul nu a putut fi salvat.'); });
  });

  var file = document.getElementById('ed-file'), target = null;
  function upload(i, key) { target = [i, key]; file.value = ''; file.click(); }
  file.addEventListener('change', function () {
    if (!file.files[0] || !target) return;
    var data = new FormData(); data.append('_token', token); data.append('image', file.files[0]);
    fetch(urls.upload, {method: 'POST', body: data, headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
      .then(function (r) { return r.json().then(function (j) { return r.ok ? j : Promise.reject(j); }); })
      .then(function (j) { blocks[target[0]][target[1]] = j.url; changed(); render(); },
        function (j) { alert((j && j.errors && j.errors.image && j.errors.image[0]) || 'Imaginea nu a putut fi încărcată.'); });
  });

  document.querySelectorAll('[data-w]').forEach(function (b) { b.addEventListener('click', function () { frame.style.width = b.dataset.w; }); });
  document.getElementById('preheader').addEventListener('input', function () { changed(); schedule(); });
  document.getElementById('ed-form').addEventListener('submit', function () { document.getElementById('ed-blocks').value = JSON.stringify(blocks); dirty = false; });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  render();
})();
</script>
@endsection
