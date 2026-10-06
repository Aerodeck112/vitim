@extends('layouts.app')
@section('title', 'Contacte')
@section('content')
@php
  $canBulk = $canManage && ($canLists || $canConsent);
  $channelNames = ['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'];
@endphp
<div class="head">
  <div><h1>Contacte</h1><p>Clienții și potențialii clienți ai firmei.</p></div>
  @if ($canManage)<div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn" href="{{ route('portal.contacts.import', $organization->slug) }}">Import din Excel / CSV</a><a class="btn btn-p" href="{{ route('portal.contacts.create', $organization->slug) }}">Contact nou</a></div>@endif
</div>
<form method="get" class="toolbar">
  <input type="text" name="q" value="{{ $q }}" placeholder="Caută după nume, email, telefon, firmă" aria-label="Caută">
  <button class="btn" type="submit">Caută</button>
</form>

@if ($canBulk && $contacts->total() > 0)
<form method="post" action="{{ route('portal.contacts.bulk', $organization->slug) }}" id="bulk" class="card bulk" data-bulk>
  @csrf
  <input type="hidden" name="scope" value="selected" data-bulk-scope>
  <input type="hidden" name="q" value="{{ $q }}">
  <div class="bulk-head">
    <strong data-bulk-count>Bifează contactele din tabel</strong>
    <button type="button" class="btn btn-s" data-bulk-all>Selectează toate cele {{ number_format($contacts->total(), 0, ',', '.') }} contacte{{ $q !== '' ? ' găsite' : '' }}</button>
    <button type="button" class="btn btn-s" data-bulk-clear hidden>Renunță la selecție</button>
  </div>
  <div class="bulk-body">
    @if ($canLists)
    <div class="bulk-row">
      <div class="fl"><label for="list_id">Adaugă în lista</label>
        <select id="list_id" name="list_id"><option value="">— alege lista —</option>@foreach ($lists as $id => $n)<option value="{{ $id }}" @selected((string) old('list_id') === (string) $id)>{{ $n }}</option>@endforeach</select></div>
      <div class="fl"><label for="new_list">sau creează o listă nouă</label><input id="new_list" type="text" name="new_list" maxlength="120" value="{{ old('new_list') }}" placeholder="ex. Clienți 2026"></div>
    </div>
    @endif
    @if ($canConsent)
    <details class="bulk-consent" @if (old('consent')) open @endif>
      <summary>Înregistrează și acordul de marketing pentru toate (opțional)</summary>
      <p class="small muted">Campaniile pleacă doar către contactele cu acord pe acel canal. Bifează doar dacă ai acordul lor documentat; nu e nevoie să îl completezi la fiecare contact. Cine a refuzat sau s-a dezabonat rămâne fără acord.</p>
      @foreach ($channelNames as $value => $label)
        <label class="chk"><input type="checkbox" name="consent[]" value="{{ $value }}" @checked(in_array($value, old('consent', []), true))> Au acceptat mesaje de marketing pe {{ $label }}</label>
      @endforeach
      <div class="fl" style="margin-top:8px"><label for="evidence">De unde ai acordul?</label><input id="evidence" type="text" name="evidence" maxlength="300" value="{{ old('evidence') }}" placeholder="ex. clienți care s-au abonat pe site sau la casă, 2024 – prezent"></div>
      <label class="chk"><input type="checkbox" name="declare" value="1"> Confirm că aceste persoane și-au dat acordul și că îl pot dovedi la cerere.</label>
    </details>
    @endif
    <button class="btn btn-p" type="submit" data-bulk-submit>Aplică</button>
  </div>
</form>
@endif

<div class="table-wrap"><table>
  <thead><tr>
    @if ($canBulk)<th class="cb"><input type="checkbox" data-bulk-page aria-label="Bifează toate contactele din pagină"></th>@endif
    <th>Nume</th><th>Email</th><th>Telefon</th><th>Firmă</th><th>Marketing</th><th>Sursă</th><th>Adăugat</th></tr></thead>
  <tbody>
  @forelse ($contacts as $c)
    @php $m = $marketing[$c->id] ?? []; @endphp
    <tr>
      @if ($canBulk)<td class="cb"><input type="checkbox" name="ids[]" value="{{ $c->id }}" form="bulk" data-bulk-id aria-label="Bifează {{ $c->displayName() }}"></td>@endif
      <td><a href="{{ route('portal.contacts.show', [$organization->slug, $c->id]) }}"><strong>{{ $c->displayName() }}</strong></a></td>
      <td>{{ $c->email ?? '—' }}</td><td>{{ $c->phone ?? '—' }}</td><td>{{ $c->company ?? '—' }}</td>
      <td>@php $granted = array_keys(array_filter($m, fn ($s) => $s === \App\Enums\ConsentStatus::Granted)); $revoked = array_keys(array_filter($m, fn ($s) => $s === \App\Enums\ConsentStatus::Revoked)); @endphp
        @forelse ($granted as $ch)<span class="badge ok">{{ $channelNames[$ch] ?? $ch }}</span> @empty @if (! $revoked)<span class="small muted">fără acord</span>@endif @endforelse
        @foreach ($revoked as $ch)<span class="badge err" title="Dezabonat / acord retras">{{ $channelNames[$ch] ?? $ch }} retras</span> @endforeach</td>
      <td><span class="badge">{{ $c->source->value }}</span></td><td class="small muted">{{ $c->created_at->format('d.m.Y') }}</td></tr>
  @empty
    <tr><td colspan="{{ $canBulk ? 8 : 7 }}" class="empty">{{ $q ? 'Niciun rezultat.' : 'Niciun contact încă.' }}</td></tr>
  @endforelse
  </tbody>
</table></div>
@include('partials.pager', ['paginator' => $contacts])

@if ($canBulk)
<script>
(function () {
  var f = document.querySelector('[data-bulk]'); if (!f) return;
  var total = {{ (int) $contacts->total() }}, scope = f.querySelector('[data-bulk-scope]'), count = f.querySelector('[data-bulk-count]');
  var all = f.querySelector('[data-bulk-all]'), clear = f.querySelector('[data-bulk-clear]'), page = document.querySelector('[data-bulk-page]');
  var boxes = Array.prototype.slice.call(document.querySelectorAll('[data-bulk-id]'));
  function fmt(n) { return n.toLocaleString('ro-RO'); }
  function update() {
    var n = boxes.filter(function (b) { return b.checked; }).length;
    if (scope.value === 'all') { count.textContent = 'Toate cele ' + fmt(total) + ' contacte sunt selectate'; }
    else { count.textContent = n ? n + (n === 1 ? ' contact selectat' : ' contacte selectate') : 'Bifează contactele din tabel'; }
    f.classList.toggle('on', scope.value === 'all' || n > 0);
    clear.hidden = !(scope.value === 'all' || n > 0);
    all.hidden = scope.value === 'all' || total <= boxes.length && n === boxes.length;
    if (page) { page.checked = n > 0 && n === boxes.length; page.indeterminate = n > 0 && n < boxes.length; }
  }
  boxes.forEach(function (b) { b.addEventListener('change', function () { if (!b.checked) scope.value = 'selected'; update(); }); });
  if (page) page.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = page.checked; }); if (!page.checked) scope.value = 'selected'; update(); });
  all.addEventListener('click', function () { scope.value = 'all'; boxes.forEach(function (b) { b.checked = true; }); update(); });
  clear.addEventListener('click', function () { scope.value = 'selected'; boxes.forEach(function (b) { b.checked = false; }); update(); });
  f.addEventListener('submit', function (e) {
    var n = scope.value === 'all' ? total : boxes.filter(function (b) { return b.checked; }).length;
    if (!n) { e.preventDefault(); count.textContent = 'Bifează cel puțin un contact'; return; }
    if (n > 25 && !confirm('Aplici pentru ' + fmt(n) + ' contacte?')) e.preventDefault();
  });
  update();
})();
</script>
@endif
@endsection
