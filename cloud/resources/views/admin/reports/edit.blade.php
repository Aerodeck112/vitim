@extends('layouts.app')
@section('title', 'Raport '.$preview['label'])
@section('content')
@php($slug = $organization->slug)
<div class="head"><div><h1>Raport {{ $preview['label'] }}</h1>
  <p>{{ $organization->name }} · @if ($report?->isPublished())<span class="badge ok">publicat {{ $report->published_at->format('d.m.Y H:i') }}</span>@else<span class="badge warn">nepublicat</span>@endif</p></div>
  <a class="btn" href="{{ route('admin.reports.index', $slug) }}">Toate rapoartele</a></div>
@if (! $services)
  <div class="alert alert-warn">Clientul nu are servicii active. Bifează-le în <a href="{{ route('admin.reports.index', $slug) }}">Servicii și rapoarte</a>.</div>
@endif
<form method="post" action="{{ route('admin.reports.update', [$slug, $period]) }}">
  @csrf @method('put')
  @foreach ($services as $service)
    @if ($metrics = \App\Reports\ServiceCatalog::metrics($service))
      <div class="card">
        <h2>{{ \App\Reports\ServiceCatalog::label($service) }}</h2>
        <div class="grid">
          @foreach ($metrics as $key => [$label, $unit])
            <div class="fl"><label for="m-{{ $service }}-{{ $key }}">{{ $label }}{{ $unit ? " ({$unit})" : '' }}</label>
              <input id="m-{{ $service }}-{{ $key }}" type="number" step="any" min="0" name="data[{{ $service }}][metrics][{{ $key }}]" value="{{ old("data.{$service}.metrics.{$key}", $report?->data[$service]['metrics'][$key] ?? '') }}">
              @error("data.{$service}.metrics.{$key}")<div class="err">{{ $message }}</div>@enderror</div>
          @endforeach
        </div>
        <div class="fl"><label for="s-{{ $service }}">Ce am făcut și ce rezultate (vizibil clientului)</label>
          <textarea id="s-{{ $service }}" name="data[{{ $service }}][summary]" maxlength="5000">{{ old("data.{$service}.summary", $report?->data[$service]['summary'] ?? '') }}</textarea></div>
      </div>
    @endif
  @endforeach
  <div class="card">
    <h2>Rezumatul lunii</h2>
    <p class="small muted" style="margin-top:-6px">Mentenanța (actualizări, backup-uri, probleme rezolvate) se calculează automat din activitatea pe site-uri.</p>
    <textarea name="summary" maxlength="5000" placeholder="Ex: Luna aceasta am actualizat site-ul, am rezolvat problemele de GDPR și am crescut afișările în Google cu 18%.">{{ old('summary', $report?->summary) }}</textarea>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px">
    <button class="btn" type="submit">Salvează ciorna</button>
    <button class="btn btn-p" type="submit" name="publish" value="1" onclick="return confirm('Publici raportul și îl trimiți clientului pe email?')">{{ $report?->isPublished() ? 'Republică și retrimite' : 'Publică și trimite clientului' }}</button>
  </div>
</form>
@if ($report?->isPublished())
  <form method="post" action="{{ route('admin.reports.unpublish', [$slug, $period]) }}" style="margin:-12px 0 24px">@csrf<button class="linkbtn" type="submit">Retrage publicarea</button></form>
@endif
<h2>Previzualizare (cum o vede clientul)</h2>
@include('partials.report', ['r' => $preview])
@endsection
