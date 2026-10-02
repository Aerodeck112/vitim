@extends('layouts.app')
@section('title', $form->name)
@section('content')
@php($slug = $organization->slug)
@php($c = $form->content)
@php($b = $form->behavior)
@php($live = $form->status === 'live')
<div class="head"><div><h1>{{ $form->name }}</h1>
  <p>{{ \App\Models\SignupForm::TYPES[$form->type][0] }} · <span class="badge {{ $live ? 'ok' : '' }}">{{ $live ? 'pe site' : 'ciornă' }}</span>
    · {{ number_format($form->views, 0, ',', '.') }} afișări · {{ number_format($form->submissions, 0, ',', '.') }} înscrieri @if ($form->views) ({{ $form->rate() }}%)@endif</p></div>
  <div style="display:flex;gap:8px">
    <form method="post" action="{{ route('portal.forms.status', [$slug, $form->id]) }}">@csrf<input type="hidden" name="status" value="{{ $live ? 'draft' : 'live' }}"><button class="btn {{ $live ? '' : 'btn-p' }}" type="submit">{{ $live ? 'Oprește' : 'Publică pe site' }}</button></form>
    <form method="post" action="{{ route('portal.forms.destroy', [$slug, $form->id]) }}" onsubmit="return confirm('Ștergi formularul? Contactele strânse rămân.')">@csrf @method('delete')<button class="btn btn-d" type="submit">Șterge</button></form>
  </div></div>
@include('partials.marketing-tabs')
@error('form')<div class="alert alert-err">{{ $message }}</div>@enderror
@if ($form->double_opt_in && ! $hasEmail)<div class="alert alert-warn">Dubla confirmare trimite un email prin contul firmei. <a href="{{ route('portal.channels', $slug) }}#email">Conectează contul de email</a> înainte de publicare.</div>@endif

<div class="grid" style="grid-template-columns:minmax(0,1fr) minmax(0,1.1fr);align-items:start">
<form method="post" action="{{ route('portal.forms.update', [$slug, $form->id]) }}" class="card" id="vf-form">
  @csrf @method('put')
  <div class="fl"><label for="name">Numele formularului (intern)</label><input id="name" type="text" name="name" value="{{ old('name', $form->name) }}" maxlength="120" required></div>

  <h2>Conținut</h2>
  <div class="fl"><label for="title">Titlu</label><input id="title" type="text" name="title" value="{{ $c['title'] ?? '' }}" maxlength="160"></div>
  @if ($form->type !== 'bar')<div class="fl"><label for="text">Text</label><textarea id="text" name="text" rows="3" maxlength="600">{{ $c['text'] ?? '' }}</textarea></div>@endif
  <div class="fl"><label for="button">Textul butonului</label><input id="button" type="text" name="button" value="{{ $c['button'] ?? '' }}" maxlength="40"></div>
  @if ($form->type !== 'bar')
  <div class="lbl">Câmpuri</div>
  <label class="chk"><input type="checkbox" checked disabled> Email (obligatoriu)</label>
  <label class="chk"><input type="checkbox" name="fields[]" value="first_name" @checked(in_array('first_name', $c['fields'] ?? [], true))> Prenume</label>
  <label class="chk"><input type="checkbox" name="fields[]" value="phone" @checked(in_array('phone', $c['fields'] ?? [], true))> Telefon</label>
  <label class="chk" style="margin-left:24px"><input type="checkbox" name="sms" value="1" @checked($c['sms'] ?? false)> cu bifă separată de acord pentru SMS-uri de marketing</label>
  <div class="fl" style="margin-top:10px"><label for="image_url">Imagine (https://…, opțional)</label><input id="image_url" type="url" name="image_url" value="{{ $c['image_url'] ?? '' }}" maxlength="500">@error('image_url')<div class="err">{{ $message }}</div>@enderror
    <div class="hint">La popup apare în stânga, la flyout deasupra. Poți folosi o imagine încărcată în editorul de email.</div></div>
  @endif

  <h2 style="margin-top:18px">Aspect</h2>
  <div class="row">
    <div class="fl"><label for="color">Culoarea butonului</label><input id="color" type="color" name="color" value="{{ $c['color'] ?? '#2f6bff' }}" style="height:38px;padding:2px"></div>
    @if ($form->type !== 'bar')<div class="fl"><label for="background">Fundal</label><input id="background" type="color" name="background" value="{{ $c['background'] ?? '#ffffff' }}" style="height:38px;padding:2px"></div>@endif
    @if (in_array($form->type, ['flyout', 'bar'], true))
      <div class="fl"><label for="position">Poziție</label><select id="position" name="position">
        @foreach ($form->type === 'bar' ? ['top' => 'sus', 'bottom' => 'jos'] : ['right' => 'dreapta jos', 'left' => 'stânga jos'] as $k => $l)<option value="{{ $k }}" @selected(($c['position'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
    @endif
  </div>

  <h2 style="margin-top:18px">După înscriere</h2>
  <div class="fl"><label for="success_title">Titlu</label><input id="success_title" type="text" name="success_title" value="{{ $c['success_title'] ?? '' }}" maxlength="160"></div>
  <div class="fl"><label for="success_text">Text</label><textarea id="success_text" name="success_text" rows="2" maxlength="600">{{ $c['success_text'] ?? '' }}</textarea></div>
  <div class="fl"><label for="coupon">Cod de reducere (opțional)</label><input id="coupon" type="text" name="coupon" value="{{ $c['coupon'] ?? '' }}" maxlength="40" placeholder="ex. BUNVENIT10">
    <div class="hint">Cu dubla confirmare, codul apare după ce persoana confirmă din email; altfel, imediat. Îl poți trimite și în fluxul „Bun venit”.</div></div>
  <div class="fl"><label for="list">Adaugă abonații în lista</label><select id="list" name="contact_list_id"><option value="">— nicio listă —</option>@foreach ($lists as $id => $n)<option value="{{ $id }}" @selected($form->contact_list_id === $id)>{{ $n }}</option>@endforeach</select>
    <div class="hint">Fluxul „Bun venit” pornește singur la abonare (evenimentul „S-a abonat”). @if ($lists->isEmpty())<a href="{{ route('portal.audience', $slug) }}">Creează o listă</a>@endif</div></div>
  <label class="chk"><input type="checkbox" name="double_opt_in" value="1" @checked($form->double_opt_in)> <span><strong>Dublă confirmare</strong> (recomandat): abonarea e valabilă după click pe linkul din emailul de confirmare. Lista rămâne curată și ai dovada acordului.</span></label>

  @if ($form->type !== 'embed')
  <h2 style="margin-top:18px">Când și unde apare</h2>
  <div class="row">
    <div class="fl"><label for="trigger">Apare</label><select id="trigger" name="trigger">
      @foreach (['immediate' => 'imediat', 'delay' => 'după câteva secunde', 'scroll' => 'după ce derulează pagina', 'exit' => 'când vrea să plece (intenție de ieșire)'] as $k => $l)<option value="{{ $k }}" @selected(($b['trigger'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="fl" data-show="delay"><label for="delay">Secunde</label><input id="delay" type="number" name="delay" min="0" max="600" value="{{ $b['delay'] ?? 6 }}"></div>
    <div class="fl" data-show="scroll"><label for="scroll">Procent din pagină</label><input id="scroll" type="number" name="scroll" min="5" max="100" value="{{ $b['scroll'] ?? 40 }}"></div>
  </div>
  <div class="row">
    <div class="fl"><label for="devices">Dispozitive</label><select id="devices" name="devices">@foreach (['all' => 'toate', 'desktop' => 'doar calculator', 'mobile' => 'doar telefon'] as $k => $l)<option value="{{ $k }}" @selected(($b['devices'] ?? 'all') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="fl"><label for="frequency_days">După închidere, nu mai apare</label><div style="display:flex;gap:6px;align-items:center"><input id="frequency_days" type="number" name="frequency_days" min="0" max="365" value="{{ $b['frequency_days'] ?? 7 }}" style="width:90px"> zile</div></div>
  </div>
  <div class="row">
    <div class="fl"><label for="include">Doar pe paginile care conțin</label><textarea id="include" name="include" rows="2" placeholder="/blog&#10;/produse">{{ $b['include'] ?? '' }}</textarea><div class="hint">Câte una pe rând; gol = toate paginile.</div></div>
    <div class="fl"><label for="exclude">Nu pe paginile care conțin</label><textarea id="exclude" name="exclude" rows="2" placeholder="/cos&#10;/checkout">{{ $b['exclude'] ?? '' }}</textarea></div>
  </div>
  @endif
  @if ($sites->count() > 1)
    <div class="fl"><label for="site_id">Pe site-ul</label><select id="site_id" name="site_id"><option value="">toate site-urile firmei</option>@foreach ($sites as $id => $d)<option value="{{ $id }}" @selected($form->site_id === $id)>{{ $d }}</option>@endforeach</select></div>
  @endif
  <button class="btn btn-p" type="submit">Salvează</button>
</form>

<div style="position:sticky;top:12px">
  <div class="card">
    <h2>Previzualizare</h2>
    <div class="ed-device" style="margin-top:0"><button type="button" class="btn btn-s" data-st="confirm">Formular</button><button type="button" class="btn btn-s" data-st="success">După înscriere</button></div>
    <div class="vf-stage {{ $form->type === 'embed' ? 'vf-emb' : '' }}" id="vf-stage">@if ($form->type !== 'embed')<div class="vf-browser"><i></i><i></i><i></i></div>@endif<div id="vf-box"></div></div>
    <p class="small muted" style="margin-bottom:0">Așa apare pe site (fără să salvezi). Contorul de afișări numără doar vizitatorii reali.</p>
  </div>
  <div class="card">
    <h2>Pe site</h2>
    @if ($form->type === 'embed')
      <p class="small">Pune acest cod în pagina unde vrei formularul (de exemplu într-un bloc „HTML personalizat” din WordPress):</p>
      <pre class="mono small" style="white-space:pre-wrap;background:var(--panel-2);padding:10px;border-radius:8px;user-select:all">&lt;div data-vitim-form="{{ $form->id }}"&gt;&lt;/div&gt;</pre>
    @endif
    <p class="small muted" style="margin-bottom:0">Formularul apare pe site-urile cu pluginul VITIM Connector sau cu scriptul VITIM instalat, cât timp e publicat. Contactele care s-au abonat deja nu îl mai văd.</p>
  </div>
</div>
</div>

<div class="card">
  <h2>Înscrieri</h2>
  @if ($submissions->isEmpty())<p class="muted small">Încă nu s-a înscris nimeni.</p>@else
  <div class="table-wrap"><table>
    <thead><tr><th>Contact</th><th>Email</th><th>Când</th><th>Confirmat</th><th>Pagina</th></tr></thead>
    <tbody>
    @foreach ($submissions as $s)
      <tr><td>@if ($s->contact)<a href="{{ route('portal.contacts.show', [$slug, $s->contact_id]) }}">{{ $s->contact->displayName() }}</a>@else — @endif</td>
        <td class="small mono">{{ $s->data['email'] ?? '' }}@if (! empty($s->data['sms'])) · SMS ✓@endif</td>
        <td class="small muted">{{ $s->created_at->setTimezone('Europe/Bucharest')->format('d.m.Y H:i') }}</td>
        <td>@if ($s->confirmed_at)<span class="badge ok">da</span>@else<span class="badge warn">așteaptă</span>@endif</td>
        <td class="small muted" style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $s->page }}</td></tr>
    @endforeach
    </tbody>
  </table></div>
  @include('partials.pager', ['paginator' => $submissions])
  @endif
</div>

<script src="{{ asset('widget/v1/forms.js') }}?v=1"></script>
<script>
(function () {
  var base = @json($payload), form = document.getElementById('vf-form'), box = document.getElementById('vf-box'), status = 'confirm';
  function val(n) { var el = form.elements[n]; return el ? el.value : undefined; }
  function payload() {
    var p = JSON.parse(JSON.stringify(base)), c = p.content;
    ['title', 'text', 'button', 'image_url', 'color', 'background', 'position', 'success_title', 'success_text', 'coupon'].forEach(function (k) { if (val(k) !== undefined) c[k] = val(k); });
    c.fields = ['email'].concat(Array.prototype.slice.call(form.querySelectorAll('[name="fields[]"]:checked')).map(function (e) { return e.value; }));
    c.sms = !!(form.elements.sms && form.elements.sms.checked);
    if (!/^https:\/\//.test(c.image_url || '')) c.image_url = null;
    return p;
  }
  function draw() {
    var p = payload(), host = window.VitimForms.preview(p, box, form.elements.double_opt_in.checked ? 'confirm' : 'subscribed');
    if (status === 'success') { var r = host.shadowRoot || host, f = r.querySelector('form'); r.querySelector('[name=email]').value = 'maria@exemplu.ro'; f.requestSubmit ? f.requestSubmit() : f.dispatchEvent(new Event('submit', {cancelable: true})); }
  }
  function toggle() { var t = val('trigger'); document.querySelectorAll('[data-show]').forEach(function (e) { e.style.display = e.dataset.show === t ? '' : 'none'; }); }
  var timer; form.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(draw, 200); toggle(); });
  form.addEventListener('change', function () { draw(); toggle(); });
  document.querySelectorAll('[data-st]').forEach(function (b) { b.addEventListener('click', function () { status = b.dataset.st; draw(); }); });
  toggle(); draw();
})();
</script>
@endsection
