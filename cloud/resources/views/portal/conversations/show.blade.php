@extends('layouts.app')
@section('title', 'Conversație')
@section('content')
<div class="head"><div><h1>Conversație · {{ $conversation->contact?->displayName() ?? 'vizitator anonim' }}</h1>
  <p>{{ $conversation->site?->domain }} · începută {{ $conversation->created_at->format('d.m.Y H:i') }}
    @if ($conversation->visitor_page) · de pe <span class="mono">{{ \Illuminate\Support\Str::limit($conversation->visitor_page, 80) }}</span>@endif</p></div>
  <a class="btn" href="{{ route('portal.conversations.index', $organization->slug) }}">Toate conversațiile</a></div>
@if ($conversation->status->value === 'pending')<div class="alert alert-warn">Vizitatorul a cerut să vorbească cu un om. Contactează-l folosind datele din lead (dacă le-a lăsat).</div>@endif
@if ($conversation->lead)
  <div class="alert alert-ok">Lead creat: <a href="{{ route('portal.contacts.show', [$organization->slug, $conversation->lead->contact_id]) }}">{{ $conversation->contact?->displayName() ?? 'contact' }}</a>
    @if ($conversation->contact?->phone) · {{ $conversation->contact->phone }}@endif @if ($conversation->contact?->email) · {{ $conversation->contact->email }}@endif</div>
@endif
<div class="card chat">
  @foreach ($messages as $m)
    <div class="bubble {{ $m->direction === 'inbound' ? 'me' : ($m->sender_type->value === 'system' ? 'sys' : 'ai') }}">{{ $m->direction === 'inbound' ? $m->content : \App\Support\ChatText::render($m->content) }}</div>
    @foreach ($m->metadata['tools'] ?? [] as $id)
      @if ($e = $executions->get($id))<div class="tool"><span class="badge {{ $e->status === 'ok' ? 'ok' : 'warn' }}">{{ $e->tool === 'create_lead' ? 'cerere salvată' : ($e->tool === 'request_human' ? 'cere un om' : $e->tool) }}{{ $e->status === 'ok' ? '' : ' · '.$e->status }}</span></div>@endif
    @endforeach
  @endforeach
</div>
@endsection
