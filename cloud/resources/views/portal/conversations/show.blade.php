@extends('layouts.app')
@section('title', 'Conversație')
@section('content')
@php($canHandle = auth()->user()->can('handle_conversations'))
<div class="head"><div><h1>Conversație · {{ $conversation->contact?->displayName() ?? 'vizitator anonim' }}</h1>
  <p>{{ $conversation->site?->domain }} · începută {{ $conversation->created_at->format('d.m.Y H:i') }}
    @if ($conversation->visitor_page) · de pe <span class="mono">{{ \Illuminate\Support\Str::limit($conversation->visitor_page, 80) }}</span>@endif</p></div>
  <a class="btn" href="{{ route('portal.conversations.index', $organization->slug) }}">Toate conversațiile</a></div>
@if ($conversation->status->value === 'pending' && ! $conversation->isLive())<div class="alert alert-warn">Vizitatorul a cerut să vorbească cu un om. @if ($canHandle)Preia conversația și scrie-i aici — mesajul apare imediat în fereastra de chat de pe site.@else Contactează-l folosind datele din lead (dacă le-a lăsat).@endif</div>@endif
@if ($conversation->lead)
  <div class="alert alert-ok">Lead creat: <a href="{{ route('portal.contacts.show', [$organization->slug, $conversation->lead->contact_id]) }}">{{ $conversation->contact?->displayName() ?? 'contact' }}</a>
    @if ($conversation->contact?->phone) · {{ $conversation->contact->phone }}@endif @if ($conversation->contact?->email) · {{ $conversation->contact->email }}@endif</div>
@endif
<div class="live-bar">
  <span id="visitor-state"><span @class(['dot', 'on' => $conversation->visitorOnline()])></span>{{ $conversation->visitorOnline() ? 'Vizitatorul e pe site acum' : 'Vizitatorul nu e pe site acum' }}</span>
  <span class="badge {{ $conversation->isLive() ? 'ok' : '' }}" id="mode-badge">{{ $conversation->isLive() ? 'Răspunzi tu (live)' : 'Răspunde asistentul AI' }}</span>
  @if ($conversation->status->value === 'closed')<span class="badge">încheiată</span>@endif
  @if ($canHandle)
    @foreach (array_filter([! $conversation->isLive() ? ['take', 'Preia conversația', 'btn-p'] : ['release', 'Predă asistentului AI', ''], $conversation->status->value !== 'closed' ? ['close', 'Încheie conversația', ''] : null]) as [$action, $label, $class])
      <form method="post" action="{{ route('portal.conversations.action', [$organization->slug, $conversation->id]) }}">@csrf<input type="hidden" name="action" value="{{ $action }}"><button class="btn {{ $class }}" type="submit">{{ $label }}</button></form>
    @endforeach
  @endif
</div>
<div class="card chat live-chat" id="chat" data-last="{{ $messages->max('id') ?? 0 }}" data-poll="{{ route('portal.conversations.poll', [$organization->slug, $conversation->id]) }}">
  @foreach ($messages as $m)
    <div class="bubble {{ \App\Http\Controllers\Portal\ConversationController::bubble($m) }}">{{ $m->direction === 'inbound' ? $m->content : \App\Support\ChatText::render($m->content) }}<span class="t">{{ $m->created_at?->format('H:i') }}</span></div>
    @foreach ($m->metadata['tools'] ?? [] as $id)
      @if ($e = $executions->get($id))<div class="tool"><span class="badge {{ $e->status === 'ok' ? 'ok' : 'warn' }}">{{ $e->tool === 'create_lead' ? 'cerere salvată' : ($e->tool === 'request_human' ? 'cere un om' : $e->tool) }}{{ $e->status === 'ok' ? '' : ' · '.$e->status }}</span></div>@endif
    @endforeach
  @endforeach
</div>
@if ($canHandle)
<form class="reply-form" id="reply" method="post" action="{{ route('portal.conversations.reply', [$organization->slug, $conversation->id]) }}" data-typing="{{ route('portal.conversations.typing', [$organization->slug, $conversation->id]) }}">
  @csrf
  <textarea name="message" maxlength="2000" required placeholder="{{ $conversation->isLive() ? 'Scrie vizitatorului… (Enter trimite, Shift+Enter rând nou)' : 'Scrie vizitatorului — trimiterea preia conversația de la asistentul AI' }}"></textarea>
  <button class="btn btn-p" type="submit">Trimite</button>
</form>
@endif
<script>
(function () {
  var chat = document.getElementById('chat'), form = document.getElementById('reply'), last = +chat.dataset.last, title = document.title, unseen = 0, typedAt = 0;
  var token = document.querySelector('#reply input[name=_token]');
  chat.scrollTop = chat.scrollHeight;
  function beep() { try { var a = new (window.AudioContext || window.webkitAudioContext)(), o = a.createOscillator(), g = a.createGain(); o.frequency.value = 880; g.gain.setValueAtTime(.08, a.currentTime); g.gain.exponentialRampToValueAtTime(.0001, a.currentTime + .25); o.connect(g); g.connect(a.destination); o.start(); o.stop(a.currentTime + .26); } catch (e) {} }
  function fetchNew() {
    return fetch(chat.dataset.poll + '?after=' + last, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      var incoming = false;
      j.messages.forEach(function (m) {
        if (m.id <= last) return;
        last = m.id;
        var b = document.createElement('div'); b.className = 'bubble ' + m.class; b.innerHTML = m.html + '<span class="t">' + m.at + '</span>';
        chat.appendChild(b); if (m.inbound) incoming = true;
      });
      if (j.messages.length) chat.scrollTop = chat.scrollHeight;
      if (incoming) { beep(); if (document.hidden) { unseen++; document.title = '(' + unseen + ') Mesaj nou · ' + title; } }
      var s = document.getElementById('visitor-state');
      s.innerHTML = '<span class="dot' + (j.visitor_online ? ' on' : '') + '"></span>' + (j.visitor_online ? 'Vizitatorul e pe site acum' : 'Vizitatorul nu e pe site acum');
    }).catch(function () {});
  }
  function poll() { fetchNew().then(function () { setTimeout(poll, document.hidden ? 10000 : 4000); }); }
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { unseen = 0; document.title = title; } });
  setTimeout(poll, 4000);
  if (!form) return;
  var area = form.querySelector('textarea');
  area.addEventListener('input', function () {
    if (Date.now() - typedAt < 3000) return; typedAt = Date.now();
    fetch(form.dataset.typing, { method: 'POST', headers: { 'X-CSRF-TOKEN': token.value, Accept: 'application/json' }, credentials: 'same-origin' }).catch(function () {});
  });
  area.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
  form.addEventListener('submit', function (e) {
    var text = area.value.trim();
    if (!text) return;
    if (!document.getElementById('mode-badge').classList.contains('ok')) return; // prima trimitere: formular normal (preia conversația, pagina se reîncarcă)
    e.preventDefault();
    area.value = ''; area.disabled = true;
    var data = new FormData(); data.append('message', text);
    fetch(form.action, { method: 'POST', body: data, headers: { 'X-CSRF-TOKEN': token.value, Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw r; }).catch(function () { area.value = text; alert('Mesajul nu a fost trimis. Încearcă din nou.'); })
      .then(function () { area.disabled = false; area.focus(); fetchNew(); });
  });
})();
</script>
@endsection
