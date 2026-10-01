{{-- Backup-urile unui site. $site, $backups (ultimele), $staff (bool). --}}
@php($schedule = $site->health['backup_schedule'] ?? null)
<div class="card">
  <h2>Backup-uri</h2>
  @if (! $schedule)
    <p class="muted" style="margin:0">Backup-ul automat cere pluginul VITIM Connector 1.2.0 sau mai nou.</p>
  @else
    <p class="small muted" style="margin-top:-6px">Automat: {{ ['daily' => 'zilnic', 'weekly' => 'săptămânal', 'off' => 'oprit'][$schedule] ?? $schedule }}
      · se păstrează ultimele {{ $site->health['backup_keep'] ?? '—' }} copii, pe hostingul site-ului.</p>
    @forelse ($backups as $b)
      <p style="margin:6px 0" class="small">
        @if ($b->status === 'ok' && $b->verified)<span class="badge ok">verificat</span>@elseif ($b->status === 'ok')<span class="badge warn">neverificat</span>@else<span class="badge err">eșuat</span>@endif
        {{ $b->started_at->format('d.m.Y H:i') }}
        @if ($b->status === 'ok') · bază de date {{ \App\Services\BackupMonitor::size($b->db_bytes) }} · fișiere {{ \App\Services\BackupMonitor::size($b->files_bytes) }} ({{ $b->files_count }})@endif
        @if ($staff && $b->status === 'ok' && $b->location)<span class="muted mono"> · {{ $b->location }}</span>@endif
        @if ($b->error)<span class="muted"> · {{ $b->error }}</span>@endif
      </p>
    @empty
      <p class="muted" style="margin:0">Niciun backup încă.</p>
    @endforelse
  @endif
</div>
