@extends('layouts.app')
@section('title', $organization->name)
@section('content')
@php($isAdmin = true)
<div class="head">
  <div><h1>{{ $organization->name }}</h1>
    <p>Plan {{ strtoupper($subscription?->plan ?? '—') }} · {{ $subscription?->status->value }}
      @if ($subscription?->trial_ends_at) · probă până la {{ $subscription->trial_ends_at->format('d.m.Y') }}@endif</p></div>
  <a class="btn" href="{{ route('portal.home', $organization->slug) }}">Deschide dashboardul firmei</a>
</div>

@if (session('issued'))
  <div class="card">
    <h2>Cod de instalare</h2>
    @include('partials.install', ['publicKey' => session('issued')['public'], 'secret' => session('issued')['secret']])
  </div>
@endif

<div class="card">
  <h2>Site-uri</h2>
  <p class="small muted" style="margin-top:-6px">Conectare: <a href="{{ asset('downloads/vitim-connector.zip') }}">pluginul WordPress</a> · <a href="{{ asset('downloads/vitim-connector.php.txt') }}" download="vitim-connector.php">conectorul PHP</a>. Codul de conectare apare o singură dată, la adăugarea site-ului sau după „Schimbă cheile”.</p>
  @if ($sites->isEmpty())<p class="muted">Niciun site încă.</p>@endif
  @foreach ($sites as $site)
    @php($active = $site->keys->firstWhere('revoked_at', null))
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);flex-wrap:wrap">
      <div><a href="{{ route('admin.sites.health', [$organization->slug, $site->id]) }}"><strong>{{ $site->domain }}</strong></a> <span class="badge">{{ $site->platform->label() }}</span>
        @php($openIssues = $site->issues->where('status', 'open'))
        @if ($openIssues->isNotEmpty())<a class="badge {{ $openIssues->contains('severity', 'critical') ? 'err' : 'warn' }}" href="{{ route('admin.sites.health', [$organization->slug, $site->id]) }}">{{ $openIssues->count() }} {{ $openIssues->count() === 1 ? 'problemă' : 'probleme' }}</a>@endif
        <div class="small muted mono">{{ $active?->public_key ?? 'fără cheie activă' }}</div>
        <div style="margin-top:4px">@include('partials.site-health', ['site' => $site])</div>
        @if (! empty($site->health['plugin_updates']))<details class="small"><summary>Pluginuri de actualizat</summary><ul style="margin:6px 0">@foreach ($site->health['plugin_updates'] as $u)<li>{{ $u['name'] }} {{ $u['from'] ?? '' }} → {{ $u['to'] ?? '' }}</li>@endforeach</ul></details>@endif</div>
      @if ($isAdmin)
        <form method="post" action="{{ route('admin.sites.rotate', [$organization->slug, $site->id]) }}" onsubmit="return confirm('Cheile vechi nu vor mai funcționa. Continui?')">
          @csrf<button class="btn btn-s" type="submit">Schimbă cheile</button></form>
      @endif
    </div>
  @endforeach
  @if ($isAdmin)
    <form method="post" action="{{ route('admin.sites.store', $organization->slug) }}" style="margin-top:16px" class="row">
      @csrf
      <div class="fl"><label for="domain">Domeniu nou</label><input id="domain" type="text" name="domain" placeholder="firma.ro" required></div>
      <div class="fl"><label for="platform">Platformă</label>
        <select id="platform" name="platform">@foreach (\App\Enums\SitePlatform::cases() as $p)<option value="{{ $p->value }}">{{ $p->label() }}</option>@endforeach</select></div>
      <div><button class="btn btn-p" type="submit">Adaugă site</button></div>
    </form>
  @endif
</div>

<div class="card">
  <h2>Agenți AI</h2>
  @forelse ($agents as $agent)
    <p style="margin:6px 0"><a href="{{ route('portal.agents.edit', [$organization->slug, $agent->id]) }}"><strong>{{ $agent->name }}</strong></a>
      <span class="badge">{{ $agent->status->value }}</span> <span class="small muted">{{ $agent->site?->domain }}</span></p>
  @empty
    <p class="muted">Niciun agent. <a href="{{ route('portal.agents.index', $organization->slug) }}">Creează agentul</a></p>
  @endforelse
</div>

<div class="card">
  <h2>Utilizatori <a class="btn btn-s" style="float:right" href="{{ route('portal.settings', $organization->slug) }}">Invită / administrează</a></h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Nume</th><th>Email</th><th>Rol</th><th>Ultima autentificare</th></tr></thead>
    <tbody>
    @forelse ($members as $m)
      <tr><td>{{ $m->user->name }}</td><td>{{ $m->user->email }}</td><td>{{ $m->role->label() }}</td><td class="small muted">{{ $m->user->last_login_at?->format('d.m.Y H:i') ?? 'niciodată' }}</td></tr>
    @empty
      <tr><td colspan="4" class="muted">Niciun utilizator.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</div>

<div class="card">
  <h2>Jurnal</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Data</th><th>Acțiune</th><th>Actor</th></tr></thead>
    <tbody>
    @foreach ($audit as $row)
      <tr><td class="small muted">{{ $row->created_at?->format('d.m.Y H:i') }}</td><td class="mono">{{ $row->action }}</td><td class="small">{{ $row->actor_type }}</td></tr>
    @endforeach
    </tbody>
  </table></div>
</div>
@endsection
