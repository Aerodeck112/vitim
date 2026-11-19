@extends('layouts.app')
@section('title', 'Birou')
@section('content')
@include('partials.office-style')
<div class="head"><div><h1>Biroul {{ $organization->name }}</h1>
  <p>Fiecare agent răspunde de un singur lucru. Aici vezi ce face fiecare acum și unde te așteaptă pe tine.
    @if ($summary['problem'] + $summary['needs'] === 0) Acum nu ai nimic de făcut.
    @else Acum: @if ($summary['problem'])<strong>{{ $summary['problem'] }} cu probleme</strong>@endif @if ($summary['problem'] && $summary['needs']) și @endif @if ($summary['needs'])<strong>{{ $summary['needs'] }} te așteaptă</strong>@endif.
    @endif</p></div></div>
<div class="office">
  @foreach (\App\Office\Office::ZONES as $zone => $label)
    <section class="zone" aria-label="{{ $label }}">
      <h2>{{ $label }}</h2>
      <div class="desks">
        @foreach (collect($desks)->where('zone', $zone) as $desk)
          <article class="desk {{ $desk['state'] }}">
            <span class="lamp"><i aria-hidden="true"></i>{{ \App\Office\Office::STATES[$desk['state']][0] }}</span>
            <h3>{{ $desk['name'] }}</h3>
            <p class="role">{{ $desk['role'] }}</p>
            <p class="line">{{ $desk['line'] }}</p>
            <ul>@foreach ($desk['facts'] as $fact)<li>{{ $fact }}</li>@endforeach</ul>
            @if ($desk['url'])<a class="btn btn-s" href="{{ $desk['url'] }}">Deschide</a>@endif
          </article>
        @endforeach
      </div>
    </section>
  @endforeach
</div>
@endsection
