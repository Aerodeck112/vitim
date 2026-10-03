@extends('layouts.app')
@section('title', $flow->name)
@section('content')
@php($slug = $organization->slug)
@php($t = (array) $flow->trigger)
<div class="head"><div><h1>{{ $flow->name }}</h1>
  <p><span class="badge {{ ['live' => 'ok', 'paused' => 'warn'][$flow->status] ?? '' }}">{{ \App\Models\Flow::STATUSES[$flow->status] }}</span> · {{ $flow->describeTrigger($lists->all(), $segments->all()) }}
    · în flux acum: <strong>{{ $stats['runs']['active'] ?? 0 }}</strong> · au terminat: {{ $stats['runs']['completed'] ?? 0 }} · au ieșit: {{ $stats['runs']['exited'] ?? 0 }}@if (($revenue['orders'] ?? 0) > 0) · venit atribuit: <strong>{{ number_format($revenue['revenue'], 2, ',', '.') }} lei</strong> ({{ $revenue['orders'] }} comenzi)@endif</p></div>
  <div style="display:flex;gap:8px">
    <form method="post" action="{{ route('portal.flows.status', [$slug, $flow->id]) }}">@csrf<input type="hidden" name="status" value="{{ $flow->status === 'live' ? 'paused' : 'live' }}">
      <button class="btn {{ $flow->status === 'live' ? '' : 'btn-p' }}" type="submit">{{ $flow->status === 'live' ? 'Oprește' : 'Pornește automatizarea' }}</button></form>
    @if ($flow->status !== 'live')<form method="post" action="{{ route('portal.flows.destroy', [$slug, $flow->id]) }}" onsubmit="return confirm('Ștergi automatizarea?')">@csrf @method('delete')<button class="btn btn-d" type="submit">Șterge</button></form>@endif
  </div></div>
@include('partials.marketing-tabs')
@error('flow')<div class="alert alert-err">{{ $message }}</div>@enderror

<div class="grid" style="grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);align-items:start">
<div class="card flow-canvas">
  <div class="step step-trigger"><span class="step-type">Declanșator</span> <span class="step-sum">{{ $flow->describeTrigger($lists->all(), $segments->all()) }}</span></div>
  @include('portal.flows.sequence', ['parent' => null, 'branch' => null])
</div>

<div>
<form method="post" action="{{ route('portal.flows.update', [$slug, $flow->id]) }}" class="card">@csrf @method('put')
  <h2>Setări</h2>
  <div class="fl"><label for="fn">Nume</label><input id="fn" type="text" name="name" value="{{ $flow->name }}" maxlength="160"></div>
  <div class="fl"><label for="tt">Pornește când</label>
    <select id="tt" name="trigger_type">
      <option value="event" @selected(($t['type'] ?? '') === 'event')>se întâmplă un eveniment</option>
      <option value="list" @selected(($t['type'] ?? '') === 'list')>e adăugat într-o listă</option>
      <option value="segment" @selected(($t['type'] ?? '') === 'segment')>intră într-un segment</option>
      <option value="date" @selected(($t['type'] ?? '') === 'date')>e ziua lui de naștere</option></select></div>
  <div class="fl" data-t="event"><label for="te">Evenimentul</label><select id="te" name="trigger_event">@foreach (\App\Models\Flow::TRIGGER_EVENTS as $e)<option value="{{ $e }}" @selected(($t['event'] ?? '') === $e)>{{ $events[$e][0] }}</option>@endforeach</select></div>
  <div class="fl" data-t="list"><label for="tl">Lista</label><select id="tl" name="trigger_list_id"><option value="">—</option>@foreach ($lists as $id => $n)<option value="{{ $id }}" @selected((int) ($t['list_id'] ?? 0) === $id)>{{ $n }}</option>@endforeach</select></div>
  <div class="fl" data-t="segment"><label for="ts">Segmentul</label><select id="ts" name="trigger_segment_id"><option value="">—</option>@foreach ($segments as $id => $n)<option value="{{ $id }}" @selected((int) ($t['segment_id'] ?? 0) === $id)>{{ $n }}</option>@endforeach</select>
    <div class="hint">Intră doar cei care ajung în segment după pornire.</div></div>
  @error('trigger_type')<div class="err">{{ $message }}</div>@enderror
  <div class="fl"><label>Iese din flux dacă</label>
    @foreach (['placed_order', 'lead_created', 'added_to_cart', 'email_clicked', 'unsubscribed'] as $e)<label class="chk"><input type="checkbox" name="exit_on[]" value="{{ $e }}" @checked(in_array($e, (array) $flow->setting('exit_on', []), true))> {{ mb_strtolower($events[$e][0]) }}</label>@endforeach</div>
  <div class="fl"><label for="ss">Smart sending: nu trimite dacă a primit un mesaj pe același canal în ultimele (ore)</label><input id="ss" type="number" name="smart_sending_hours" min="0" max="168" value="{{ $flow->setting('smart_sending_hours', 16) }}"></div>
  <div class="fl"><label>Poate intra din nou</label>
    <label class="chk"><input type="radio" name="reentry" value="never" @checked($flow->setting('reentry_days') === null)> niciodată (o singură dată pe contact)</label>
    <label class="chk"><input type="radio" name="reentry" value="days" @checked($flow->setting('reentry_days') !== null)> după <input type="number" name="reentry_days" min="0" max="3650" value="{{ $flow->setting('reentry_days', 30) }}" style="width:80px"> zile</label></div>
  <button class="btn btn-p" type="submit">Salvează setările</button>
</form>
<div class="card">
  <h2>Ultimii intrați</h2>
  @forelse ($recent as $run)
    <div class="small" style="padding:5px 0;border-bottom:1px solid var(--border)">@if ($run->contact)<a href="{{ route('portal.contacts.show', [$slug, $run->contact_id]) }}">{{ $run->contact->displayName() }}</a>@endif
      · {{ ['active' => 'în flux', 'completed' => 'a terminat', 'exited' => 'a ieșit', 'failed' => 'eroare'][$run->status] ?? $run->status }}
      <span class="muted">· {{ $run->started_at->setTimezone('Europe/Bucharest')->format('d.m H:i') }}{{ $run->note ? ' · '.$run->note : '' }}</span></div>
  @empty
    <p class="muted small" style="margin:0">Nimeni încă.</p>
  @endforelse
</div>
</div>
</div>
<script>
(function () { var s = document.getElementById('tt'); function f() { document.querySelectorAll('[data-t]').forEach(function (d) { d.style.display = d.dataset.t === s.value ? '' : 'none'; }); } s.addEventListener('change', f); f();
  if (location.hash) { var d = document.querySelector(location.hash + ' > details'); if (d) d.open = true; } })();
</script>
@endsection
