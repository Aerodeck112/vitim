@extends('layouts.app')
@section('title', 'Analiză')
@section('content')
@php($slug = $organization->slug)
@php($lei = fn ($v) => number_format((float) $v, 0, ',', '.').' lei')
<div class="head"><div><h1>Analiză</h1><p>Cât aduc emailurile, SMS-urile și automatizările: venituri, implicare, audiență.</p></div>
  <form method="get" class="toolbar" style="margin:0"><label for="zile" class="small muted">Perioada</label>
    <select id="zile" name="zile" onchange="this.form.submit()" style="width:auto">@foreach ($periods as $k => $l)<option value="{{ $k }}" @selected($days === $k)>ultimele {{ $l }}</option>@endforeach</select></form></div>
@include('partials.marketing-tabs')

<div class="grid" style="margin-bottom:18px">
  <div class="kpi"><small>Venit atribuit marketingului</small><b>{{ $lei($revenue['email']) }}</b>
    <span class="muted small">{{ $revenue['total'] > 0 ? round(100 * $revenue['email'] / $revenue['total']).'% din vânzări' : 'fără comenzi încă' }}</span></div>
  <div class="kpi"><small>Vânzări magazin</small><b>{{ $lei($revenue['total']) }}</b><span class="muted small">{{ $revenue['orders'] === 1 ? '1 comandă' : $revenue['orders'].' comenzi' }} · medie {{ $lei($revenue['aov']) }}</span></div>
  <div class="kpi"><small>Emailuri trimise</small><b>{{ number_format($email['sent'], 0, ',', '.') }}</b><span class="muted small">deschise {{ $email['open_rate'] }}% · click {{ $email['click_rate'] }}%</span></div>
  <div class="kpi"><small>Abonați noi</small><b>{{ number_format($audience['subscribed'], 0, ',', '.') }}</b><span class="muted small">{{ $audience['forms'] }} din formulare · {{ $audience['unsubscribed'] }} dezabonări</span></div>
</div>

@php($max = max(1, max(array_map(fn ($d) => $d['email'] + $d['other'], $series))))
@php($n = count($series))
@php($w = 720)
@php($h = 220)
@php($bw = max(2, ($w - 2 * $n) / $n))
<div class="card viz">
  <h2>Venituri pe zi</h2>
  <div class="viz-legend small"><span><i class="sw sw-1"></i>atribuite emailurilor și SMS-urilor</span><span><i class="sw sw-2"></i>alte comenzi</span></div>
  @if ($revenue['total'] > 0)
  <svg viewBox="0 0 {{ $w }} {{ $h + 24 }}" role="img" aria-label="Venituri pe zi, ultimele {{ $periods[$days] }}" style="width:100%;height:auto;display:block">
    @foreach ([0.5, 1] as $g)<line x1="0" x2="{{ $w }}" y1="{{ $h - $h * $g }}" y2="{{ $h - $h * $g }}" class="grid-line"/><text x="0" y="{{ $h - $h * $g - 4 }}" class="axis">{{ $lei($max * $g) }}</text>@endforeach
    <line x1="0" x2="{{ $w }}" y1="{{ $h }}" y2="{{ $h }}" class="base-line"/>
    @foreach ($series as $day => $v)
      @php($x = $loop->index * ($bw + 2))
      @php($he = $v['email'] / $max * ($h - 18))
      @php($ho = $v['other'] / $max * ($h - 18))
      <g class="bar"><title>{{ \Illuminate\Support\Carbon::parse($day)->format('d.m') }}: {{ $lei($v['email']) }} atribuite, {{ $lei($v['other']) }} alte comenzi</title>
        <rect x="{{ $x }}" y="0" width="{{ $bw }}" height="{{ $h }}" class="hit"/>
        @if ($he > 0)<rect x="{{ $x }}" y="{{ $h - $he }}" width="{{ $bw }}" height="{{ $he }}" rx="{{ $ho > 0 ? 0 : min(4, $bw / 2) }}" class="s1"/>@endif
        @if ($ho > 0)<rect x="{{ $x }}" y="{{ $h - $he - $ho - ($he > 0 ? 2 : 0) }}" width="{{ $bw }}" height="{{ $ho }}" rx="{{ min(4, $bw / 2) }}" class="s2"/>@endif
      </g>
      @if ($loop->first || $loop->last || ($n > 14 && $loop->index % (int) ceil($n / 6) === 0))<text x="{{ $loop->first ? $x : ($loop->last ? $x + $bw : $x + $bw / 2) }}" y="{{ $h + 16 }}" text-anchor="{{ $loop->first ? 'start' : ($loop->last ? 'end' : 'middle') }}" class="axis">{{ \Illuminate\Support\Carbon::parse($day)->format('d.m') }}</text>@endif
    @endforeach
  </svg>
  @else<p class="muted small">Încă nu sunt comenzi în perioada aleasă. Conectează magazinul WooCommerce cu pluginul VITIM Connector 1.5.</p>@endif
</div>

<div class="grid" style="grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);align-items:start">
<div>
  <div class="card">
    <h2>Campanii</h2>
    @if ($campaigns->isEmpty())<p class="muted small">Nicio campanie trimisă în perioada aleasă.</p>@else
    <div class="table-wrap"><table>
      <thead><tr><th>Campanie</th><th>Trimise</th><th>Deschise</th><th>Click</th><th>Comenzi</th><th>Venit</th></tr></thead>
      <tbody>@foreach ($campaigns as $r)
        <tr><td><a href="{{ route('portal.campaigns.show', [$slug, $r['campaign']->id]) }}">{{ $r['campaign']->name }}</a> <span class="muted small">{{ $r['campaign']->channel->value }}</span></td>
          <td>{{ number_format($r['engagement']['sent'], 0, ',', '.') }}</td><td>{{ $r['campaign']->channel->value === 'email' ? $r['engagement']['open_rate'].'%' : '—' }}</td>
          <td>{{ $r['campaign']->channel->value === 'email' ? $r['engagement']['click_rate'].'%' : '—' }}</td><td>{{ $r['orders'] }}</td><td>{{ $lei($r['revenue']) }}</td></tr>
      @endforeach</tbody>
    </table></div>@endif
  </div>
  <div class="card">
    <h2>Automatizări</h2>
    @if ($flows->isEmpty())<p class="muted small">Nicio automatizare pornită. <a href="{{ route('portal.flows.index', $slug) }}">Pornește una</a> (bun venit, coș abandonat…).</p>@else
    <div class="table-wrap"><table>
      <thead><tr><th>Automatizare</th><th>Stare</th><th>Mesaje trimise</th><th>Venit atribuit</th></tr></thead>
      <tbody>@foreach ($flows as $r)
        <tr><td><a href="{{ route('portal.flows.show', [$slug, $r['flow']->id]) }}">{{ $r['flow']->name }}</a></td><td><span class="badge {{ $r['flow']->status === 'live' ? 'ok' : '' }}">{{ \App\Models\Flow::STATUSES[$r['flow']->status] }}</span></td>
          <td>{{ number_format($r['sent'], 0, ',', '.') }}</td><td>{{ $lei($r['revenue']) }}</td></tr>
      @endforeach</tbody>
    </table></div>@endif
    <p class="small muted" style="margin-bottom:0">Atribuire: o comandă se atribuie ultimului email pe care clientul a dat click (sau, altfel, pe care l-a deschis) în cele 5 zile dinaintea comenzii.</p>
  </div>
</div>
<div>
  <div class="card viz">
    <h2>Când deschid emailurile</h2>
    @php($hmax = max(1, max($sendTime['by_hour'])))
    @if ($sendTime['sample'] > 0)
    <svg viewBox="0 0 480 140" role="img" aria-label="Deschideri pe ore" style="width:100%;height:auto;display:block">
      <line x1="0" x2="480" y1="118" y2="118" class="base-line"/>
      @foreach ($sendTime['by_hour'] as $hour => $count)
        @php($bh = $count / $hmax * 104)
        <g class="bar"><title>{{ sprintf('%02d:00', $hour) }}: {{ $count }} deschideri</title><rect x="{{ $hour * 20 }}" y="0" width="18" height="118" class="hit"/>
          @if ($bh > 0)<rect x="{{ $hour * 20 }}" y="{{ 118 - $bh }}" width="18" height="{{ $bh }}" rx="4" class="{{ $hour === $sendTime['hour'] ? 's1' : 's1 dim' }}"/>@endif</g>
        @if ($hour % 6 === 0)<text x="{{ $hour === 0 ? 0 : $hour * 20 + 9 }}" y="134" text-anchor="{{ $hour === 0 ? 'start' : 'middle' }}" class="axis">{{ $hour }}:00</text>@endif
      @endforeach
    </svg>@endif
    <p class="small" style="margin-bottom:0">Ora recomandată pentru campanii: <strong>{{ sprintf('%02d:00', $sendTime['hour']) }}</strong>
      <span class="muted">{{ $sendTime['reliable'] ? '(din '.$sendTime['sample'].' deschideri, ultimele 180 de zile)' : '(implicit, până la '.\App\Services\SendTime::MIN_SAMPLE.' deschideri)' }}</span></p>
  </div>
  <div class="card">
    <h2>Predicții clienți</h2>
    @if ($risk->isEmpty())<p class="muted small">Apar după primele comenzi din magazin (se recalculează în fiecare noapte).</p>@else
    <dl class="kv">
      <dt>Valoare estimată totală</dt><dd><strong>{{ $lei($clv) }}</strong></dd>
      @foreach (['high' => ['mare', 'err'], 'medium' => ['mediu', 'warn'], 'low' => ['mic', 'ok']] as $k => [$label, $cls])
        <dt>Risc de pierdere {{ $label }}</dt><dd><span class="badge {{ $cls }}">{{ number_format((int) ($risk[$k] ?? 0), 0, ',', '.') }} clienți</span></dd>
      @endforeach
    </dl>
    <p class="small muted" style="margin-bottom:0">Folosește-le în <a href="{{ route('portal.audience.segment.create', $slug) }}">segmente</a> (condiția „Predicții”), de exemplu o campanie de recâștigare pentru clienții cu risc mare.</p>@endif
  </div>
</div>
</div>
@endsection
