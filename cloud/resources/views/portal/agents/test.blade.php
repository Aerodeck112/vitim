@extends('layouts.app')
@section('title', 'Test: '.$agent->name)
@section('content')
<div class="head"><div><h1>Testează: {{ $agent->name }}</h1>
  <p>Răspunsuri reale ale agentului, exact ca pe site. În test nu se creează lead-uri și nu se trimit notificări.</p></div>
  <div style="display:flex;gap:8px"><a class="btn" href="{{ route('portal.agents.edit', [$organization->slug, $agent->id]) }}">Configurație</a>
  @if ($conversation)<a class="btn" href="{{ route('portal.agents.test', [$organization->slug, $agent->id]) }}">Conversație nouă</a>@endif</div></div>

@if ($engine === 'local' || ($engine === 'auto' && ! $configured))
  <div class="alert alert-ok">Agentul răspunde <strong>din informațiile firmei</strong> (fără AI extern, fără cost). Dacă un răspuns lipsește sau nu e bun, completează textul din <a href="{{ route('portal.agents.edit', [$organization->slug, $agent->id]) }}">Configurație</a> → „Informații despre firmă” și încearcă din nou.</div>
@elseif (! $configured)
  <div class="alert alert-warn">Agentul e setat pe „Doar Claude”, dar cheia nu este configurată pe server (<span class="mono">ANTHROPIC_API_KEY</span> în <span class="mono">.env</span>). Alege „Automat” în Configurație ca să răspundă din informațiile firmei.</div>
@endif
<p class="muted small">Consum AI luna aceasta: <strong>${{ number_format($spentUsd, 2) }}</strong>@if ($capUsd !== null) din plafonul de ${{ $capUsd }}@endif.@if ($conversation) Conversația aceasta: ${{ number_format($conversation->ai_cost_micro_usd / 1_000_000, 4) }}.@endif</p>

<div class="card chat">
  @if (! empty($agent->system_configuration['greeting']))
    <div class="bubble ai">{{ $agent->system_configuration['greeting'] }}</div>
  @endif
  @forelse ($messages as $m)
    <div class="bubble {{ $m->direction === 'inbound' ? 'me' : ($m->sender_type->value === 'system' ? 'sys' : 'ai') }}">{{ $m->direction === 'inbound' ? $m->content : \App\Support\ChatText::render($m->content) }}</div>
    @foreach ($m->metadata['tools'] ?? [] as $id)
      @if ($e = $executions->get($id))
        <div class="tool"><span class="badge {{ $e->status === 'ok' || $e->status === 'dry_run' ? 'ok' : 'warn' }}">{{ $e->tool }} · {{ $e->status }}</span>
          <span class="muted small">{{ $e->result }}</span>
          @if ($e->input)<details><summary class="small">Date trimise de agent</summary><pre class="code">{{ json_encode($e->input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>@endif
        </div>
      @endif
    @endforeach
  @empty
    <p class="muted">Scrie un mesaj ca un client al firmei. De exemplu: „Cât costă…?”, „Aveți program sâmbăta?”, „Vreau o programare”.</p>
  @endforelse
</div>
<form method="post" action="{{ route('portal.agents.test.send', [$organization->slug, $agent->id]) }}" class="chat-form">
  @csrf
  @if ($conversation)<input type="hidden" name="conversation_id" value="{{ $conversation->id }}">@endif
  <textarea name="message" required maxlength="{{ config('vitim.ai.max_message_chars') }}" placeholder="Mesajul clientului…" rows="2" autofocus></textarea>
  <button class="btn btn-p" type="submit">Trimite</button>
</form>
@endsection
