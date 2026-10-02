@extends('layouts.app')
@section('title', 'Brand')
@section('content')
@php($slug = $organization->slug)
@include('partials.marketing-tabs')
<div class="head"><div><h1>Brandul firmei</h1><p>Logo-ul, culoarea, fontul și linkurile de mai jos apar automat în toate emailurile create cu editorul vizual.</p></div></div>
<div class="grid" style="grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);align-items:start">
<form method="post" action="{{ route('portal.brand.save', $slug) }}" enctype="multipart/form-data" class="card">
  @csrf @method('put')
  <h2>Logo</h2>
  @if (! empty($brand['logo_url']))<div style="margin-bottom:10px;padding:12px;background:#fff;border:1px solid var(--border);border-radius:10px;display:inline-block"><img src="{{ $brand['logo_url'] }}" alt="Logo" style="max-width:180px;max-height:72px;display:block"></div>@endif
  <div class="fl"><label for="logo">Încarcă logo-ul (PNG, JPG, GIF sau WebP, max. 2 MB)</label><input id="logo" type="file" name="logo" accept="image/png,image/jpeg,image/gif,image/webp">@error('logo')<div class="err">{{ $message }}</div>@enderror</div>
  <div class="fl"><label for="logo_url">sau adresa logo-ului (https://…)</label><input id="logo_url" type="url" name="logo_url" value="{{ old('logo_url', $brand['logo_url'] ?? '') }}" maxlength="500">@error('logo_url')<div class="err">{{ $message }}</div>@enderror</div>

  <h2 style="margin-top:18px">Aspect</h2>
  <div class="row">
    <div class="fl"><label for="color">Culoarea principală (butoane, linkuri)</label>
      <div style="display:flex;gap:8px;align-items:center"><input id="color-pick" type="color" value="{{ old('color', $brand['color'] ?? '#2f6bff') }}" style="width:48px;height:38px;padding:2px" aria-label="Alege culoarea"><input id="color" type="text" name="color" value="{{ old('color', $brand['color'] ?? '#2f6bff') }}" maxlength="7" pattern="#[0-9a-fA-F]{6}"></div>
      @error('color')<div class="err">Culoarea trebuie să fie de forma #2f6bff.</div>@enderror</div>
    <div class="fl"><label for="font">Font</label><select id="font" name="font">
      @foreach (['Arial, Helvetica, sans-serif' => 'Arial (modern, simplu)', 'Georgia, serif' => 'Georgia (elegant)', 'Verdana, sans-serif' => 'Verdana (foarte lizibil)', 'Trebuchet MS, sans-serif' => 'Trebuchet (prietenos)'] as $value => $label)
        <option value="{{ $value }}" @selected(old('font', $brand['font'] ?? 'Arial, Helvetica, sans-serif') === $value)>{{ $label }}</option>
      @endforeach</select></div>
  </div>

  <h2 style="margin-top:18px">Linkuri (blocul „Rețele sociale”)</h2>
  @foreach (['website' => 'Site', 'facebook' => 'Facebook', 'instagram' => 'Instagram'] as $key => $label)
    <div class="fl"><label for="{{ $key }}">{{ $label }}</label><input id="{{ $key }}" type="url" name="{{ $key }}" value="{{ old($key, $brand[$key] ?? '') }}" placeholder="https://…" maxlength="300">@error($key)<div class="err">Adresa trebuie să înceapă cu https://</div>@enderror</div>
  @endforeach
  <button class="btn btn-p" type="submit">Salvează brandul</button>
</form>
<div class="card">
  <h2>Unde se folosește</h2>
  <ul class="small" style="padding-left:18px;margin:0">
    <li>blocul <strong>Logo</strong> afișează logo-ul de aici;</li>
    <li>butoanele și linkurile au culoarea principală (un buton poate avea și culoarea lui);</li>
    <li>blocul <strong>Rețele sociale</strong> pune linkurile de mai sus;</li>
    <li>datele firmei din subsolul emailului vin din Setări.</li>
  </ul>
  <p class="small muted">Deschide editorul dintr-o campanie de email („Editor vizual”) sau dintr-un pas de email al unei automatizări.</p>
</div>
</div>
<script>
(function () {
  var pick = document.getElementById('color-pick'), text = document.getElementById('color');
  pick.addEventListener('input', function () { text.value = pick.value; });
  text.addEventListener('input', function () { if (/^#[0-9a-fA-F]{6}$/.test(text.value)) pick.value = text.value; });
})();
</script>
@endsection
