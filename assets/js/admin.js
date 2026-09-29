/* VITIM — panou de control */
(function () {
  'use strict';
  var d = document, root = d.documentElement;
  var CSRF = (d.querySelector('meta[name=csrf]') || {}).content || '';
  var BASE = (d.querySelector('meta[name=base]') || {}).content || '';
  function $(s, c) { return (c || d).querySelector(s); }
  function $$(s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); }
  function post(url, data) {
    var fd = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData) && data) Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    fd.append('_csrf', CSRF);
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-Token': CSRF } })
      .then(function (r) { return r.json(); });
  }
  window.vpost = post;
  function slugify(t) {
    var map = { 'ă': 'a', 'â': 'a', 'î': 'i', 'ș': 's', 'ş': 's', 'ț': 't', 'ţ': 't' };
    return t.toLowerCase().replace(/[ăâîșşțţ]/g, function (c) { return map[c]; })
      .normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  }

  /* Temă */
  $$('[data-theme-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', t);
      try { localStorage.setItem('admin-theme', t); } catch (e) {}
    });
  });
  $$('[data-side-toggle]').forEach(function (b) { b.addEventListener('click', function () { $('.side').classList.toggle('open'); }); });

  /* Confirmări */
  d.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.dataset.confirm && !confirm(f.dataset.confirm)) e.preventDefault();
  });
  d.addEventListener('click', function (e) {
    var a = e.target.closest('[data-confirm]');
    if (a && a.tagName === 'A' && !confirm(a.dataset.confirm)) e.preventDefault();
  });

  /* Contor caractere (titlu SEO ≤ 60, descriere ≤ 160) */
  $$('[data-count]').forEach(function (inp) {
    var max = +inp.dataset.count, lab = inp.closest('.fl') && $('label', inp.closest('.fl'));
    if (!lab) return;
    var c = d.createElement('span'); c.className = 'count'; lab.appendChild(c);
    function upd() { var n = inp.value.length; c.textContent = n + ' / ' + max; c.classList.toggle('over', n > max); }
    inp.addEventListener('input', upd); upd();
  });

  /* Slug automat */
  $$('[data-slug-from]').forEach(function (slug) {
    var src = $('[name="' + slug.dataset.slugFrom + '"]');
    var touched = slug.value !== '';
    slug.addEventListener('input', function () { touched = true; });
    if (src) src.addEventListener('input', function () { if (!touched) slug.value = slugify(src.value); });
  });

  /* Previzualizare Google */
  $$('[data-serp]').forEach(function (box) {
    var t = $(box.dataset.title), dsc = $(box.dataset.desc), fbT = $(box.dataset.fbTitle || '#none'), fbD = $(box.dataset.fbDesc || '#none');
    function upd() {
      $('.t', box).textContent = (t && t.value) || (fbT && fbT.value) || 'Titlul paginii';
      $('.d', box).textContent = ((dsc && dsc.value) || (fbD && (fbD.value || fbD.textContent)) || 'Descrierea care apare în Google…').slice(0, 165);
    }
    [t, dsc, fbT, fbD].forEach(function (x) { if (x) x.addEventListener('input', upd); });
    upd();
  });

  /* Editor text (fără dependențe) */
  $$('textarea[data-rte]').forEach(function (ta) {
    var wrap = d.createElement('div'); wrap.className = 'rte';
    var bar = d.createElement('div'); bar.className = 'rte-bar';
    var area = d.createElement('div'); area.className = 'rte-area'; area.contentEditable = 'true'; area.innerHTML = ta.value;
    ta.parentNode.insertBefore(wrap, ta); wrap.appendChild(bar); wrap.appendChild(area); wrap.appendChild(ta);
    ta.classList.add('rte-src'); ta.removeAttribute('data-rte');
    var tools = [
      ['P', 'formatBlock', 'P', 'Paragraf'], ['H2', 'formatBlock', 'H2', 'Titlu secțiune'], ['H3', 'formatBlock', 'H3', 'Subtitlu'], '|',
      ['<b>B</b>', 'bold', null, 'Îngroșat'], ['<i>I</i>', 'italic', null, 'Cursiv'], ['• Listă', 'insertUnorderedList', null, 'Listă'], ['1. Listă', 'insertOrderedList', null, 'Listă numerotată'], '|',
      ['❝', 'formatBlock', 'BLOCKQUOTE', 'Citat'], ['Casetă', 'callout', null, 'Casetă evidențiată'], ['Link', 'link', null, 'Link'], ['Imagine', 'image', null, 'Imagine din bibliotecă'], ['—', 'insertHorizontalRule', null, 'Linie'], '|',
      ['Curăță', 'removeFormat', null, 'Elimină formatarea'], ['&lt;/&gt; HTML', 'source', null, 'Editează HTML']
    ];
    tools.forEach(function (t) {
      if (t === '|') { var s = d.createElement('span'); s.className = 'sep'; bar.appendChild(s); return; }
      var b = d.createElement('button'); b.type = 'button'; b.innerHTML = t[0]; b.title = t[3];
      b.addEventListener('mousedown', function (e) { e.preventDefault(); });
      b.addEventListener('click', function () {
        if (t[1] === 'source') { if (wrap.classList.contains('src')) area.innerHTML = ta.value; else ta.value = area.innerHTML; wrap.classList.toggle('src'); return; }
        area.focus();
        if (t[1] === 'link') { var u = prompt('Adresa linkului (ex: /servicii/seo sau https://...)'); if (u) d.execCommand('createLink', false, u); }
        else if (t[1] === 'image') { openMedia(function (m) { area.focus(); d.execCommand('insertHTML', false, '<img src="' + m.url + '" alt="' + (m.alt || '').replace(/"/g, '&quot;') + '" width="' + m.width + '" height="' + m.height + '" loading="lazy">'); }); }
        else if (t[1] === 'callout') { var sel = window.getSelection().toString() || 'Text evidențiat'; d.execCommand('insertHTML', false, '<div class="callout">' + sel + '</div><p></p>'); }
        else d.execCommand(t[1], false, t[2] ? '<' + t[2] + '>' : null);
        sync();
      });
      bar.appendChild(b);
    });
    function sync() { if (!wrap.classList.contains('src')) ta.value = area.innerHTML; }
    area.addEventListener('input', sync);
    area.addEventListener('paste', function (e) {
      // lipire curată: păstrăm doar structura de bază
      var html = (e.clipboardData || window.clipboardData).getData('text/html');
      if (!html) return;
      e.preventDefault();
      var tmp = d.createElement('div'); tmp.innerHTML = html;
      $$('*', tmp).forEach(function (el) { ['style', 'class', 'id'].forEach(function (a) { el.removeAttribute(a); }); if (/^(SPAN|FONT)$/.test(el.tagName)) { while (el.firstChild) el.parentNode.insertBefore(el.firstChild, el); el.remove(); } });
      $$('script,style,meta,link', tmp).forEach(function (el) { el.remove(); });
      d.execCommand('insertHTML', false, tmp.innerHTML); sync();
    });
    var form = ta.closest('form'); if (form) form.addEventListener('submit', sync);
  });

  /* Repeater (liste de elemente: FAQ, beneficii, pași) */
  $$('[data-repeater]').forEach(function (box) {
    var input = $('input[type=hidden]', box), list = $('.rep-list', box), fields = JSON.parse(box.dataset.fields);
    var items = []; try { items = JSON.parse(input.value || '[]') || []; } catch (e) { items = []; }
    function render() {
      list.innerHTML = '';
      items.forEach(function (it, i) {
        var row = d.createElement('div'); row.className = 'rep-item';
        fields.forEach(function (f) {
          var el;
          if (f.type === 'textarea') { el = d.createElement('textarea'); el.rows = 3; }
          else if (f.type === 'select') { el = d.createElement('select'); (f.options || []).forEach(function (o) { var op = d.createElement('option'); op.value = o; op.textContent = o; el.appendChild(op); }); }
          else { el = d.createElement('input'); el.type = 'text'; }
          el.className = 'in'; el.placeholder = f.label; el.value = it[f.key] || '';
          el.addEventListener('input', function () { items[i][f.key] = el.value; save(); });
          row.appendChild(el);
        });
        var tools = d.createElement('div'); tools.className = 'rep-tools';
        [['↑', -1], ['↓', 1], ['✕', 0]].forEach(function (t) {
          var b = d.createElement('button'); b.type = 'button'; b.className = 'btn btn-xs'; b.textContent = t[0];
          b.addEventListener('click', function () {
            if (t[1] === 0) { if (confirm('Ștergi elementul?')) items.splice(i, 1); }
            else { var j = i + t[1]; if (j < 0 || j >= items.length) return; var tmp = items[i]; items[i] = items[j]; items[j] = tmp; }
            save(); render();
          });
          tools.appendChild(b);
        });
        row.appendChild(tools);
        list.appendChild(row);
      });
    }
    function save() { input.value = JSON.stringify(items); }
    $('[data-rep-add]', box).addEventListener('click', function () { var o = {}; fields.forEach(function (f) { o[f.key] = ''; }); items.push(o); save(); render(); });
    render();
  });

  /* Bibliotecă media */
  var dlg = $('#media-dialog'), mediaCb = null;
  function openMedia(cb) {
    if (!dlg) return;
    mediaCb = cb;
    var grid = $('.media-grid', dlg); grid.innerHTML = '<p class="muted">Se încarcă…</p>';
    fetch(BASE + '/admin/media/json', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (list) {
      grid.innerHTML = list.length ? '' : '<p class="muted">Nicio imagine încă. Încarcă prima imagine mai sus.</p>';
      list.forEach(function (m) {
        var it = d.createElement('div'); it.className = 'media-item';
        it.innerHTML = '<img loading="lazy" src="' + m.thumb + '" alt=""><div class="meta">' + (m.name || '') + '</div>';
        it.addEventListener('click', function () { dlg.close(); if (mediaCb) mediaCb(m); });
        grid.appendChild(it);
      });
    });
    dlg.showModal();
  }
  window.openMedia = openMedia;
  if (dlg) {
    var up = $('input[type=file]', dlg);
    up.addEventListener('change', function () {
      if (!up.files.length) return;
      var fd = new FormData(); Array.prototype.forEach.call(up.files, function (f) { fd.append('files[]', f); });
      $('.up-status', dlg).textContent = 'Se încarcă…';
      post(BASE + '/admin/media/upload', fd).then(function (j) { $('.up-status', dlg).textContent = j.message || ''; up.value = ''; openMedia(mediaCb); });
    });
    $$('[data-close]', dlg).forEach(function (b) { b.addEventListener('click', function () { dlg.close(); }); });
  }
  $$('[data-image-field]').forEach(function (f) {
    var input = $('input[type=hidden]', f), prev = $('.prev', f);
    $('[data-pick]', f).addEventListener('click', function () { openMedia(function (m) { input.value = m.path; prev.style.backgroundImage = 'url(' + m.thumb + ')'; }); });
    $('[data-clear]', f).addEventListener('click', function () { input.value = ''; prev.style.backgroundImage = ''; });
  });

  /* Pagina Media: încărcare prin drag & drop */
  var drop = $('[data-drop]');
  if (drop) {
    var fi = $('input[type=file]', drop.parentNode);
    drop.addEventListener('click', function () { fi.click(); });
    ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('over'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('over'); }); });
    function send(files) {
      var fd = new FormData(); Array.prototype.forEach.call(files, function (f) { fd.append('files[]', f); });
      drop.textContent = 'Se încarcă ' + files.length + ' fișier(e)…';
      post(BASE + '/admin/media/upload', fd).then(function (j) { alert(j.message); location.reload(); });
    }
    drop.addEventListener('drop', function (e) { if (e.dataTransfer.files.length) send(e.dataTransfer.files); });
    fi.addEventListener('change', function () { if (fi.files.length) send(fi.files); });
  }

  /* Kanban oportunități */
  var dragged = null;
  $$('.deal[draggable]').forEach(function (el) {
    el.addEventListener('dragstart', function () { dragged = el; el.classList.add('drag'); });
    el.addEventListener('dragend', function () { el.classList.remove('drag'); });
  });
  $$('.col[data-stage]').forEach(function (col) {
    col.addEventListener('dragover', function (e) { e.preventDefault(); col.classList.add('over'); });
    col.addEventListener('dragleave', function () { col.classList.remove('over'); });
    col.addEventListener('drop', function (e) {
      e.preventDefault(); col.classList.remove('over');
      if (!dragged) return;
      $('.col-body', col).appendChild(dragged);
      post(BASE + '/admin/crm/oportunitati/' + dragged.dataset.id + '/etapa', { stage: col.dataset.stage }).then(function (j) {
        if (!j.ok) { alert(j.message || 'Eroare'); location.reload(); }
        $$('.col[data-stage]').forEach(function (c) { $('.col-count', c).textContent = $$('.deal', c).length; });
      });
    });
  });

  /* Audiență campanie: numărare live */
  var aud = $('[data-audience]');
  if (aud) {
    var out = $('[data-audience-count]');
    var recount = function () {
      var fd = new FormData(aud.closest('form'));
      out.textContent = '…';
      post(BASE + '/admin/email/audienta', fd).then(function (j) { out.textContent = j.count; });
    };
    $$('input,select', aud).forEach(function (i) { i.addEventListener('change', recount); });
    var kind = $('[name=kind]'); if (kind) kind.addEventListener('change', recount);
    recount();
  }

  /* Trimitere campanie în loturi (fără cron) */
  var sender = $('[data-sender]');
  if (sender) {
    var bar = $('.bar i', sender), stat = $('[data-sender-status]', sender), btn = $('[data-sender-start]', sender), running = false;
    function step() {
      if (!running) return;
      post(sender.dataset.url, {}).then(function (j) {
        var done = (j.total || 0) - (j.left || 0);
        if (j.total) bar.style.width = Math.round(done * 100 / j.total) + '%';
        stat.textContent = j.message || ('Trimise ' + done + ' din ' + j.total + (j.failed ? ' · eșuate: ' + j.failed : ''));
        if (j.done) { running = false; stat.textContent = 'Gata! Campania a fost trimisă.'; setTimeout(function () { location.reload(); }, 1200); return; }
        setTimeout(step, j.wait ? 60000 : 1500);
      }).catch(function () { stat.textContent = 'Eroare de rețea – reîncerc în 10 secunde…'; setTimeout(step, 10000); });
    }
    if (btn) btn.addEventListener('click', function () { running = !running; btn.textContent = running ? 'Pauză' : 'Continuă trimiterea'; if (running) step(); });
    if (sender.dataset.autostart === '1' && btn) btn.click();
  }

  /* QR pentru 2FA */
  var qr = $('[data-qr]');
  if (qr && window.qrcode) {
    var q = window.qrcode(0, 'M'); q.addData(qr.dataset.qr); q.make();
    qr.innerHTML = q.createSvgTag({ cellSize: 5, margin: 2, scalable: true });
    var svg = $('svg', qr); if (svg) { svg.style.width = '200px'; svg.style.height = '200px'; svg.style.background = '#fff'; svg.style.borderRadius = '12px'; }
  }

  /* Copiere în clipboard */
  $$('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = $(b.dataset.copy); if (!t) return;
      navigator.clipboard.writeText(t.textContent.trim()).then(function () { var o = b.textContent; b.textContent = 'Copiat ✓'; setTimeout(function () { b.textContent = o; }, 1500); });
    });
  });

  /* Selectare tot */
  $$('[data-check-all]').forEach(function (c) { c.addEventListener('change', function () { $$(c.dataset.checkAll).forEach(function (x) { x.checked = c.checked; }); }); });
})();
