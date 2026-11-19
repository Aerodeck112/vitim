@extends('layouts.app')
@section('title', 'Social media')
@section('content')
@php($slug = $organization->slug)
@if (session('error'))<div class="alert alert-err" role="alert">{{ session('error') }}</div>@endif
@php($end = $week->copy()->addDays(6))
<div class="head">
  <div><h1>Social media</h1><p>Agentul scrie planul săptămânii, câte o postare pe zi: textul pentru Facebook și Instagram, hashtagurile, ideea de fotografie și scenariul unui video scurt pentru TikTok sau Reels. Scrie doar din datele firmei tale. Tu citești, corectezi și aprobi; publicarea o faci din contul firmei.</p></div>
  <form method="post" action="{{ route('portal.social.plan', $slug) }}" onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Agentul scrie…'">@csrf
    <input type="hidden" name="s" value="{{ $week->toDateString() }}">
    <button class="btn btn-p" type="submit" @disabled(! $available)>{{ $posts->where('source', 'ai')->isEmpty() ? 'Scrie planul săptămânii' : 'Rescrie ciornele săptămânii' }}</button></form>
</div>
@unless ($available)
  <div class="alert alert-warn">Agentul de social media nu poate scrie acum: modelul AI nu este configurat pentru platformă sau plafonul lunar al firmei a fost atins. Poți adăuga postări scrise de tine.</div>
@endunless

<div class="tabs">
  <a href="{{ route('portal.social', [$slug, 's' => $week->copy()->subWeek()->toDateString()]) }}">← săptămâna anterioară</a>
  <a class="on" href="{{ route('portal.social', [$slug, 's' => $week->toDateString()]) }}">{{ $week->format('d.m') }} – {{ $end->format('d.m.Y') }}</a>
  <a href="{{ route('portal.social', [$slug, 's' => $week->copy()->addWeek()->toDateString()]) }}">săptămâna următoare →</a>
</div>

@for ($d = 0; $d < 7; $d++)
  @php($date = $week->copy()->addDays($d))
  @php($dayPosts = $posts->filter(fn ($p) => $p->day->toDateString() === $date->toDateString()))
  <div class="card" style="margin-bottom:12px">
    <div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline;flex-wrap:wrap">
      <h2 style="margin:0">{{ ucfirst(\App\Social\SocialPlanner::DAYS[$d + 1]) }}, {{ $date->format('d.m') }}</h2>
      @if ($dayPosts->isEmpty())
        <form method="post" action="{{ route('portal.social.store', $slug) }}">@csrf<input type="hidden" name="day" value="{{ $date->toDateString() }}"><button class="btn btn-s" type="submit">Adaugă o postare scrisă de mine</button></form>
      @endif
    </div>
    @forelse ($dayPosts as $post)
      <div style="margin-top:10px">
        <p style="margin:0 0 6px"><span class="badge {{ ['approved' => 'ok', 'published' => 'ok', 'skipped' => 'err'][$post->status] ?? 'warn' }}">{{ \App\Models\SocialPost::STATUSES[$post->status] }}</span>
          <a href="{{ route('portal.social.show', [$slug, $post->id]) }}"><strong>{{ $post->hook }}</strong></a> <span class="small muted">· {{ $post->topic }}</span></p>
        <p style="margin:0 0 6px;white-space:pre-line">{{ $post->caption }}</p>
        @if ($post->hashtags)<p class="small muted" style="margin:0 0 8px">{{ $post->hashtags }}</p>@endif
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <a class="btn btn-s" href="{{ route('portal.social.show', [$slug, $post->id]) }}">Deschide: text, imagine și video</a>
          <button class="btn btn-s" type="button" data-copy="{{ $post->fullText() }}">Copiază textul</button>
          @foreach (['draft' => ['approved' => 'Aprobă'], 'approved' => ['published' => 'Am publicat-o', 'draft' => 'Înapoi la ciornă'], 'published' => [], 'skipped' => ['draft' => 'Reia']][$post->status] as $to => $label)
            <form method="post" action="{{ route('portal.social.status', [$slug, $post->id]) }}">@csrf<input type="hidden" name="status" value="{{ $to }}"><button class="btn btn-s{{ $to === 'approved' ? ' btn-p' : '' }}" type="submit">{{ $label }}</button></form>
          @endforeach
          @if ($post->status === 'draft')<form method="post" action="{{ route('portal.social.status', [$slug, $post->id]) }}">@csrf<input type="hidden" name="status" value="skipped"><button class="btn btn-s" type="submit">Sari peste</button></form>@endif
        </div>
      </div>
    @empty
      <p class="small muted" style="margin:8px 0 0">{{ in_array($d + 1, $settings['days'], true) ? 'Nicio postare încă.' : 'Zi fără postare în plan.' }}</p>
    @endforelse
  </div>
@endfor

<form method="post" action="{{ route('portal.social.settings', $slug) }}" class="card">
  @csrf @method('put')
  <h2>Cum lucrează agentul</h2>
  <div class="row">
    <div class="fl"><label>Rețele</label>
      @foreach (\App\Social\SocialPlanner::PLATFORMS as $key => $label)<label class="chk"><input type="checkbox" name="platforms[]" value="{{ $key }}" @checked(in_array($key, $settings['platforms'], true))> <span>{{ $label }}</span></label>@endforeach</div>
    <div class="fl"><label>Zile cu postare</label>
      <div style="display:flex;gap:10px;flex-wrap:wrap">@foreach (\App\Social\SocialPlanner::DAYS as $n => $label)<label class="chk"><input type="checkbox" name="days[]" value="{{ $n }}" @checked(in_array($n, $settings['days'], true))> <span>{{ $label }}</span></label>@endforeach</div></div>
  </div>
  <div class="row">
    <div class="fl"><label for="tone">Tonul</label><input id="tone" type="text" name="tone" maxlength="300" value="{{ old('tone', $settings['tone']) }}" placeholder="ex. prietenos, direct, fără emoji"></div>
    <div class="fl"><label for="cta">Îndemnul de la final</label><input id="cta" type="text" name="cta" maxlength="200" value="{{ old('cta', $settings['cta']) }}" placeholder="ex. Sună-ne sau scrie-ne pe site"></div>
  </div>
  <div class="row">
    <div class="fl"><label for="topics">Teme dorite</label><textarea id="topics" name="topics" maxlength="600" placeholder="ex. sfaturi de întreținere, lucrări recente, echipa la lucru">{{ old('topics', $settings['topics']) }}</textarea></div>
    <div class="fl"><label for="avoid">De evitat</label><textarea id="avoid" name="avoid" maxlength="400" placeholder="ex. prețuri, comparații cu alte firme">{{ old('avoid', $settings['avoid']) }}</textarea></div>
  </div>
  <div class="fl"><label for="color">Culoarea firmei pe imagini și video</label><input id="color" type="color" name="color" value="{{ old('color', $settings['color']) }}" style="width:70px;height:38px;padding:2px"></div>
  <label class="chk"><input type="checkbox" name="auto" value="1" @checked($settings['auto'])> <span><strong>Scrie singur planul săptămânii următoare</strong>, în fiecare duminică după-amiază. Postările rămân ciorne până le aprobi.</span></label>
  <label class="chk"><input type="checkbox" name="remind" value="1" @checked($settings['remind'])> <span><strong>Trimite-mi dimineața postarea zilei pe email</strong> (proprietarului și administratorilor firmei), cu textul gata de copiat.</span></label>
  <p class="small muted">Agentul folosește profilul public al firmei, informațiile date asistentului de pe site și paginile citite din site. Cu cât acestea sunt mai complete, cu atât postările sunt mai bune.</p>
  <button class="btn" type="submit">Salvează</button>
</form>
<script>
document.querySelectorAll('[data-copy]').forEach(function (b) {
  b.addEventListener('click', function () {
    navigator.clipboard.writeText(b.dataset.copy).then(function () { var t = b.textContent; b.textContent = 'Copiat'; setTimeout(function () { b.textContent = t; }, 1500); });
  });
});
</script>
@endsection
