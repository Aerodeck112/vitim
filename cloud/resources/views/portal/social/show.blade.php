@extends('layouts.app')
@section('title', 'Postare '.$post->day->format('d.m'))
@section('content')
@php($slug = $organization->slug)
@if (session('error'))<div class="alert alert-err" role="alert">{{ session('error') }}</div>@endif
<div class="head">
  <div><h1>Postarea din {{ $post->day->format('d.m.Y') }}</h1>
    <p><a href="{{ route('portal.social', [$slug, 's' => \App\Social\SocialPlanner::monday($post->day->toDateString())->toDateString()]) }}">← planul săptămânii</a>
      · <span class="badge {{ ['approved' => 'ok', 'published' => 'ok', 'skipped' => 'err'][$post->status] ?? 'warn' }}">{{ \App\Models\SocialPost::STATUSES[$post->status] }}</span>
      @if ($post->source === 'ai') · <span class="small muted">scrisă de agent, de verificat de un om</span>@endif</p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn" type="button" id="copy">Copiază textul</button>
    @if ($post->status !== 'approved' && $post->status !== 'published')<form method="post" action="{{ route('portal.social.status', [$slug, $post->id]) }}">@csrf<input type="hidden" name="status" value="approved"><button class="btn btn-p" type="submit">Aprobă</button></form>@endif
    @if ($post->status === 'approved')<form method="post" action="{{ route('portal.social.status', [$slug, $post->id]) }}">@csrf<input type="hidden" name="status" value="published"><button class="btn btn-p" type="submit">Am publicat-o</button></form>@endif
  </div>
</div>

<form method="post" action="{{ route('portal.social.update', [$slug, $post->id]) }}" class="card" id="post-form">
  @csrf @method('put')
  <h2>Textul</h2>
  <div class="row">
    <div class="fl"><label for="topic">Tema</label><input id="topic" type="text" name="topic" maxlength="150" required value="{{ old('topic', $post->topic) }}"></div>
    <div class="fl"><label for="day">Ziua</label><input id="day" type="date" name="day" required value="{{ old('day', $post->day->toDateString()) }}"></div>
  </div>
  <div class="fl"><label for="hook">Titlul de pe imagine (cârligul)</label><input id="hook" type="text" name="hook" maxlength="190" required value="{{ old('hook', $post->hook) }}"></div>
  <div class="fl"><label for="caption">Textul postării (Facebook, Instagram, descrierea de pe TikTok)</label><textarea id="caption" name="caption" maxlength="2200" required style="min-height:150px">{{ old('caption', $post->caption) }}</textarea></div>
  <div class="fl"><label for="hashtags">Hashtaguri</label><input id="hashtags" type="text" name="hashtags" maxlength="490" value="{{ old('hashtags', $post->hashtags) }}"></div>
  <div class="fl"><label for="visual">Ideea vizuală (ce fotografie sau filmare se potrivește)</label><textarea id="visual" name="visual" maxlength="1000">{{ old('visual', $post->visual) }}</textarea></div>
  @if ($assets->isNotEmpty())
  <div class="fl"><label>Fotografia sau clipul postării (din materialele firmei)</label>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <label style="cursor:pointer"><input type="radio" name="asset_id" value="" @checked(! $post->asset_id)> <span class="small">fără</span></label>
      @foreach ($assets as $asset)
        <label style="cursor:pointer" title="{{ $asset->title }}"><input type="radio" name="asset_id" value="{{ $asset->id }}" @checked($post->asset_id === $asset->id) style="position:absolute;opacity:0" data-asset>
          @if ($asset->kind === 'video')<video src="{{ route('portal.social.media.file', [$slug, $asset->id]) }}#t=0.5" preload="metadata" muted playsinline width="84" height="84" style="object-fit:cover;border-radius:8px;display:block;border:3px solid {{ $post->asset_id === $asset->id ? 'var(--brand)' : 'transparent' }}" aria-label="{{ $asset->title ?: 'Clip video' }}"></video></label>
          @else          <img src="{{ route('portal.social.media.file', [$slug, $asset->id]) }}" alt="{{ $asset->title ?: 'Fotografie' }}" loading="lazy" width="84" height="84" style="object-fit:cover;border-radius:8px;display:block;border:3px solid {{ $post->asset_id === $asset->id ? 'var(--brand)' : 'transparent' }}"></label>
          @endif
      @endforeach
    </div></div>
  @endif
  <h2 style="margin-top:14px">Scenariul video (TikTok / Reels)</h2>
  <p class="small muted">Fiecare scenă: textul de pe ecran, ce se filmează sau ce fotografie se pune, și câte secunde durează. Scenele goale se șterg la salvare.</p>
  @php($scenes = array_pad(old('scenes', $post->scenes ?? []), max(5, count($post->scenes ?? [])), ['text' => '', 'shot' => '', 'seconds' => 4]))
  @foreach ($scenes as $i => $scene)
    <div style="display:grid;grid-template-columns:2fr 3fr 80px;gap:8px;margin-bottom:8px">
      <input type="text" name="scenes[{{ $i }}][text]" maxlength="140" value="{{ $scene['text'] ?? '' }}" placeholder="Scena {{ $i + 1 }}: text pe ecran" aria-label="Scena {{ $i + 1 }}: text pe ecran" data-scene-text>
      <input type="text" name="scenes[{{ $i }}][shot]" maxlength="300" value="{{ $scene['shot'] ?? '' }}" placeholder="ce se filmează" aria-label="Scena {{ $i + 1 }}: ce se filmează">
      <input type="number" name="scenes[{{ $i }}][seconds]" min="2" max="10" value="{{ $scene['seconds'] ?? 4 }}" aria-label="Scena {{ $i + 1 }}: secunde" data-scene-seconds>
    </div>
  @endforeach
  <h2 style="margin-top:14px">Textul vocii</h2>
  <div class="fl"><label for="voiceover">Ce spune vocea în video (numerele scrise în litere, cum se pronunță)</label><textarea id="voiceover" name="voiceover" maxlength="{{ \App\Social\VoiceStudio::MAX_TEXT }}" style="min-height:110px">{{ old('voiceover', $post->voiceover) }}</textarea></div>
  <input type="hidden" name="design" id="design" value="">
  <button class="btn btn-p" type="submit">Salvează</button>
</form>

<div class="card">
  <h2>Cere agentului altă variantă</h2>
  <form method="post" action="{{ route('portal.social.rewrite', [$slug, $post->id]) }}" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap" onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Agentul scrie…'">@csrf
    <div class="fl" style="margin:0;flex:1;min-width:240px"><label for="instruction">Indicație (opțional)</label><input id="instruction" type="text" name="instruction" maxlength="300" placeholder="ex. mai scurt, mai mult despre livrare, fără emoji"></div>
    <button class="btn" type="submit">Rescrie</button></form>
  <p class="small muted" style="margin:8px 0 0">Rescrierea înlocuiește textul de mai sus și duce postarea înapoi la ciornă.</p>
</div>

<style>
.ed{display:grid;grid-template-columns:minmax(0,300px) 1fr;gap:18px;align-items:start}
.ed canvas{width:100%;height:auto;border-radius:12px;border:1px solid var(--border);display:block;touch-action:none}
.ed #image{cursor:grab}
.ed-tools{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px 14px}
.ed-tools label{display:block;font-size:12.5px;font-weight:600;margin-bottom:3px}
.ed-tools input[type=range]{width:100%}
.scene-row{display:flex;gap:10px;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:13.5px}
.scene-row select{max-width:230px}
@media (max-width:820px){.ed{grid-template-columns:1fr}}
</style>
<div id="editor" data-config="{{ json_encode([
    'color' => $settings['color'], 'brand' => $organization->name, 'day' => $post->day->toDateString(), 'design' => $post->design, 'voice' => $post->voice_path ? route('portal.social.voice.file', [$slug, $post->id]).'?v='.$post->updated_at?->timestamp : null,
    'assets' => $assets->sortBy(fn ($a) => $a->id === $post->asset_id ? 0 : 1)->take(24)->map(fn ($a) => ['url' => route('portal.social.media.file', [$slug, $a->id]), 'title' => $a->title, 'video' => $a->kind === 'video'])->values(),
]) }}">
<div class="card">
  <h2>Editor foto</h2>
  <p class="small muted">Pornește de la fotografia postării. Trage de imagine ca să o încadrezi. „Întreagă, pe fundal” păstrează toată fotografia (bun pentru capturi de site). Poți adăuga și fotografii de pe dispozitiv; acelea nu se încarcă în platformă.</p>
  <div class="ed">
    <div><canvas id="image" width="1080" height="1350" aria-label="Previzualizarea imaginii"></canvas>
      <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap"><button class="btn btn-p" type="button" id="save-image">Descarcă imaginea</button><button class="btn" type="button" id="reset">Revino la început</button></div></div>
    <div>
      <div class="ed-tools">
        <div><label for="photos">Fotografii de pe dispozitiv</label><input id="photos" type="file" accept="image/*" multiple></div>
        <div><label for="e-shape">Format</label><select id="e-shape" data-set="shape"><option value="1080x1350">Portret 4:5</option><option value="1080x1080">Pătrat 1:1</option><option value="1080x1920">Vertical 9:16</option><option value="1200x630">Orizontal (link)</option></select></div>
        <div><label for="e-fit">Încadrare</label><select id="e-fit" data-set="fit"><option value="cover">Umple imaginea</option><option value="contain">Întreagă, pe fundal</option></select></div>
        <div><label for="e-pos">Poziția textului</label><select id="e-pos" data-set="pos"><option value="bottom">Jos</option><option value="center">Centru</option><option value="top">Sus</option></select></div>
        <div><label for="e-zoom">Mărire</label><input id="e-zoom" type="range" min="60" max="250" data-set="zoom"></div>
        <div><label for="e-size">Mărimea textului</label><input id="e-size" type="range" min="60" max="160" data-set="size"></div>
        <div><label for="e-dark">Umbra de sub text</label><input id="e-dark" type="range" min="0" max="95" data-set="dark"></div>
        <div><label for="e-bright">Luminozitate</label><input id="e-bright" type="range" min="50" max="150" data-set="bright"></div>
        <div><label for="e-contrast">Contrast</label><input id="e-contrast" type="range" min="50" max="150" data-set="contrast"></div>
        <div><label for="e-sat">Saturație</label><input id="e-sat" type="range" min="0" max="200" data-set="sat"></div>
        <div><label for="e-color">Culoare</label><input id="e-color" type="color" data-set="color" style="width:60px;height:34px;padding:2px"></div>
        <div><label class="chk"><input type="checkbox" data-set="brand"> <span>Numele firmei</span></label><label class="chk"><input type="checkbox" data-set="bar"> <span>Linia de accent</span></label></div>
      </div>
      <p class="small muted" style="margin-top:10px">Textul de pe imagine este „Titlul de pe imagine” de mai sus. Aranjamentul se păstrează când apeși „Salvează” la postare.</p>
    </div>
  </div>
</div>

<div class="card" id="audio">
  <h2>Sunet: voce, muzică, efecte</h2>
  <p class="small muted">Agentul audio citește „Textul vocii” cu o voce în limba română și îl pune peste video, cu muzica mai încet sub voce. Ascultă înregistrarea înainte: pronunția numelor proprii se corectează scriindu-le în text cum se aud.</p>
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:10px">
    @if ($post->voice_path)
      <audio id="voice-audio" controls preload="auto" src="{{ route('portal.social.voice.file', [$slug, $post->id]) }}?v={{ $post->updated_at?->timestamp }}"></audio>
    @endif
    <form method="post" action="{{ route('portal.social.voice', [$slug, $post->id]) }}" onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Se generează vocea…'">@csrf
      <button class="btn{{ $post->voice_path ? '' : ' btn-p' }}" type="submit" @disabled(! \App\Social\VoiceStudio::configured() || ! $post->voiceover)>{{ $post->voice_path ? 'Generează din nou vocea' : 'Generează vocea' }}</button></form>
    @if ($post->voice_path)<form method="post" action="{{ route('portal.social.voice.delete', [$slug, $post->id]) }}">@csrf @method('delete')<button class="btn" type="submit">Șterge vocea</button></form>@endif
  </div>
  @unless (\App\Social\VoiceStudio::configured())<p class="small" style="color:var(--err)">Serviciul de voce nu este configurat încă pentru platformă. Muzica și efectele merg și fără el.</p>
  @elseif (! $post->voiceover)<p class="small muted">Scrie „Textul vocii” mai sus și salvează postarea, apoi generează vocea.</p>@endunless
  <div class="ed-tools">
    <div><label for="a-music">Muzică de fundal</label><select id="a-music" data-set="music"><option value="none">Fără muzică</option><option value="calm">Calmă (generată)</option><option value="bright">Luminoasă (generată)</option><option value="pulse">Ritmată (generată)</option><option value="file">Fișierul meu</option></select></div>
    <div><label for="music">Fișier audio propriu</label><input id="music" type="file" accept="audio/*"></div>
    <div><label for="a-mvol">Volumul muzicii</label><input id="a-mvol" type="range" min="0" max="100" data-set="musicVol"></div>
    <div><label for="a-vvol">Volumul vocii</label><input id="a-vvol" type="range" min="0" max="100" data-set="voiceVol"></div>
    <div><label class="chk"><input type="checkbox" data-set="whoosh"> <span>Efect sonor la schimbarea scenei</span></label><label class="chk"><input type="checkbox" data-set="fitVoice"> <span>Potrivește durata video-ului la voce</span></label></div>
    <div><button class="btn" type="button" id="listen">Ascultă mixajul</button> <button class="btn" type="button" id="stop">Oprește</button></div>
  </div>
  <p class="small muted" style="margin-top:8px">Muzica „generată” este compusă pe loc de editor, deci nu are drepturi de autor ale altcuiva. Pentru un fișier propriu, folosește doar muzică pentru care ai drept de folosire.</p>
</div>

<div class="card">
  <h2>Editor video</h2>
  <p class="small muted">Video-ul se montează din scenele postării: fiecare scenă are textul ei, fotografia ei și durata ei. Alegi tranziția, mișcarea și, dacă vrei, muzica. Folosește aceleași reglaje de imagine ca editorul foto.</p>
  <div class="ed">
    <div><canvas id="video" width="720" height="1280" aria-label="Previzualizarea video-ului"></canvas>
      <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap"><button class="btn" type="button" id="play">Previzualizează</button><button class="btn btn-p" type="button" id="record">Creează video</button></div>
      <p class="small muted" id="video-note" style="margin:6px 0 0" role="status"></p></div>
    <div>
      <div class="ed-tools">
        <div><label for="v-ratio">Format</label><select id="v-ratio" data-set="ratio"><option value="720x1280">Vertical 9:16 (TikTok, Reels)</option><option value="1080x1080">Pătrat 1:1</option><option value="1080x1350">Portret 4:5</option><option value="1280x720">Orizontal 16:9</option></select></div>
        <div><label for="v-transition">Tranziție între scene</label><select id="v-transition" data-set="transition"><option value="fade">Topire</option><option value="slide">Glisare</option><option value="zoom">Apropiere</option><option value="none">Tăietură</option></select></div>
        <div><label for="v-motion">Mișcarea fotografiei</label><select id="v-motion" data-set="motion"><option value="in">Apropiere lentă</option><option value="out">Depărtare lentă</option><option value="still">Fără mișcare</option></select></div>
      </div>
      <p class="small" id="video-length" style="margin:12px 0 4px;font-weight:600"></p>
      <div id="scene-list"></div>
      <p class="small muted" style="margin-top:8px">Textele și duratele scenelor se schimbă în „Scenariul video” de mai sus.</p>
    </div>
  </div>
</div>
</div>

<form method="post" action="{{ route('portal.social.destroy', [$slug, $post->id]) }}" onsubmit="return confirm('Ștergi această postare?')">@csrf @method('delete')<button class="btn btn-s" type="submit">Șterge postarea</button></form>

<script src="{{ asset('js/social-editor.js') }}?v={{ @filemtime(public_path('js/social-editor.js')) }}" defer></script>
@endsection
