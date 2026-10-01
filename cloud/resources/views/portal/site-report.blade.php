@extends('layouts.app')
@section('title', 'Raport '.$site->domain)
@section('content')
<div class="head"><div><h1>{{ $site->domain }}</h1>
  <p>Raport de sănătate al site-ului · verificat {{ $site->last_audit_at ? $site->last_audit_at->format('d.m.Y H:i') : 'în curând' }}</p></div></div>
@include('partials.site-scores', ['site' => $site])
<div class="card">
  <h2>Rezolvate de VITIM în ultimele 90 de zile ({{ $resolved->count() }})</h2>
  @forelse ($resolved as $issue)
    <p style="margin:6px 0"><span class="badge ok">rezolvat</span> {{ $issue->title }} <span class="small muted">· {{ $issue->resolved_at?->format('d.m.Y') }} · {{ \App\Audit\Guidance::CATEGORIES[$issue->category] ?? '' }}</span></p>
  @empty
    <p class="muted" style="margin:0">Nimic de raportat încă.</p>
  @endforelse
</div>
<div class="card">
  <h2>De îmbunătățit ({{ $open->count() }})</h2>
  @forelse ($open as $issue)
    <p style="margin:6px 0"><span class="badge {{ ['critical' => 'err', 'warning' => 'warn', 'info' => ''][$issue->severity] ?? '' }}">{{ \App\Models\SiteIssue::SEVERITIES[$issue->severity] ?? '' }}</span>
      {{ $issue->title }} <span class="small muted">· {{ \App\Audit\Guidance::CATEGORIES[$issue->category] ?? '' }}</span></p>
  @empty
    <p class="muted" style="margin:0">Nicio problemă deschisă.</p>
  @endforelse
  @if ($open->isNotEmpty())<p class="tag-note" style="margin:10px 0 0">Pentru detalii și remediere, contactează echipa VITIM.</p>@endif
</div>
@include('partials.site-backups', ['site' => $site, 'backups' => $backups, 'staff' => false])
<div class="card">
  <h2>Lucrări VITIM pe acest site</h2>
  @forelse ($work as $log)
    <p style="margin:6px 0"><span class="small muted">{{ $log->performed_at->format('d.m.Y') }}</span> · <strong>{{ $log->title }}</strong> <span class="badge">{{ $log->category->label() }}</span></p>
  @empty
    <p class="muted" style="margin:0">Nicio lucrare înregistrată încă.</p>
  @endforelse
</div>
@endsection
