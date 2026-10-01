{{-- Conținutul raportului lunar ($r din ReportBuilder). Folosit în panoul clientului și la previzualizare. --}}
<div class="report">
  <div class="card">
    <h2>Pe scurt</h2>
    <div class="grid" style="margin-bottom:{{ $r['summary'] ? '14px' : '0' }}">
      <div class="kpi"><small>Lucrări în {{ $r['label'] }}</small><b>{{ $r['logs']->count() }}</b></div>
      <div class="kpi"><small>Timp lucrat</small><b>{{ intdiv($r['minutes'], 60) }} h {{ $r['minutes'] % 60 }} min</b></div>
      <div class="kpi"><small>Servicii active</small><b>{{ count($r['sections']) }}</b></div>
    </div>
    @if ($r['summary'])<div style="white-space:pre-line">{{ $r['summary'] }}</div>@endif
  </div>
  @foreach ($r['sections'] as $section)
    <div class="card">
      <h2>{{ $section['label'] }}</h2>
      @if ($section['rows'])
        <div class="table-wrap" style="margin-bottom:12px"><table>
          <thead><tr><th>Indicator</th><th>{{ $r['label'] }}</th><th>Luna anterioară</th><th>Evoluție</th></tr></thead><tbody>
          @foreach ($section['rows'] as $row)
            <tr><td>{{ $row['label'] }}</td>
              <td><strong>{{ is_numeric($row['value']) ? rtrim(rtrim(number_format((float) $row['value'], 2, ',', '.'), '0'), ',') : $row['value'] }}</strong> {{ $row['unit'] }}</td>
              <td class="muted">{{ $row['previous'] !== null && $row['previous'] !== '' ? rtrim(rtrim(number_format((float) $row['previous'], 2, ',', '.'), '0'), ',').' '.$row['unit'] : '—' }}</td>
              <td>@if ($row['delta'] !== null && $row['delta'] != 0)<span class="badge {{ $row['good'] === true ? 'ok' : ($row['good'] === false ? 'err' : '') }}">{{ $row['delta'] > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($row['delta'], 2, ',', '.'), '0'), ',') }}</span>@else<span class="muted">—</span>@endif</td></tr>
          @endforeach
        </tbody></table></div>
      @elseif ($section['service'] !== 'maintenance')
        <p class="muted">Cifrele lunii vor fi completate de echipa VITIM.</p>
      @endif
      @if ($section['summary'])<div style="white-space:pre-line;margin-bottom:10px">{{ $section['summary'] }}</div>@endif
      @if ($section['logs']->isNotEmpty())
        <div class="small"><strong>Ce am făcut:</strong>
          <ul style="margin:6px 0 0;padding-left:18px">@foreach ($section['logs']->take(30) as $log)<li>{{ $log->performed_at->format('d.m') }} · {{ $log->title }}@if ($log->site) <span class="muted">({{ $log->site->domain }})</span>@endif</li>@endforeach</ul>
          @if ($section['logs']->count() > 30)<p class="muted">și încă {{ $section['logs']->count() - 30 }} lucrări (vezi Lucrări VITIM).</p>@endif
        </div>
      @endif
    </div>
  @endforeach
  @if ($r['sites']->isNotEmpty())
    <div class="card">
      <h2>Starea site-urilor</h2>
      @foreach ($r['sites'] as $site)
        <p style="margin:6px 0"><strong>{{ $site->domain }}</strong>
          @if ($site->scores) <span class="small muted">@foreach (\App\Audit\Guidance::CATEGORIES as $k => $l){{ $l }} {{ $site->scores[$k] ?? 100 }}@if (! $loop->last) · @endif @endforeach</span>@else <span class="small muted">neverificat încă</span>@endif</p>
      @endforeach
    </div>
  @endif
</div>
