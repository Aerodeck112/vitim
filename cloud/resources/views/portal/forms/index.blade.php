@extends('layouts.app')
@section('title', 'Formulare de abonare')
@section('content')
@php($slug = $organization->slug)
<div class="head"><div><h1>Formulare de abonare</h1><p>Strânge abonați de pe site: popup, casetă în colț, bară sau formular în pagină. Contactele intră direct în Audiență, cu acordul înregistrat.</p></div></div>
@include('partials.marketing-tabs')
@if ($forms->isNotEmpty())
<div class="grid" style="margin-bottom:18px">
  <div class="kpi"><small>Formulare active</small><b>{{ $forms->where('status', 'live')->count() }}</b></div>
  <div class="kpi"><small>Înscrieri în ultimele 7 zile</small><b>{{ number_format($week, 0, ',', '.') }}</b></div>
  <div class="kpi"><small>Afișări (total)</small><b>{{ number_format($forms->sum('views'), 0, ',', '.') }}</b></div>
</div>
<div class="table-wrap" style="margin-bottom:22px"><table>
  <thead><tr><th>Formular</th><th>Tip</th><th>Stare</th><th>Afișări</th><th>Înscrieri</th><th>Conversie</th><th>Listă</th></tr></thead>
  <tbody>
  @foreach ($forms as $f)
    <tr><td><a href="{{ route('portal.forms.show', [$slug, $f->id]) }}"><strong>{{ $f->name }}</strong></a></td><td>{{ $types[$f->type][0] }}</td>
      <td><span class="badge {{ $f->status === 'live' ? 'ok' : '' }}">{{ $f->status === 'live' ? 'pe site' : 'ciornă' }}</span></td>
      <td>{{ number_format($f->views, 0, ',', '.') }}</td><td>{{ number_format($f->submissions, 0, ',', '.') }}</td><td>{{ $f->type === 'embed' && ! $f->views ? '—' : $f->rate().'%' }}</td>
      <td class="small">{{ $f->list?->name ?? '—' }}</td></tr>
  @endforeach
  </tbody>
</table></div>
@endif
<h2>Formular nou</h2>
<div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr))">
  @foreach ($types as $key => [$label, $desc])
    <form method="post" action="{{ route('portal.forms.store', $slug) }}" class="card" style="margin:0;display:flex;flex-direction:column">@csrf
      <input type="hidden" name="type" value="{{ $key }}">
      <div class="vf-icon vf-{{ $key }}" aria-hidden="true"><span></span></div>
      <strong>{{ $label }}</strong><p class="small muted" style="flex:1">{{ $desc }}</p>
      <button class="btn" type="submit">Creează</button>
    </form>
  @endforeach
</div>
@endsection
