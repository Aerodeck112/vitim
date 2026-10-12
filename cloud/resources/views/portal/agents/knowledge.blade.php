@extends('layouts.app')
@section('title', 'Ce știe din site · '.$agent->name)
@section('content')
@php($slug = $organization->slug)
@php($busy = $site && in_array($site->learn_status, ['queued', 'running'], true))
<div class="head"><div><h1>Ce știe {{ $agent->name }} din site</h1>
  <p>Agentul citește singur paginile publice ale site-ului (servicii, produse, prețuri, condiții) și caută în ele când un client întreabă.</p></div>
  <a class="btn" href="{{ route('portal.agents.edit', [$slug, $agent->id]) }}">Înapoi la agent</a></div>

@if (! $site)
  <div class="alert alert-warn">Agentul nu are un site ales. Alege site-ul în <a href="{{ route('portal.agents.edit', [$slug, $agent->id]) }}">setările agentului</a>, apoi revino aici.</div>
@else
<div class="kn-grid">
  <section class="card" aria-labelledby="st-h">
    <h2 id="st-h">{{ $site->domain }}</h2>
    <p class="kn-status">
      @switch($site->learn_status)
        @case('queued')<span class="badge warn">la rând</span> Citirea pornește în cel mult un minut.@break
        @case('running')<span class="badge warn">se citește</span> {{ $counts['pending'] }} pagini rămase. Pagina se actualizează singură.@break
        @case('done')<span class="badge ok">citit</span> ultima dată la {{ $site->learned_at?->setTimezone('Europe/Bucharest')->format('d.m.Y H:i') }}.@break
        @case('failed')<span class="badge err">nu s-a putut citi</span> {{ $site->learn_error }}@break
        @default<span class="badge">încă necitit</span> Apasă „Citește site-ul acum”.
      @endswitch
    </p>
    <div class="kn-kpis">
      <div class="kpi"><small>Pagini folosite</small><b>{{ number_format($counts['ok'], 0, ',', '.') }}</b></div>
      <div class="kpi"><small>Pagini de produs</small><b>{{ number_format($counts['products'], 0, ',', '.') }}</b></div>
      @if ($counts['shop'])<div class="kpi"><small>Produse din magazin</small><b>{{ number_format($counts['shop'], 0, ',', '.') }}</b><span class="muted small">sincronizate de plugin</span></div>@endif
      <div class="kpi"><small>Pagini scoase</small><b>{{ $counts['excluded'] }}</b></div>
    </div>
    @if ($canManage)
      <div class="kn-actions">
        <form method="post" action="{{ route('portal.agents.knowledge.learn', [$slug, $agent->id]) }}">@csrf<button class="btn btn-p" type="submit" @disabled($busy)>{{ $busy ? 'Se citește…' : 'Citește site-ul acum' }}</button></form>
        <form method="post" action="{{ route('portal.agents.knowledge.auto', [$slug, $agent->id]) }}" class="kn-auto">@csrf
          <input type="hidden" name="learn_auto" value="{{ $site->learn_auto ? 0 : 1 }}">
          <button type="submit" class="sw-btn{{ $site->learn_auto ? ' on' : '' }}" role="switch" aria-checked="{{ $site->learn_auto ? 'true' : 'false' }}">
            <span class="sw-track" aria-hidden="true"><span class="sw-knob"></span></span>
            <span class="sw-text"><span class="sw-label">Recitește automat săptămânal</span><span class="sw-state">{{ $site->learn_auto ? 'pornit' : 'oprit' }}</span></span>
          </button></form>
      </div>
    @endif
    <p class="hint small muted">Se citesc până la {{ \App\Knowledge\SiteLearner::MAX_PAGES }} de pagini publice, din sitemap și din linkurile site-ului. Coșul, contul de client și paginile blocate pentru roboți nu se citesc.</p>
  </section>

  <section class="card" aria-labelledby="tr-h">
    <h2 id="tr-h">Cum îl antrenezi</h2>
    <ol class="kn-steps">
      <li><b>Site-ul îl învață singur.</b> Ce e publicat pe site (servicii, produse, prețuri, livrare) agentul găsește la nevoie.</li>
      <li><b>Completează ce nu scrie pe site</b> în <a href="{{ route('portal.agents.edit', [$slug, $agent->id]) }}#business_facts">Informații despre firmă</a>: prețuri orientative, zone deservite, termene, întrebări frecvente. Acolo au prioritate față de site.</li>
      <li><b>Scoate paginile nepotrivite</b> (oferte expirate, pagini vechi) din lista de mai jos.</li>
      <li><b>Testează</b> mai jos sau în <a href="{{ route('portal.agents.test', [$slug, $agent->id]) }}">Testează agentul</a>, cu întrebări reale ale clienților.</li>
      <li><b>Urmărește conversațiile</b>: unde agentul nu știe, adaugă răspunsul în informațiile firmei sau pe site.</li>
    </ol>
  </section>
</div>

<section class="card" aria-labelledby="q-h">
  <h2 id="q-h">Întreabă ca un client</h2>
  <form method="get" class="toolbar" style="margin-bottom:10px">
    <input type="text" name="q" value="{{ $q }}" placeholder="ex. cât costă schimbul de ulei? / aveți canapele gri?" aria-label="Întrebare" style="flex:1">
    <button class="btn" type="submit">Vezi ce găsește</button>
  </form>
  @if ($q !== '')
    @if ($results === [] && $products === [])
      <p class="muted">Nu am găsit nimic pe site pentru această întrebare. Agentul va spune că nu are informația; adaug-o în informațiile firmei.</p>
    @else
      <p class="small muted">Asta găsește agentul pe site. Răspunsul către client îl formulează el, din aceste fragmente.</p>
      @if ($products)<ul class="kn-products">@foreach ($products as $p)<li><b>{{ $p['name'] }}</b>@if ($p['price']) · {{ $p['price'] }}@endif · <span class="badge {{ $p['in_stock'] ? 'ok' : 'err' }}">{{ $p['in_stock'] ? 'în stoc' : 'stoc epuizat' }}</span>@if ($p['url']) · <a href="{{ $p['url'] }}" target="_blank" rel="noopener">deschide</a>@endif</li>@endforeach</ul>@endif
      <ol class="kn-results">
        @foreach ($results as $r)
          <li><div><b>{{ $r['title'] }}</b>@if ($r['price']) <span class="badge">{{ $r['price'] }}</span>@endif</div>
            <a class="small mono" href="{{ $r['url'] }}" target="_blank" rel="noopener">{{ $r['url'] }}</a>
            <p>{{ \Illuminate\Support\Str::limit($r['excerpt'], 500) }}</p></li>
        @endforeach
      </ol>
    @endif
  @endif
</section>

<section class="card" aria-labelledby="p-h">
  <div class="kn-pages-head">
    <h2 id="p-h">Paginile citite</h2>
    <nav class="tabs-mini" aria-label="Filtru">
      @foreach (['' => 'Toate', 'product' => 'Produse', 'excluded' => 'Scoase'] as $k => $l)
        <a href="{{ route('portal.agents.knowledge', [$slug, $agent->id] + ($k !== '' ? ['f' => $k] : [])) }}" @class(['on' => $filter === $k])>{{ $l }}</a>
      @endforeach
    </nav>
  </div>
  @if ($pages->isEmpty())
    <p class="muted">{{ $busy ? 'Paginile apar aici pe măsură ce sunt citite.' : 'Nicio pagină încă.' }}</p>
  @else
  <div class="table-wrap"><table>
    <thead><tr><th>Pagină</th><th>Tip</th><th>Preț</th><th>Citită</th>@if ($canManage)<th></th>@endif</tr></thead>
    <tbody>
    @foreach ($pages as $p)
      <tr @class(['kn-off' => $p->excluded])>
        <td><b>{{ $p->title ?: '—' }}</b><br><a class="small mono" href="{{ $p->url }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit(preg_replace('#^https://#', '', $p->url), 80) }}</a></td>
        <td>@if ($p->status === 'pending')<span class="badge warn">la rând</span>@elseif ($p->kind === 'product')<span class="badge ok">produs</span>@else<span class="badge">pagină</span>@endif</td>
        <td class="small">{{ $p->price ?? '—' }}</td>
        <td class="small muted">{{ $p->fetched_at?->setTimezone('Europe/Bucharest')->format('d.m.Y') ?? '—' }}</td>
        @if ($canManage)<td>@if ($p->status === 'ok')<form method="post" action="{{ route('portal.agents.knowledge.page', [$slug, $agent->id, $p->id]) }}">@csrf<input type="hidden" name="excluded" value="{{ $p->excluded ? 0 : 1 }}"><button class="btn btn-s" type="submit">{{ $p->excluded ? 'Folosește din nou' : 'Scoate' }}</button></form>@endif</td>@endif
      </tr>
    @endforeach
    </tbody>
  </table></div>
  @include('partials.pager', ['paginator' => $pages])
  @endif
</section>
@if ($busy)<script>setTimeout(function () { if (!document.querySelector('input[name=q]:focus')) location.reload(); }, 15000);</script>@endif
@endif
@endsection
