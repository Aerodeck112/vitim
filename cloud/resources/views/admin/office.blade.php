@extends('layouts.app')
@section('title', 'Birou')
@section('content')
@include('partials.office-style')
<div class="head"><div><h1>Biroul VITIM AI</h1><p>Agenții platformei, văzuți peste toți clienții. Fiecare răspunde de un singur lucru; sub fiecare apar firmele la care are o problemă sau așteaptă un om.</p></div>
  <a class="btn" href="{{ route('admin.monitor') }}">Monitorizare</a></div>
<div class="office">
  <section class="zone" aria-label="Echipa VITIM">
    <h2>Echipa VITIM</h2>
    <div class="desks">
      @php($mState = ! $monitorOn ? 'off' : (($monitor['critical'] ?? 0) > 0 ? 'problem' : 'working'))
      <article class="desk {{ $mState }}">
        <span class="lamp"><i aria-hidden="true"></i>{{ \App\Office\Office::STATES[$mState][0] }}</span>
        <h3>Agentul de monitorizare</h3><p class="role">Urmărește toți clienții și anunță echipa</p>
        <p class="line">{{ ! $monitorOn ? 'Rezumatul zilnic este oprit.' : (($monitor['critical'] ?? 0).' critice, '.($monitor['warning'] ?? 0).' de urmărit la ultima verificare') }}</p>
        <a class="btn btn-s" href="{{ route('admin.monitor') }}">Deschide</a>
      </article>
    </div>
  </section>
  @foreach (\App\Office\Office::ZONES as $zone => $label)
    <section class="zone" aria-label="{{ $label }}">
      <h2>{{ $label }}</h2>
      <div class="desks">
        @foreach (collect($agents)->where('zone', $zone) as $agent)
          <article class="desk {{ $agent['state'] }}">
            <span class="lamp"><i aria-hidden="true"></i>{{ \App\Office\Office::STATES[$agent['state']][0] }}</span>
            <h3>{{ $agent['name'] }}</h3>
            <p class="role">{{ $agent['role'] }}</p>
            <p class="line">Lucrează la {{ $agent['counts']['working'] + $agent['counts']['idle'] + $agent['counts']['needs'] + $agent['counts']['problem'] }} firme · oprit la {{ $agent['counts']['off'] }}</p>
            <ul>
              @foreach (array_slice($agent['attention'], 0, 6) as $item)
                <li><span class="badge {{ $item['state'] === 'problem' ? 'err' : 'warn' }}">{{ $item['state'] === 'problem' ? 'problemă' : 'așteaptă' }}</span> <a href="{{ route('portal.office', $item['slug']) }}">{{ $item['org'] }}</a>: {{ $item['line'] }}</li>
              @endforeach
              @if (count($agent['attention']) > 6)<li>și încă {{ count($agent['attention']) - 6 }} firme</li>@endif
              @if (! $agent['attention'])<li>Nicio firmă nu cere atenție.</li>@endif
            </ul>
          </article>
        @endforeach
      </div>
    </section>
  @endforeach
  @if (! $agents)<p class="muted">Niciun client activ încă.</p>@endif
</div>
@endsection
