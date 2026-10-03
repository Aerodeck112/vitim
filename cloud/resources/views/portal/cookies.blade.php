@extends('layouts.app')
@section('title', 'Cookie-uri')
@section('content')
@php($slug = $organization->slug)
@php($s = $settings)
<div class="head"><div><h1>Cookie-uri</h1><p>Bannerul de consimțământ pentru site-ul tău, conform Legii 506/2004 și GDPR: vizitatorul alege ce acceptă, iar alegerea se păstrează ca dovadă.</p></div>
  @if ($sites->count() > 1)<form method="get" class="toolbar" style="margin:0"><label for="site" class="small muted">Site</label><select id="site" name="site" onchange="this.form.submit()" style="width:auto">@foreach ($sites as $x)<option value="{{ $x->id }}" @selected($site?->id === $x->id)>{{ $x->domain }}</option>@endforeach</select></form>@endif</div>

@if (! $site)
  <div class="card"><p>Adaugă întâi un site în <a href="{{ route('portal.settings', $slug) }}">Setări</a>.</p></div>
@else
@php($total = $stats->sum('n'))
<div class="grid" style="margin-bottom:18px">
  <div class="kpi"><small>Stare pe {{ $site->domain }}</small><b>@if ($s['enabled'])<span class="badge ok" style="font-size:16px">activ</span>@else<span class="badge" style="font-size:16px">oprit</span>@endif</b><span class="muted small">versiunea politicii: {{ $s['version'] }}</span></div>
  <div class="kpi"><small>Alegeri în ultimele 30 de zile</small><b>{{ number_format($total, 0, ',', '.') }}</b></div>
  <div class="kpi"><small>Au acceptat marketingul</small><b>{{ $total ? round(100 * $stats->sum('m') / $total) : 0 }}%</b><span class="muted small">statistici: {{ $total ? round(100 * $stats->sum('s') / $total) : 0 }}%</span></div>
  <div class="kpi"><small>Au refuzat tot</small><b>{{ $total ? round(100 * (int) ($stats->get('reject_all')?->n ?? 0) / $total) : 0 }}%</b></div>
</div>

<div class="grid" style="grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);align-items:start">
<form method="post" action="{{ route('portal.cookies.update', [$slug, $site->id]) }}" class="card">
  @csrf @method('put')
  <label class="chk" style="font-size:15px"><input type="checkbox" name="enabled" value="1" @checked($s['enabled'])> <strong>Afișează bannerul VITIM de cookie-uri pe {{ $site->domain }}</strong></label>
  <p class="small muted">Dacă site-ul are deja alt banner (CookieYes, Complianz etc.), dezactivează-l pe acela ca să nu apară două.</p>

  <h2 style="margin-top:16px">Aspect și texte</h2>
  <div class="row">
    <div class="fl"><label for="layout">Format</label><select id="layout" name="layout"><option value="bar" @selected($s['layout'] === 'bar')>bandă jos, pe toată lățimea</option><option value="box" @selected($s['layout'] === 'box')>casetă în colț</option></select></div>
    <div class="fl"><label for="position">Colț (casetă și butonul 🍪)</label><select id="position" name="position"><option value="left" @selected($s['position'] === 'left')>stânga</option><option value="right" @selected($s['position'] === 'right')>dreapta</option></select></div>
    <div class="fl"><label for="color">Culoare butoane</label><input id="color" type="color" name="color" value="{{ $s['color'] }}" style="height:38px;padding:2px"></div>
  </div>
  <div class="fl"><label for="title">Titlu</label><input id="title" type="text" name="title" maxlength="80" value="{{ $s['title'] }}"></div>
  <div class="fl"><label for="text">Text</label><textarea id="text" name="text" rows="3" maxlength="600">{{ $s['text'] }}</textarea></div>
  <div class="row">
    <div class="fl"><label for="policy_url">Pagina „Politica de cookie-uri”</label><input id="policy_url" type="url" name="policy_url" value="{{ $s['policy_url'] }}" placeholder="https://{{ $site->domain }}/politica-cookies">@error('policy_url')<div class="err">{{ $message }}</div>@enderror</div>
    <div class="fl"><label for="privacy_url">Pagina „Politica de confidențialitate”</label><input id="privacy_url" type="url" name="privacy_url" value="{{ $s['privacy_url'] }}" placeholder="https://{{ $site->domain }}/confidentialitate">@error('privacy_url')<div class="err">{{ $message }}</div>@enderror</div>
  </div>

  <h2 style="margin-top:16px">Ce folosește site-ul</h2>
  <p class="small muted" style="margin-top:-6px">Cookie-urile VITIM (chat, formulare, recunoașterea abonaților), WordPress și WooCommerce se adaugă automat. Bifează serviciile externe instalate pe site:</p>
  <div class="grid" style="grid-template-columns:1fr 1fr;gap:2px 14px">
    @foreach (\App\Services\CookieSettings::SERVICES as $key => [$name, $provider, $category])
      <label class="chk"><input type="checkbox" name="services[]" value="{{ $key }}" @checked(in_array($key, $s['services'], true))> {{ $name }} <span class="muted small">· {{ \App\Services\CookieSettings::CATEGORIES[$category][0] }}</span></label>
    @endforeach
  </div>
  <details style="margin:10px 0" @if ($s['custom']) open @endif><summary style="cursor:pointer;font-weight:600">Alt serviciu, care nu e în listă</summary>
    @foreach (array_merge($s['custom'], [[]]) as $i => $c)
      <div class="row" style="margin-top:8px">
        <div class="fl" style="margin:0"><input type="text" name="custom[{{ $i }}][name]" value="{{ $c['name'] ?? '' }}" placeholder="Serviciu (ex. Smartsupp)" aria-label="Serviciu"></div>
        <div class="fl" style="margin:0"><input type="text" name="custom[{{ $i }}][provider]" value="{{ $c['provider'] ?? '' }}" placeholder="Furnizor" aria-label="Furnizor"></div>
        <div class="fl" style="margin:0"><select name="custom[{{ $i }}][category]" aria-label="Categorie">@foreach (\App\Services\CookieSettings::CATEGORIES as $k => [$l]) @if ($k !== 'necessary')<option value="{{ $k }}" @selected(($c['category'] ?? 'marketing') === $k)>{{ $l }}</option>@endif @endforeach<option value="necessary" @selected(($c['category'] ?? '') === 'necessary')>Strict necesare</option></select></div>
      </div>
      <div class="row"><div class="fl" style="margin:4px 0 0"><input type="text" name="custom[{{ $i }}][cookies]" value="{{ $c['cookies'] ?? '' }}" placeholder="Cookie-uri (ex. ssupp.vid)" aria-label="Cookie-uri"></div>
        <div class="fl" style="margin:4px 0 0"><input type="text" name="custom[{{ $i }}][duration]" value="{{ $c['duration'] ?? '' }}" placeholder="Durată (ex. 6 luni)" aria-label="Durată"></div></div>
    @endforeach
    <p class="small muted">Salvează ca să apară un rând nou gol.</p>
  </details>

  <h2 style="margin-top:16px">Reguli</h2>
  <div class="fl"><label for="consent_days">Alegerea vizitatorului se păstrează</label><select id="consent_days" name="consent_days" style="width:auto">@foreach ([90 => '3 luni', 180 => '6 luni (recomandat)', 365 => '12 luni'] as $d => $l)<option value="{{ $d }}" @selected($s['consent_days'] === $d)>{{ $l }}</option>@endforeach</select></div>
  <label class="chk"><input type="checkbox" name="reopen" value="1" @checked($s['reopen'])> Buton mic 🍪 în colț, ca vizitatorul să-și poată schimba alegerea oricând</label>
  <label class="chk"><input type="checkbox" name="gcm" value="1" @checked($s['gcm'])> Google Consent Mode v2 (Google Analytics și Google Ads respectă automat alegerea)</label>
  <label class="chk"><input type="checkbox" name="reconsent" value="1"> Întreabă din nou toți vizitatorii (bifează după ce adaugi un serviciu nou de statistici sau marketing)</label>
  <div style="margin-top:14px"><button class="btn btn-p" type="submit">Salvează</button></div>
</form>

<div>
  <div class="card">
    <h2>Instalare</h2>
    @if ($site->platform === 'wordpress')
      <p class="small">Pe WordPress, pluginul <strong>VITIM Connector 1.6</strong> pune singur bannerul și Google Consent Mode. Pentru pagina cu politica de cookie-uri, adaugă într-o pagină blocul „Shortcode” cu <code class="mono">[vitim_cookies]</code>.</p>
    @else
      <p class="small">Pune acest cod cât mai sus în <code>&lt;head&gt;</code>, înaintea Google Analytics / Tag Manager:</p>
      <pre class="mono small" style="white-space:pre-wrap;word-break:break-all;background:var(--panel-2);padding:10px;border-radius:8px;max-height:140px;overflow:auto;user-select:all">{{ $snippet }}</pre>
      <p class="small">Scriptul VITIM (din Setări → cod de instalare) afișează bannerul. Politica de cookie-uri: <code class="mono">&lt;div data-vitim-cookie-policy&gt;&lt;/div&gt;</code> în pagina ei.</p>
    @endif
    <p class="small"><strong>Scripturile care trebuie să aștepte acordul</strong> (Meta Pixel, TikTok, Hotjar etc., dacă nu folosesc Google Consent Mode) se marchează așa:</p>
    <pre class="mono small" style="white-space:pre-wrap;background:var(--panel-2);padding:10px;border-radius:8px">&lt;script type="text/plain" data-vitim-consent="marketing"&gt;…&lt;/script&gt;</pre>
    <p class="small muted" style="margin-bottom:0">Categorii: <code>preferences</code>, <code>statistics</code>, <code>marketing</code>. Iframe-urile (YouTube, hărți) primesc <code>data-vitim-consent</code> și <code>data-src</code> în loc de <code>src</code>. Orice link <code>href="#vitim-cookies"</code> deschide setările.</p>
  </div>
  <div class="card">
    <h2>Cookie-urile site-ului</h2>
    @foreach (\App\Services\CookieSettings::CATEGORIES as $key => [$label])
      @if (! empty($table[$key]))
        <div class="lbl" style="margin-top:8px">{{ $label }}</div>
        @foreach ($table[$key] as $r)<div class="small" style="padding:3px 0;border-bottom:1px solid var(--border)">{{ $r['name'] }} <span class="muted mono">{{ $r['cookies'] }}</span> <span class="muted">· {{ $r['duration'] }}</span></div>@endforeach
      @endif
    @endforeach
  </div>
</div>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap"><h2 style="margin:0">Registrul consimțămintelor</h2>
    <a class="btn btn-s" href="{{ route('portal.cookies.export', [$slug, $site->id]) }}">Descarcă tot (CSV)</a></div>
  <p class="small muted">Dovada cerută la un control ANSPDCP: ce a ales fiecare vizitator, când, pe ce pagină și pentru ce versiune a politicii. IP-ul nu se păstrează în clar.</p>
  @if ($recent->isEmpty())<p class="small muted">Încă nicio alegere înregistrată.</p>@else
  <div class="table-wrap"><table>
    <thead><tr><th>Când</th><th>Alegere</th><th>Preferințe</th><th>Statistici</th><th>Marketing</th><th>Pagina</th></tr></thead>
    <tbody>@foreach ($recent as $r)
      <tr><td class="small">{{ $r->created_at->setTimezone('Europe/Bucharest')->format('d.m.Y H:i') }}</td><td>{{ \App\Models\CookieConsent::ACTIONS[$r->action] ?? $r->action }}</td>
        @foreach (['preferences', 'statistics', 'marketing'] as $k)<td>{!! $r->$k ? '<span class="badge ok">da</span>' : '<span class="badge">nu</span>' !!}</td>@endforeach
        <td class="small muted" style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $r->page }}</td></tr>
    @endforeach</tbody>
  </table></div>@endif
</div>
@endif
@endsection
