@extends('layouts.app')
@section('title', 'Noutăți VITIM')
@section('content')
<div class="head"><div><h1>Noutăți VITIM</h1><p>Ce e nou în platformă și ce am făcut pentru firma ta. Le primești și pe email.</p></div></div>
@if ($items->isEmpty())
  <div class="card"><p class="muted" style="margin:0">Încă nu ai primit noutăți. Le vei găsi aici după primul email de la echipa VITIM.</p></div>
@else
  @foreach ($items as $a)
    <a class="card" href="{{ route('portal.news.show', [$organization->slug, $a->id]) }}" style="display:block;text-decoration:none;color:inherit">
      <div class="small muted">{{ $a->sent_at?->setTimezone('Europe/Bucharest')->format('d.m.Y') }} · {{ \App\Models\Announcement::KINDS[$a->kind] }}</div>
      <h2 style="margin:6px 0 4px">{{ $a->title }}</h2>
      @if ($a->intro)<p class="muted" style="margin:0">{{ $intros[$a->id] ?? '' }}</p>@endif
    </a>
  @endforeach
  @include('partials.pager', ['paginator' => $items])
@endif
@endsection
