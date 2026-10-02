@extends('layouts.app')
@section('title', 'Prezentare')
@section('content')
<div class="head">
  <div><h1>VITIM AI</h1><p>Toți clienții platformei. Cifrele sunt reale; secțiunile încă neconstruite apar cu 0 și o notă.</p></div>
  <a class="btn btn-p" href="{{ route('admin.organizations.create') }}">Client nou</a>
</div>
<div class="grid" style="margin-bottom:18px">
  @foreach ($kpi as [$label, $value, $note])
    <div class="kpi"><small>{{ $label }}</small><b>{{ number_format($value, 0, ',', '.') }}</b>@if ($note)<div class="tag-note">{{ $note }}</div>@endif</div>
  @endforeach
</div>
<div class="card">
  <h2>Consum luna aceasta</h2>
  @if ($usage->isEmpty())
    <p class="muted" style="margin:0">Niciun consum înregistrat încă. Costul AI apare după activarea agentului (Faza 2).</p>
  @else
    <dl class="kv">@foreach ($usage as $metric => $total)<dt class="mono">{{ $metric }}</dt><dd>{{ number_format((int) $total, 0, ',', '.') }}</dd>@endforeach</dl>
  @endif
</div>
<div class="tabs">
  <a href="?" @class(['on' => ! $serviceFilter])>Toți clienții ({{ $totalOrganizations }})</a>
  @foreach (\App\Reports\ServiceCatalog::SERVICES as $key => $service)
    <a href="?serviciu={{ $key }}" @class(['on' => $serviceFilter === $key])>{{ $service[0] }} ({{ $serviceCounts[$key] }})</a>
  @endforeach
  <a href="?serviciu=fara" @class(['on' => $serviceFilter === 'fara'])>Fără servicii ({{ $serviceCounts['fara'] }})</a>
</div>
<div class="table-wrap">
  <table>
    <thead><tr><th>Organizație</th><th>Servicii</th><th>Plan</th><th>Abonament</th><th>Site-uri</th><th>Agenți</th><th>Utilizatori</th><th>Contacte</th><th>Lead-uri</th></tr></thead>
    <tbody>
    @forelse ($organizations as $org)
      @php($sub = $subscriptions[$org->id] ?? null)
      <tr>
        <td><a href="{{ route('admin.organizations.show', $org->slug) }}"><strong>{{ $org->name }}</strong></a>
          @unless ($org->isActive())<span class="badge err">suspendat</span>@endunless</td>
        <td>@forelse ($services[$org->id] ?? [] as $svc)<span class="badge ok" style="margin:1px">{{ ['maintenance' => 'Mentenanță', 'seo' => 'SEO', 'google_ads' => 'Ads', 'google_business' => 'GBP'][$svc] ?? $svc }}</span>@empty<span class="muted small">—</span>@endforelse</td>
        <td>{{ strtoupper($sub?->plan ?? '—') }}</td>
        <td>@if ($sub)<span @class(['badge', 'ok' => $sub->isServiceable(), 'err' => ! $sub->isServiceable()])>{{ $sub->status->value }}</span>@endif</td>
        @foreach (['sites', 'agents', 'members', 'contacts', 'leads'] as $k)<td>{{ $counts[$k][$org->id] ?? 0 }}</td>@endforeach
      </tr>
    @empty
      <tr><td colspan="9" class="empty">{{ $serviceFilter ? 'Niciun client cu acest serviciu.' : 'Niciun client încă.' }}</td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@endsection
