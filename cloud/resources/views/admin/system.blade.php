@extends('layouts.app')
@section('title', 'Sistem')
@section('content')
<div class="head"><div><h1>Sistem</h1><p>Versiunea platformei și actualizarea din arhivă .zip.</p></div></div>
@if (session('error'))<div class="alert alert-err">{{ session('error') }}</div>@endif
<div class="card">
  <dl class="kv">
    <dt>Versiune instalată</dt><dd><strong>{{ $version }}</strong>@if ($deployed !== $version) <span class="badge warn">migrări în curs (cron)</span>@endif</dd>
    <dt>PHP</dt><dd>{{ $php }}</dd>
    <dt>Limită upload</dt><dd>{{ number_format($uploadLimit / 1048576, 0) }} MB</dd>
  </dl>
</div>
<form class="card" method="post" action="{{ route('admin.system.upload') }}" enctype="multipart/form-data" onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Se actualizează… nu închide pagina'">
  @csrf
  <h2>Actualizare</h2>
  <p class="muted small">Se face automat backup la baza de date, apoi se înlocuiesc fișierele și se rulează migrările. <span class="mono">.env</span> și <span class="mono">storage/</span> nu se ating. Se acceptă doar o versiune mai nouă decât cea instalată.</p>
  <div class="fl"><label for="archive">Arhiva versiunii (vitim-ai-x.y.z.zip)</label><input id="archive" type="file" name="archive" accept=".zip" required>
    @error('archive')<div class="err">{{ $message }}</div>@enderror</div>
  <div class="fl"><label for="password">Parola ta (confirmare)</label><input id="password" type="password" name="password" required autocomplete="current-password">
    @error('password')<div class="err">{{ $message }}</div>@enderror</div>
  <button class="btn btn-p" type="submit">Actualizează</button>
  <p class="hint small muted" style="margin-top:10px">Dacă arhiva e mai mare decât limita de upload: urc-o cu File Manager în <span class="mono">vitim-ai/storage/app/updates/</span> și reîncarcă pagina.</p>
</form>
@if ($pending->isNotEmpty())
<div class="card">
  <h2>Arhive urcate prin File Manager</h2>
  @foreach ($pending as $zip)
    <form method="post" action="{{ route('admin.system.pending') }}" class="row" style="align-items:flex-end">
      @csrf <input type="hidden" name="name" value="{{ $zip['name'] }}">
      <div class="fl"><label>{{ $zip['name'] }} <span class="muted small">({{ number_format($zip['size'] / 1048576, 1) }} MB)</span></label>
        <input type="password" name="password" placeholder="Parola ta" required autocomplete="current-password"></div>
      <div class="fl"><button class="btn btn-p" type="submit">Aplică</button></div>
    </form>
  @endforeach
  @error('name')<div class="err">{{ $message }}</div>@enderror
</div>
@endif
@endsection
