@extends('layouts.app')
@section('title', $segment->exists ? $segment->name : 'Segment nou')
@section('content')
@php($def = $segment->definition)
@php($slug = $organization->slug)
<div class="head"><div><h1>{{ $segment->exists ? $segment->name : 'Segment nou' }}</h1><p>Contactele intră și ies singure din segment, după condiții. Îl folosești în campanii și în automatizări.</p></div>
  @if ($segment->exists)<form method="post" action="{{ route('portal.audience.segment.destroy', [$slug, $segment->id]) }}" onsubmit="return confirm('Ștergi segmentul?')">@csrf @method('delete')<button class="btn btn-d" type="submit">Șterge</button></form>@endif</div>
@include('partials.marketing-tabs')
<div class="grid" style="grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);align-items:start">
<form method="post" action="{{ $segment->exists ? route('portal.audience.segment.update', [$slug, $segment->id]) : route('portal.audience.segment.store', $slug) }}" class="card" id="seg">
  @csrf @if ($segment->exists) @method('put') @endif
  <div class="fl"><label for="name">Numele segmentului</label><input id="name" type="text" name="name" value="{{ old('name', $segment->name) }}" required maxlength="120" placeholder="ex. Implicați în ultimele 30 de zile"></div>
  <div class="fl"><label>Contactele care îndeplinesc</label><select name="definition[match]" style="width:auto"><option value="all" @selected(($def['match'] ?? 'all') === 'all')>toate condițiile (ȘI)</option><option value="any" @selected(($def['match'] ?? '') === 'any')>oricare condiție (SAU)</option></select></div>
  <div id="rows"></div>
  <button class="btn" type="button" id="add">+ Adaugă o condiție</button>
  <div style="margin-top:16px"><button class="btn btn-p" type="submit">Salvează și numără</button></div>
  <template id="row-tpl">
    <div class="cond" style="border:1px solid var(--border);border-radius:10px;padding:10px;margin-bottom:10px;display:flex;flex-wrap:wrap;gap:8px;align-items:center">
      <select data-k="type" aria-label="Tip condiție">
        <option value="consent">Acord de marketing</option><option value="event">Ce a făcut</option><option value="property">Date din profil</option>
        <option value="list">Listă</option><option value="lead">Cereri</option><option value="revenue">Bani cheltuiți</option><option value="prediction">Predicții</option></select>
      <span data-for="consent"><select data-k="op"><option value="granted">are acord pe</option><option value="not_granted">nu are acord pe</option></select>
        <select data-k="channel"><option value="email">Email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option></select></span>
      <span data-for="event"><select data-k="event">@foreach ($events as $k => [$label])<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
        <select data-k="op"><option value="at_least">de cel puțin</option><option value="zero">niciodată</option></select>
        <input data-k="count" type="number" min="1" value="1" style="width:70px"> ori, în ultimele <input data-k="days" type="number" min="0" placeholder="oricând" style="width:90px"> zile
        <select data-k="campaign_id"><option value="">orice campanie</option>@foreach ($campaigns as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></span>
      <span data-for="property"><select data-k="field">@foreach (\App\Services\SegmentQuery::FIELDS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
        <select data-k="op">@foreach (\App\Services\SegmentQuery::TEXT_OPS + \App\Services\SegmentQuery::DATE_OPS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
        <input data-k="value" type="text" placeholder="valoare / zile / AAAA-LL-ZZ" style="width:180px"></span>
      <span data-for="list"><select data-k="op"><option value="in">e în lista</option><option value="not_in">nu e în lista</option></select>
        <select data-k="list_id">@foreach ($lists as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></span>
      <span data-for="lead"><select data-k="op"><option value="has">a trimis cel puțin o cerere</option><option value="none">nu a trimis nicio cerere</option></select></span>
      <span data-for="prediction"><select data-k="pick"><option value="churn_high">risc de pierdere mare</option><option value="churn_medium">risc de pierdere mediu</option><option value="churn_low">risc de pierdere mic</option>
        <option value="clv_at_least">valoare estimată de cel puțin (lei)</option><option value="clv_less_than">valoare estimată sub (lei)</option><option value="next_order">următoarea comandă estimată în (zile)</option></select>
        <input data-k="value" type="number" min="0" step="1" style="width:110px" placeholder="valoare"></span>
      <span data-for="revenue"><select data-k="op"><option value="at_least">cel puțin</option><option value="less_than">mai puțin de</option></select>
        <input data-k="value" type="number" min="0" step="1" style="width:110px"> lei, în ultimele <input data-k="days" type="number" min="0" placeholder="oricând" style="width:90px"> zile</span>
      <button class="btn btn-s btn-d" type="button" data-remove aria-label="Șterge condiția">×</button>
    </div>
  </template>
</form>
<div class="card">
  <h2>{{ number_format($count, 0, ',', '.') }} contacte</h2>
  <p class="small muted" style="margin-top:-6px">{{ \App\Services\SegmentQuery::describe($def, $lists->all()) }}</p>
  @foreach ($sample as $c)<div style="padding:6px 0;border-bottom:1px solid var(--border)"><a href="{{ route('portal.contacts.show', [$slug, $c->id]) }}">{{ $c->displayName() }}</a> <span class="small muted">{{ $c->email ?? $c->phone }}</span></div>@endforeach
  @if ($count > 20)<p class="small muted">… și încă {{ number_format($count - 20, 0, ',', '.') }}.</p>@endif
</div>
</div>
<script>
(function () {
  var rows = document.getElementById('rows'), tpl = document.getElementById('row-tpl'), n = 0;
  function sync(row) {
    var type = row.querySelector('[data-k=type]').value;
    row.querySelectorAll('[data-for]').forEach(function (s) {
      var on = s.dataset.for === type; s.style.display = on ? '' : 'none';
      s.querySelectorAll('[data-k]').forEach(function (f) { f.disabled = !on; });
    });
  }
  function add(values) {
    var row = tpl.content.firstElementChild.cloneNode(true), i = n++;
    row.querySelectorAll('[data-k]').forEach(function (f) {
      f.name = 'definition[conditions][' + i + '][' + f.dataset.k + ']';
      if (values && values[f.dataset.k] !== undefined && (f.dataset.k === 'type' || f.closest('[data-for]').dataset.for === values.type)) f.value = values[f.dataset.k];
    });
    row.querySelector('[data-k=type]').addEventListener('change', function () { sync(row); });
    row.querySelector('[data-remove]').addEventListener('click', function () { row.remove(); });
    rows.appendChild(row); sync(row);
  }
  (@json($def['conditions'] ?? [])).forEach(add);
  if (!rows.children.length) add({ type: 'consent' });
  document.getElementById('add').addEventListener('click', function () { add({ type: 'event' }); });
})();
</script>
@endsection
