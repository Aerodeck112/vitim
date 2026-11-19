{{-- Planul biroului, văzut de sus: câte o cameră pe zonă, câte o masă pe agent. $rooms = [etichetă => listă de [name, state, url, note]] --}}
@php
  $colors = ['working' => '#10b981', 'needs' => '#d99a12', 'problem' => '#ef4444', 'idle' => '#94a3b8', 'off' => '#cbd5e1'];
  $deskW = 190; $deskH = 150; $pad = 26; $gap = 22; $x = 20; $layout = [];
  foreach ($rooms as $label => $list) {
      $cols = max(1, (int) ceil(count($list) / 2)); $rows = count($list) > $cols ? 2 : 1;
      $w = $pad * 2 + $cols * $deskW + ($cols - 1) * 14;
      $layout[] = ['label' => $label, 'x' => $x, 'w' => $w, 'cols' => $cols, 'rows' => $rows, 'list' => array_values($list)];
      $x += $w + $gap;
  }
  $maxRows = max(array_column($layout, 'rows') ?: [1]);
  $W = $x - $gap + 20; $H = 70 + $maxRows * $deskH + 46;
@endphp
<style>
.plan-wrap{overflow-x:auto;border:1px solid var(--border);border-radius:16px;background:var(--panel);margin-bottom:18px}
.plan{display:block;min-width:{{ min($W, 900) }}px;width:100%;height:auto}
.plan .room{fill:var(--hover,#f1f5f9);stroke:var(--border-2,#cbd5e1);stroke-width:2}
.plan .room-label{font:700 12px var(--font,sans-serif);letter-spacing:.08em;text-transform:uppercase;fill:var(--text-2,#64748b)}
.plan .table{fill:var(--panel,#fff);stroke:var(--border-2,#cbd5e1);stroke-width:1.5}
.plan .screen{fill:#1e293b}.plan .chair{fill:var(--border-2,#cbd5e1)}
.plan .who{font:600 12.5px var(--font,sans-serif);fill:var(--text,#0f172a)}
.plan .note{font:11px var(--font,sans-serif);fill:var(--text-2,#64748b)}
.plan a{cursor:pointer}.plan a:hover .table,.plan a:focus .table{stroke:var(--brand,#2f6bff);stroke-width:2.5}
.plan .off{opacity:.45}
.plan .busy{animation:plan-work 1.6s ease-in-out infinite;transform-box:fill-box;transform-origin:center}
@keyframes plan-work{50%{transform:translateY(-2.5px)}}
@media (prefers-reduced-motion:reduce){.plan .busy{animation:none}}
.plan-legend{display:flex;gap:14px;flex-wrap:wrap;padding:8px 16px 12px;font-size:12.5px;color:var(--text-2)}
.plan-legend i{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:5px;vertical-align:-1px}
</style>
<div class="plan-wrap">
<svg class="plan" viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="Planul biroului: {{ collect($rooms)->flatten(1)->map(fn ($d) => $d['name'].' – '.\App\Office\Office::STATES[$d['state']][0])->implode('; ') }}">
  @foreach ($layout as $room)
    <rect class="room" x="{{ $room['x'] }}" y="20" width="{{ $room['w'] }}" height="{{ $H - 40 }}" rx="18"/>
    <rect x="{{ $room['x'] + $room['w'] / 2 - 26 }}" y="{{ $H - 23 }}" width="52" height="6" rx="3" fill="var(--panel,#fff)"/>
    <text class="room-label" x="{{ $room['x'] + 22 }}" y="48">{{ $room['label'] }}</text>
    @foreach ($room['list'] as $i => $desk)
      @php
        $cx = $room['x'] + $pad + ($i % $room['cols']) * ($deskW + 14) + $deskW / 2;
        $ty = 66 + intdiv($i, $room['cols']) * $deskH;
        $c = $colors[$desk['state']];
        $label = \Illuminate\Support\Str::limit($desk['name'], 26);
      @endphp
      <a @if ($desk['url']) href="{{ $desk['url'] }}" @endif class="{{ $desk['state'] === 'off' ? 'off' : '' }}">
        <title>{{ $desk['name'] }}: {{ \App\Office\Office::STATES[$desk['state']][0] }}. {{ $desk['note'] }}</title>
        <rect class="table" x="{{ $cx - 62 }}" y="{{ $ty }}" width="124" height="46" rx="9"/>
        <rect class="screen" x="{{ $cx - 22 }}" y="{{ $ty + 7 }}" width="44" height="9" rx="3"/>
        <rect x="{{ $cx - 20 }}" y="{{ $ty + 8.5 }}" width="40" height="6" rx="2" fill="{{ $desk['state'] === 'off' ? '#475569' : $c }}" opacity=".85"/>
        <rect x="{{ $cx - 16 }}" y="{{ $ty + 24 }}" width="32" height="11" rx="3" fill="var(--border,#e2e8f0)"/>
        <circle cx="{{ $cx + 46 }}" cy="{{ $ty + 14 }}" r="6" fill="{{ $c }}"/>
        <g class="{{ $desk['state'] === 'working' ? 'busy' : '' }}">
          <rect class="chair" x="{{ $cx - 21 }}" y="{{ $ty + 66 }}" width="42" height="16" rx="8"/>
          <ellipse cx="{{ $cx }}" cy="{{ $ty + 66 }}" rx="24" ry="13" fill="{{ $c }}"/>
          <circle cx="{{ $cx }}" cy="{{ $ty + 58 }}" r="11" fill="#f1c9a5" stroke="{{ $c }}" stroke-width="2.5"/>
        </g>
        <text class="who" x="{{ $cx }}" y="{{ $ty + 104 }}" text-anchor="middle">{{ $label }}</text>
        <text class="note" x="{{ $cx }}" y="{{ $ty + 120 }}" text-anchor="middle">{{ \App\Office\Office::STATES[$desk['state']][0] }}</text>
      </a>
    @endforeach
  @endforeach
</svg>
<div class="plan-legend">@foreach (\App\Office\Office::STATES as $key => $state)<span><i style="background:{{ $colors[$key] }}"></i>{{ $state[0] }}</span>@endforeach</div>
</div>
