{{-- Starea unui site raportată de plugin / conector. $site obligatoriu. --}}
@php($h = $site->health ?? [])
@if (! $site->last_seen_at)
  <span class="badge">neconectat</span>
@else
  @if ($site->isOnline())<span class="badge ok">conectat</span>@else<span class="badge err">fără semnal</span>@endif
  <span class="small muted">
    văzut {{ $site->last_seen_at->diffForHumans() }}
    @if (! empty($h['core_version'])) · {{ ($h['platform'] ?? '') === 'wordpress' ? 'WordPress' : 'versiune' }} {{ $h['core_version'] }}@endif
    @if (! empty($h['app_version'])) · aplicație {{ $h['app_version'] }}@endif
    @if (! empty($h['php_version'])) · PHP {{ $h['php_version'] }}@endif
  </span>
  @php($pending = count($h['plugin_updates'] ?? []) + (int) ($h['theme_updates'] ?? 0) + (empty($h['core_update']) ? 0 : 1))
  @if ($pending)<span class="badge warn">{{ $pending }} {{ $pending === 1 ? 'actualizare' : 'actualizări' }} în așteptare</span>@endif
  @if (($site->verification_status ?? '') === 'mismatch')<span class="badge err">adresa raportată nu e {{ $site->domain }}</span>@endif
@endif
