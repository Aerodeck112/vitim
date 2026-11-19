@extends('layouts.app')
@section('title', 'Monitorizare')
@section('content')
<div class="head">
  <div><h1>Agent de monitorizare</h1><p>Tot ce cere atenție la clienții platformei, într-un singur loc: site-uri care nu mai raportează, probleme critice, backup-uri, certificate, abonamente, agenți. Pagina arată starea de acum; rezumatul pleacă pe email în fiecare dimineață.</p></div>
  <form method="post" action="{{ route('admin.monitor.send') }}">@csrf<button class="btn btn-p" type="submit">Trimite rezumatul acum</button></form>
</div>

<div class="grid" style="margin-bottom:18px">
  <div class="kpi"><small>Critice</small><b @if ($report['critical']) style="color:var(--err,#c0392b)" @endif>{{ $report['critical'] }}</b></div>
  <div class="kpi"><small>De urmărit</small><b>{{ $report['warning'] }}</b></div>
  <div class="kpi"><small>Clienți activi</small><b>{{ $report['clients'] }}</b></div>
  <div class="kpi"><small>Site-uri</small><b>{{ $report['sites'] }}</b></div>
</div>

@foreach ($report['platform'] as $note)
  <div class="alert alert-err"><strong>Platformă:</strong> {{ $note }}</div>
@endforeach

@if ($groups->isEmpty())
  <div class="alert alert-ok">Nimic de semnalat: toate site-urile raportează și nu există probleme critice.</div>
@else
  <div class="table-wrap"><table>
    <thead><tr><th>Client</th><th>Nivel</th><th>Zonă</th><th>Problemă</th></tr></thead>
    <tbody>
    @foreach ($groups as $org => $items)
      @foreach ($items as $i => $item)
        <tr>
          <td>@if ($i === 0)<a href="{{ route('admin.organizations.show', $item['org_slug']) }}"><strong>{{ $org }}</strong></a>@endif</td>
          <td><span class="badge {{ $item['level'] === 'critical' ? 'err' : 'warn' }}">{{ $item['level'] === 'critical' ? 'critic' : 'atenție' }}</span></td>
          <td class="small muted">{{ $item['kind'] }}</td>
          <td><a href="{{ $item['url'] }}">{{ $item['title'] }}</a>@if ($item['detail'])<div class="small muted">{{ $item['detail'] }}</div>@endif</td>
        </tr>
      @endforeach
    @endforeach
    </tbody>
  </table></div>
@endif
@if ($report['other_warnings'] > 0)
  <p class="small muted">Alte {{ $report['other_warnings'] }} avertismente de audit sunt deschise (SEO, legal, performanță); le găsești la <a href="{{ route('admin.sites.index') }}">Site-uri</a>.</p>
@endif

<form method="post" action="{{ route('admin.monitor.settings') }}" class="card" style="margin-top:18px">
  @csrf @method('put')
  <h2>Rezumat și alerte</h2>
  <label class="chk"><input type="checkbox" name="enabled" value="1" @checked($settings['enabled'])> <span><strong>Trimite rezumatul în fiecare dimineață</strong>, cu toate problemele deschise, grupate pe client, și cu scorurile care au scăzut față de ziua precedentă.</span></label>
  <label class="chk"><input type="checkbox" name="urgent" value="1" @checked($settings['urgent'])> <span><strong>Alertă imediată pentru problemele critice noi</strong> (site care nu mai raportează de o zi, HTTPS căzut, backup eșuat, probă expirată). Verificarea se face la 15 minute; fiecare problemă anunță o singură dată.</span></label>
  <label class="chk"><input type="checkbox" name="always" value="1" @checked(! $settings['quiet'])> <span>Trimite rezumatul și în zilele în care nu e nimic de semnalat.</span></label>
  <div class="row" style="margin-top:8px">
    <div class="fl"><label for="hour">Ora rezumatului</label><input id="hour" type="number" name="hour" min="0" max="23" value="{{ old('hour', $settings['hour']) }}" style="width:90px"> <span class="small muted">ora României</span></div>
    <div class="fl" style="flex:1"><label for="extra">Adrese în plus (opțional)</label><input id="extra" type="text" name="extra" value="{{ old('extra', implode(', ', $settings['extra'])) }}" placeholder="adresa@firma.ro, alta@firma.ro">
      @error('extra.*')<div class="small" style="color:var(--err,#c0392b)">{{ $message }}</div>@enderror</div>
  </div>
  <p class="small muted">Primesc acum: {{ $recipients ? implode(', ', $recipients) : 'nimeni' }}. Conturile echipei VITIM primesc automat.@if ($lastDigest) Ultimul rezumat: {{ \Illuminate\Support\Carbon::parse($lastDigest)->format('d.m.Y') }}.@endif</p>
  <button class="btn" type="submit">Salvează</button>
</form>
@endsection
