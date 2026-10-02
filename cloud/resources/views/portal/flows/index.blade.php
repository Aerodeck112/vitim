@extends('layouts.app')
@section('title', 'Automatizări')
@section('content')
@php($slug = $organization->slug)
<div class="head"><div><h1>Automatizări</h1><p>Mesaje care pleacă singure, la momentul potrivit: bun venit, follow-up după cerere, coș abandonat, recâștigare, zi de naștere.</p></div></div>
@include('partials.marketing-tabs')
@if ($flows->isNotEmpty())
<div class="table-wrap" style="margin-bottom:22px"><table>
  <thead><tr><th>Automatizare</th><th>Declanșator</th><th>Stare</th><th>Contacte în flux acum</th></tr></thead>
  <tbody>
  @foreach ($flows as $f)
    <tr><td><a href="{{ route('portal.flows.show', [$slug, $f->id]) }}"><strong>{{ $f->name }}</strong></a></td><td class="small">{{ $f->describeTrigger() }}</td>
      <td><span class="badge {{ ['live' => 'ok', 'paused' => 'warn'][$f->status] ?? '' }}">{{ \App\Models\Flow::STATUSES[$f->status] }}</span></td><td>{{ $active[$f->id] ?? 0 }}</td></tr>
  @endforeach
  </tbody>
</table></div>
@endif
<h2>Pornește de la un șablon</h2>
<div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr))">
  @foreach ($templates as $key => $t)
    <form method="post" action="{{ route('portal.flows.store', $slug) }}" class="card" style="margin:0;display:flex;flex-direction:column">@csrf
      <input type="hidden" name="template" value="{{ $key }}">
      <strong>{{ $t['name'] }}</strong> @if ($t['shop'] ?? false)<span class="badge" style="align-self:flex-start;margin-top:4px">magazin WooCommerce</span>@endif
      <p class="small muted" style="flex:1">{{ $t['description'] }}</p>
      <button class="btn" type="submit">Folosește</button>
    </form>
  @endforeach
  <form method="post" action="{{ route('portal.flows.store', $slug) }}" class="card" style="margin:0;display:flex;flex-direction:column">@csrf
    <strong>De la zero</strong><p class="small muted" style="flex:1">Alegi tu declanșatorul și pașii.</p>
    <input type="text" name="name" placeholder="Numele automatizării" maxlength="160" style="margin-bottom:8px"><button class="btn" type="submit">Creează</button>
  </form>
</div>
@endsection
