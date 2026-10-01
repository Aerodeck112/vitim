@extends('layouts.app')
@section('title', $site->domain.' · stare')
@section('content')
@php($slug = $organization->slug)
@php($h = $site->health ?? [])
<div class="head"><div><h1>{{ $site->domain }}</h1>
  <p>@include('partials.site-health', ['site' => $site])
    · scanat {{ $site->last_scan_at ? $site->last_scan_at->diffForHumans() : 'niciodată' }}</p></div>
  <a class="btn" href="{{ route('admin.organizations.show', $slug) }}">Fișa clientului</a></div>
@if (session('error'))<div class="alert alert-err">{{ session('error') }}</div>@endif
@error('fix')<div class="alert alert-err">{{ $message }}</div>@enderror

@if (empty($h['command_url']))
  <div class="alert alert-warn">Remedierile din panou cer pluginul VITIM Connector 1.1.0 sau mai nou (instalat acum: {{ $site->connector_version ?? '—' }}).</div>
@elseif (($h['remote_fixes'] ?? true) === false)
  <div class="alert alert-warn">Remedierile de la distanță sunt oprite din pluginul de pe site (WordPress → Setări → VITIM).</div>
@endif

<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px">
  @foreach (['scan', 'update_all_plugins'] as $fix)
    <form method="post" action="{{ route('admin.sites.command', [$slug, $site->id]) }}" onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Se execută pe site…'">@csrf
      <input type="hidden" name="fix" value="{{ $fix }}"><button class="btn {{ $fix === 'scan' ? 'btn-p' : '' }}" type="submit">{{ \App\Services\Remediation::label($fix) }}</button></form>
  @endforeach
</div>

<div class="card">
  <h2>Probleme deschise ({{ $open->count() }})</h2>
  @forelse ($open as $issue)
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;padding:12px 0;border-bottom:1px solid var(--border);flex-wrap:wrap">
      <div style="flex:1;min-width:260px">
        <span class="badge {{ ['critical' => 'err', 'warning' => 'warn', 'info' => ''][$issue->severity] ?? '' }}">{{ \App\Models\SiteIssue::SEVERITIES[$issue->severity] ?? $issue->severity }}</span>
        <strong>{{ $issue->title }}</strong>
        @if ($issue->details)<div class="small muted" style="white-space:pre-line;margin-top:4px">{{ $issue->details }}</div>@endif
        <div class="small muted">din {{ $issue->first_seen_at->format('d.m.Y') }}</div>
      </div>
      @if ($issue->fix)
        <form method="post" action="{{ route('admin.sites.command', [$slug, $site->id]) }}" onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Se execută…'">@csrf
          <input type="hidden" name="fix" value="{{ $issue->fix }}"><button class="btn btn-p" type="submit">{{ \App\Services\Remediation::label($issue->fix) }}</button></form>
      @else
        <span class="small muted">remediere manuală</span>
      @endif
    </div>
  @empty
    <p class="muted" style="margin:0">{{ $site->last_scan_at ? 'Nicio problemă găsită la ultima scanare.' : 'Site-ul nu a fost scanat încă. Apasă „Scanează acum”.' }}</p>
  @endforelse
</div>

@if ($resolved->isNotEmpty())
<div class="card">
  <h2>Rezolvate recent</h2>
  @foreach ($resolved as $issue)<p style="margin:6px 0" class="small"><span class="badge ok">rezolvat</span> {{ $issue->title }} <span class="muted">· {{ $issue->resolved_at?->format('d.m.Y H:i') }}</span></p>@endforeach
</div>
@endif

<div class="card">
  <h2>Istoric remedieri</h2>
  @forelse ($commands as $c)
    <p style="margin:6px 0" class="small"><span class="badge {{ $c->status === 'done' ? 'ok' : ($c->status === 'failed' ? 'err' : '') }}">{{ $c->status === 'done' ? 'reușit' : ($c->status === 'failed' ? 'eșuat' : 'în curs') }}</span>
      {{ $c->created_at->format('d.m.Y H:i') }} · <strong>{{ \App\Services\Remediation::label($c->action.($c->target ? ':'.$c->target : '')) }}</strong>{{ $c->target ? ' ('.$c->target.')' : '' }}
      · {{ $c->requester?->name ?? 'sistem' }} <span class="muted">· {{ $c->result }}</span></p>
  @empty
    <p class="muted" style="margin:0">Nicio remediere încă.</p>
  @endforelse
</div>
@endsection
