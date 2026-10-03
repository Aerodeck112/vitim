@extends('layouts.app')
@section('title', $a->title)
@section('content')
<div class="head"><div><h1>{{ $a->title }}</h1><p>{{ $a->sent_at?->setTimezone('Europe/Bucharest')->format('d.m.Y') }} · {{ \App\Models\Announcement::KINDS[$a->kind] }}</p></div>
  <a class="btn" href="{{ route('portal.news', $organization->slug) }}">Toate noutățile</a></div>
<iframe title="{{ $a->title }}" sandbox="allow-popups allow-popups-to-escape-sandbox" srcdoc="{{ $html }}" style="width:100%;height:80vh;border:1px solid var(--border);border-radius:12px;background:#f4f6fb"></iframe>
@endsection
