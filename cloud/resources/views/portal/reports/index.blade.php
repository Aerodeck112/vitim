@extends('layouts.app')
@section('title', 'Rapoarte')
@section('content')
<div class="head"><div><h1>Rapoarte lunare</h1><p>Ce a făcut VITIM pentru tine, lună de lună, și rezultatele serviciilor.</p></div></div>
<div class="card">
  @forelse ($reports as $rep)
    <p style="margin:8px 0"><a href="{{ route('portal.reports.show', [$organization->slug, $rep->period]) }}"><strong>{{ ucfirst(\Illuminate\Support\Carbon::createFromFormat('Y-m-d', $rep->period.'-01')->locale('ro')->translatedFormat('F Y')) }}</strong></a>
      <span class="small muted">· publicat {{ $rep->published_at->format('d.m.Y') }}</span></p>
  @empty
    <p class="muted" style="margin:0">Primul raport apare la sfârșitul lunii.</p>
  @endforelse
</div>
@endsection
