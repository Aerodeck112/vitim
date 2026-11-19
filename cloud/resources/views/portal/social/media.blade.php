@extends('layouts.app')
@section('title', 'Materiale social media')
@section('content')
@php($slug = $organization->slug)
@if (session('error'))<div class="alert alert-err" role="alert">{{ session('error') }}</div>@endif
<div class="head"><div><h1>Materiale și cercetare</h1>
  <p><a href="{{ route('portal.social', $slug) }}">← planul săptămânii</a> · De aici învață agentul: fotografiile firmei (din site, din pagina de Facebook / Instagram sau încărcate de tine) și postările deja publicate, din care ia stilul și temele care au mers.</p></div></div>

<div class="card">
  <h2>Folderul de Google Drive al firmei</h2>
  @if (! \App\Social\DriveLibrary::configured())
    <p class="muted">Google Drive nu este configurat încă pentru platformă.</p>
  @else
    <p class="small muted">Pune fotografiile și clipurile video ale firmei într-un folder de Google Drive. Partajează folderul cu adresa de mai jos, ca „Cititor”, apoi lipește aici linkul folderului. Folderul rămâne privat: îl vede doar platforma. Subfolderele (de exemplu câte unul pe lucrare) se citesc și ele, iar numele lor ajută agentul să aleagă materialul potrivit.</p>
    <p>Partajează cu: <strong class="mono">{{ \App\Social\DriveLibrary::email() }}</strong></p>
    @if ($drive)
      <p><span class="badge {{ ['ok' => 'ok', 'error' => 'err'][$drive->status] ?? 'warn' }}">{{ ['ok' => 'conectat', 'error' => 'eroare', 'new' => 'necitit încă'][$drive->status] }}</span>
        @if ($drive->synced_at) ultima citire: {{ $drive->synced_at->setTimezone('Europe/Bucharest')->format('d.m.Y H:i') }}@endif
        @if ($drive->last_error)<br><span class="small" style="color:var(--err)">{{ $drive->last_error }}</span>@endif</p>
    @endif
    <form method="post" action="{{ route('portal.social.drive', $slug) }}" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">@csrf @method('put')
      <div class="fl" style="margin:0;flex:1;min-width:260px"><label for="folder">Linkul folderului</label><input id="folder" type="text" name="folder" required value="{{ old('folder', $drive ? 'https://drive.google.com/drive/folders/'.$drive->config['folder_id'] : '') }}" placeholder="https://drive.google.com/drive/folders/…">@error('folder')<div class="err">{{ $message }}</div>@enderror</div>
      <button class="btn" type="submit">Salvează</button></form>
    @if ($drive)
      <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
        <form method="post" action="{{ route('portal.social.drive.sync', $slug) }}" onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Se aduc fișierele…'">@csrf<button class="btn btn-p" type="submit">Adu fișierele din Drive</button></form>
        <form method="post" action="{{ route('portal.social.drive.disconnect', $slug) }}">@csrf @method('delete')<button class="btn" type="submit">Deconectează folderul</button></form>
      </div>
      <p class="small muted" style="margin-top:8px">Se aduc fotografii JPG, PNG, WebP (până la 8 MB) și clipuri MP4, MOV, WebM (până la 80 MB, cel mult {{ \App\Social\MediaLibrary::MAX_VIDEOS }} clipuri). Agentul recitește folderul și singur, înainte de planul săptămânii.</p>
    @endif
  @endif
</div>

<div class="card">
  <h2>Pagina de Facebook și contul de Instagram</h2>
  <p class="small muted">Agentul citește pagina prin interfața oficială Meta, cu un cod de acces al paginii (Page access token) creat de un administrator al paginii. Citește doar postările publicate, pozele lor și numărul de reacții. Nu publică și nu trimite mesaje. Codul se păstrează criptat și nu mai este afișat.</p>
  @if ($connection)
    <p><span class="badge {{ ['ok' => 'ok', 'error' => 'err'][$connection->status] ?? 'warn' }}">{{ ['ok' => 'conectată', 'error' => 'eroare', 'new' => 'necitită încă'][$connection->status] }}</span>
      @if ($connection->synced_at) ultima citire: {{ $connection->synced_at->setTimezone('Europe/Bucharest')->format('d.m.Y H:i') }} · @endif {{ $referenceCount }} postări citite
      @if ($connection->last_error)<br><span class="small" style="color:var(--err)">{{ $connection->last_error }}</span>@endif</p>
  @endif
  <form method="post" action="{{ route('portal.social.meta', $slug) }}">@csrf @method('put')
    <div class="row">
      <div class="fl"><label for="page_id">ID-ul paginii de Facebook</label><input id="page_id" type="text" name="page_id" inputmode="numeric" required value="{{ old('page_id', $pageId) }}"></div>
      <div class="fl"><label for="ig_id">ID-ul contului de Instagram (opțional)</label><input id="ig_id" type="text" name="ig_id" inputmode="numeric" value="{{ old('ig_id', $igId) }}"></div>
    </div>
    <div class="fl"><label for="token">Codul de acces al paginii</label><input id="token" type="password" name="token" autocomplete="off" @required(! $connection) placeholder="{{ $connection ? 'lasă gol ca să rămână cel salvat' : '' }}"></div>
    <button class="btn" type="submit">Salvează</button>
  </form>
  @if ($connection)
    <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
      <form method="post" action="{{ route('portal.social.meta.sync', $slug) }}" onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Se citește…'">@csrf<button class="btn btn-p" type="submit">Citește pagina acum</button></form>
      <form method="post" action="{{ route('portal.social.meta.disconnect', $slug) }}" onsubmit="return confirm('Ștergi legătura cu Meta?')">@csrf @method('delete')<button class="btn" type="submit">Șterge legătura</button></form>
    </div>
  @endif
  @if ($references->isNotEmpty())
    <h2 style="margin-top:16px">Ce a mers cel mai bine</h2>
    @foreach ($references as $r)
      <p class="small" style="margin:0 0 6px"><span class="badge">{{ $r->network }}</span> <strong>{{ $r->engagement }} reacții</strong> · {{ \Illuminate\Support\Str::limit((string) $r->message, 160) ?: 'postare fără text' }} @if ($r->permalink)<a href="{{ $r->permalink }}" target="_blank" rel="noopener noreferrer">deschide</a>@endif</p>
    @endforeach
  @endif
</div>

<div class="card">
  <h2>Fotografii și clipuri ({{ $assets->total() }} din cel mult {{ \App\Social\MediaLibrary::MAX_ASSETS }})</h2>
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:12px">
    @foreach ($sites as $site)
      <form method="post" action="{{ route('portal.social.media.collect', $slug) }}" onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Se caută fotografii…'">@csrf<input type="hidden" name="site" value="{{ $site->id }}"><button class="btn" type="submit">Adună fotografii din {{ $site->domain }}</button></form>
    @endforeach
    <form method="post" action="{{ route('portal.social.media.upload', $slug) }}" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:flex-end">@csrf
      <div class="fl" style="margin:0"><label for="photos">Încarcă fotografii (JPG, PNG, WebP; cel mult 10 odată)</label><input id="photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required></div>
      <button class="btn" type="submit">Încarcă</button></form>
  </div>
  <p class="small muted">Folosește doar fotografii ale firmei sau pentru care firma are drept de folosire.</p>
  @if ($assets->isEmpty())
    <p class="muted">Nicio fotografie încă. Adună-le din site, conectează pagina de Facebook sau încarcă-le de pe dispozitiv.</p>
  @else
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px">
      @foreach ($assets as $asset)
        <figure style="margin:0">
          @if ($asset->kind === 'video')
            <video src="{{ route('portal.social.media.file', [$slug, $asset->id]) }}#t=0.5" preload="metadata" muted playsinline controls style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px;display:block;background:#000" aria-label="{{ $asset->title ?: 'Clip video' }}"></video>
          @else
          <img src="{{ route('portal.social.media.file', [$slug, $asset->id]) }}" alt="{{ $asset->title ?: 'Fotografie' }}" loading="lazy" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px;display:block">
          @endif
          <figcaption class="small muted" style="margin-top:4px">{{ ['site' => 'din site', 'meta' => 'din Meta', 'upload' => 'încărcată', 'drive' => 'din Drive'][$asset->source] ?? $asset->source }} · {{ $asset->kind === 'video' ? 'clip, '.round($asset->bytes / 1_048_576, 1).' MB' : $asset->width.'×'.$asset->height }}@if ($asset->title)<br>{{ \Illuminate\Support\Str::limit($asset->title, 40) }}@endif
            <form method="post" action="{{ route('portal.social.media.delete', [$slug, $asset->id]) }}" style="display:inline">@csrf @method('delete')<button type="submit" class="btn btn-s" aria-label="Șterge fotografia">șterge</button></form></figcaption>
        </figure>
      @endforeach
    </div>
    @include('partials.pager', ['paginator' => $assets])
  @endif
</div>
@endsection
