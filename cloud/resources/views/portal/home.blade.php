@extends('layouts.app')
@section('title', $organization->name)
@section('content')
<div class="head"><div><h1>{{ $organization->name }}</h1><p>Agentul VITIM AI pentru site-urile tale.</p></div></div>
<div class="card">
  <h2>Ce urmează</h2>
  <p class="muted" style="margin:0">Conversațiile, lead-urile și rapoartele lunare vor apărea aici după activarea agentului.</p>
</div>
<div class="card">
  <h2>Site-uri</h2>
  @forelse ($sites as $site)
    <div style="padding:10px 0;border-bottom:1px solid var(--border)">
      <strong>{{ $site->domain }}</strong>
      @if ($canManageSites && ($key = $site->keys->first()))
        @include('partials.install', ['publicKey' => $key->public_key])
      @endif
    </div>
  @empty
    <p class="muted">Echipa VITIM îți adaugă site-ul la activare.</p>
  @endforelse
</div>
@endsection
