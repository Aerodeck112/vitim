@extends('layouts.app')
@section('title', $site->domain.' · stare')
@section('content')
@php($slug = $organization->slug)
@php($h = $site->health ?? [])
<div class="head"><div><h1>{{ $site->domain }}</h1>
  <p>@include('partials.site-health', ['site' => $site])
    · audit {{ $site->last_audit_at ? $site->last_audit_at->diffForHumans() : 'niciodată' }}
    · scanare plugin {{ $site->last_scan_at ? $site->last_scan_at->diffForHumans() : 'niciodată' }}</p></div>
  <a class="btn" href="{{ route('admin.organizations.show', $slug) }}">Fișa clientului</a></div>
@if (session('error'))<div class="alert alert-err">{{ session('error') }}</div>@endif
@error('fix')<div class="alert alert-err">{{ $message }}</div>@enderror

@include('partials.site-scores', ['site' => $site])

<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px">
  <form method="post" action="{{ route('admin.sites.audit', [$slug, $site->id]) }}" onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Se auditează… (până la un minut)'">@csrf
    <button class="btn btn-p" type="submit">Rulează auditul SEO / securitate / legal</button></form>
  @if (! empty($h['command_url']))
    @foreach (['scan', 'backup', 'update_all_plugins'] as $fix)
      <form method="post" action="{{ route('admin.sites.command', [$slug, $site->id]) }}" onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Se execută pe site…'">@csrf
        <input type="hidden" name="fix" value="{{ $fix }}"><button class="btn" type="submit">{{ $fix === 'scan' ? 'Scanează cu pluginul' : \App\Services\Remediation::label($fix) }}</button></form>
    @endforeach
  @endif
</div>
@php($pilotOk = \App\Services\SeoAutopilot::supported($site))
@php($pilotPending = app(\App\Services\SeoAutopilot::class)->pending($site))
<div class="card pilot" aria-labelledby="pilot-h">
  <div class="pilot-head">
    <div><h2 id="pilot-h" style="margin:0 0 4px">Pilot automat SEO</h2>
      <p class="small muted" style="margin:0">O dată pe săptămână, după audit, aplică singur remedierile sigure: {{ implode(', ', array_map(fn ($t) => mb_strtolower(\App\Services\Remediation::SEO_FIXES[$t]), \App\Services\SeoAutopilot::SAFE)) }}. Apoi reface auditul. HTTPS, www, robots.txt, indexarea, antetele și compresia rămân pe buton.</p></div>
    <form method="post" action="{{ route('admin.sites.autopilot', [$slug, $site->id]) }}">@csrf
      <input type="hidden" name="on" value="{{ $site->seo_autopilot ? 0 : 1 }}">
      <button type="submit" class="sw-btn{{ $site->seo_autopilot ? ' on' : '' }}" role="switch" aria-checked="{{ $site->seo_autopilot ? 'true' : 'false' }}">
        <span class="sw-track" aria-hidden="true"><span class="sw-knob"></span></span>
        <span class="sw-text"><span class="sw-label">Pilot automat</span><span class="sw-state">{{ $site->seo_autopilot ? 'pornit' : 'oprit' }}</span></span></button></form>
  </div>
  <p class="small" style="margin:10px 0 0">
    @if (! $pilotOk)<span class="badge warn">nu se poate aplica</span> Are nevoie de WordPress cu pluginul VITIM {{ \App\Services\Remediation::SEO_PLUGIN_VERSION }}+ și remedierile de la distanță permise. Pe alte site-uri, problemele rămân cu pașii din „Cum rezolvi”.
    @else Ultima rulare: <strong>{{ $site->seo_autopilot_at ? $site->seo_autopilot_at->setTimezone('Europe/Bucharest')->format('d.m.Y H:i') : 'încă nu' }}</strong>@if ($site->seo_autopilot) · următoarea: {{ $site->seo_autopilot_at ? $site->seo_autopilot_at->addDays(\App\Services\SeoAutopilot::EVERY_DAYS)->setTimezone('Europe/Bucharest')->format('d.m.Y') : 'la următorul audit' }}@endif
      · de reparat acum: {{ $pilotPending ? implode(', ', array_map(fn ($t) => mb_strtolower(\App\Services\Remediation::SEO_FIXES[$t]), $pilotPending)) : 'nimic' }}
    @endif
  </p>
  @if ($pilotOk && $pilotPending)
    <form method="post" action="{{ route('admin.sites.autopilot.run', [$slug, $site->id]) }}" style="margin-top:10px" onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Se repară și se reface auditul…'">@csrf<button class="btn btn-s" type="submit">Rulează pilotul acum</button></form>
  @endif
</div>
@php($autoFix = ! empty($h['command_url']) ? \App\Services\Remediation::combine($open->pluck('fix')) : null)
@if ($autoFix)
  @php($autoCount = $open->filter(fn ($i) => $i->fix && str_starts_with($i->fix, 'seo_fix:'))->count())
  <div class="card" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;border-color:var(--brand)">
    <div><strong>{{ $autoCount }} {{ $autoCount === 1 ? 'problemă se poate' : 'probleme se pot' }} rezolva automat</strong>
      <div class="small muted">{{ implode(' · ', array_map(fn ($t) => \App\Services\Remediation::SEO_FIXES[$t], explode('.', substr($autoFix, 8)))) }}. Pluginul aplică remedierile, apoi auditul se reface. Fiecare remediere intră în „Lucrări VITIM” la SEO și se poate opri din WordPress → Setări → VITIM.</div></div>
    <form method="post" action="{{ route('admin.sites.command', [$slug, $site->id]) }}" onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Se repară și se refac verificările… (până la un minut)'">@csrf
      <input type="hidden" name="fix" value="{{ $autoFix }}"><button class="btn btn-p" type="submit">Repară tot automat</button></form>
  </div>
@endif
@if (($site->platform->value === 'wordpress' || $site->platform->value === 'woocommerce') && empty($h['command_url']))
  <div class="alert alert-warn">Pentru scanarea din interiorul WordPress și remedierile cu un click instalează pluginul VITIM Connector 1.1.0.</div>
@elseif (($h['remote_fixes'] ?? true) === false)
  <div class="alert alert-warn">Remedierile de la distanță sunt oprite din pluginul de pe site (WordPress → Setări → VITIM).</div>
@endif

<div class="tabs">
  <a href="?" @class(['on' => ! $category])>Toate ({{ $open->count() }})</a>
  @foreach (\App\Audit\Guidance::CATEGORIES as $key => $label)
    @php($n = $open->where('category', $key)->count())
    @if ($n)<a href="?categorie={{ $key }}" @class(['on' => $category === $key])>{{ $label }} ({{ $n }})</a>@endif
  @endforeach
</div>

<div class="card">
  @php($shown = $category ? $open->where('category', $category) : $open)
  @forelse ($shown as $issue)
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;padding:12px 0;border-bottom:1px solid var(--border);flex-wrap:wrap">
      <div style="flex:1;min-width:260px">
        <span class="badge {{ ['critical' => 'err', 'warning' => 'warn', 'info' => ''][$issue->severity] ?? '' }}">{{ \App\Models\SiteIssue::SEVERITIES[$issue->severity] ?? $issue->severity }}</span>
        <span class="badge">{{ \App\Audit\Guidance::CATEGORIES[$issue->category] ?? $issue->category }}</span>
        <strong>{{ $issue->title }}</strong>
        @if ($issue->details)<div class="small muted" style="white-space:pre-line;margin-top:4px;word-break:break-word">{{ $issue->details }}</div>@endif
        @if ($guide = \App\Audit\Guidance::howTo($issue->code, $site->domain))
          <details class="howto"><summary>Cum rezolvi</summary><div>{{ $guide }}</div></details>
        @endif
        <div class="small muted">din {{ $issue->first_seen_at->format('d.m.Y') }} · {{ $issue->source === 'audit' ? 'audit extern' : 'plugin' }}</div>
      </div>
      @if ($issue->fix && ! empty($h['command_url']))
        <form method="post" action="{{ route('admin.sites.command', [$slug, $site->id]) }}" onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Se execută…'">@csrf
          <input type="hidden" name="fix" value="{{ $issue->fix }}"><button class="btn btn-p" type="submit">{{ str_starts_with($issue->fix, 'seo_fix:') ? 'Repară' : \App\Services\Remediation::label($issue->fix) }}</button></form>
      @endif
    </div>
  @empty
    <p class="muted" style="margin:0">{{ $site->last_audit_at || $site->last_scan_at ? 'Nicio problemă deschisă.' : 'Site-ul nu a fost verificat încă. Apasă „Rulează auditul”.' }}</p>
  @endforelse
</div>

@if ($resolved->isNotEmpty())
<div class="card">
  <h2>Rezolvate recent</h2>
  @foreach ($resolved as $issue)<p style="margin:6px 0" class="small"><span class="badge ok">rezolvat</span> {{ $issue->title }} <span class="muted">· {{ $issue->resolved_at?->format('d.m.Y H:i') }}</span></p>@endforeach
</div>
@endif

@include('partials.site-backups', ['site' => $site, 'backups' => $backups, 'staff' => true])

<div class="card">
  <h2>Istoric remedieri</h2>
  @forelse ($commands as $c)
    <p style="margin:6px 0" class="small"><span class="badge {{ $c->status === 'done' ? 'ok' : ($c->status === 'failed' ? 'err' : '') }}">{{ $c->status === 'done' ? 'reușit' : ($c->status === 'failed' ? 'eșuat' : 'în curs') }}</span>
      {{ $c->created_at->format('d.m.Y H:i') }} · <strong>{{ \App\Services\Remediation::label($c->action.($c->target ? ':'.$c->target : '')) }}</strong>{{ $c->target && $c->action !== 'seo_fix' ? ' ('.$c->target.')' : '' }}
      · {{ $c->requester?->name ?? 'pilot automat / sistem' }} <span class="muted">· {{ $c->result }}</span></p>
  @empty
    <p class="muted" style="margin:0">Nicio remediere încă.</p>
  @endforelse
</div>
@endsection
