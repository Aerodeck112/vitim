@extends('layouts.app')
@section('title', 'Lucrări VITIM')
@section('content')
<div class="head"><div><h1>Lucrări VITIM</h1><p>Ce a făcut echipa VITIM pentru site-urile și sistemele tale.</p></div></div>
<form method="get" class="row" style="max-width:640px;align-items:flex-end">
  <div class="fl"><label for="luna">Luna</label><select id="luna" name="luna" onchange="this.form.submit()"><option value="">Toate</option>@foreach ($months as $m)<option value="{{ $m }}" @selected($month === $m)>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $m)->locale('ro')->translatedFormat('F Y') }}</option>@endforeach</select></div>
  <div class="fl"><label for="site">Site</label><select id="site" name="site" onchange="this.form.submit()"><option value="">Toate</option>@foreach ($sites as $s)<option value="{{ $s->id }}" @selected($siteId === $s->id)>{{ $s->domain }}</option>@endforeach</select></div>
  <noscript><button class="btn" type="submit">Filtrează</button></noscript>
</form>
<div class="grid" style="margin-bottom:18px">
  <div class="kpi"><small>Lucrări</small><b>{{ $total }}</b></div>
  <div class="kpi"><small>Timp lucrat</small><b>{{ intdiv($minutes, 60) }} h {{ $minutes % 60 }} min</b></div>
</div>
<div class="table-wrap"><table>
  <thead><tr><th>Data</th><th>Tip</th><th>Lucrare</th><th>Site</th><th>Durată</th></tr></thead>
  <tbody>
  @forelse ($logs as $log)
    <tr><td class="small" style="white-space:nowrap">{{ $log->performed_at->format('d.m.Y') }}</td><td><span class="badge">{{ $log->category->label() }}</span></td>
      <td><strong>{{ $log->title }}</strong>@if ($log->description)<div class="small muted" style="white-space:pre-line">{{ $log->description }}</div>@endif</td>
      <td class="small">{{ $log->site?->domain ?? '—' }}</td><td class="small">{{ $log->duration_minutes ? $log->duration_minutes.' min' : '—' }}</td></tr>
  @empty
    <tr><td colspan="5" class="empty">Nicio lucrare în perioada aleasă.</td></tr>
  @endforelse
  </tbody>
</table></div>
@include('partials.pager', ['paginator' => $logs])
@endsection
