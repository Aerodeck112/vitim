@extends('layouts.app')
@section('title', $contact ? 'Editare contact' : 'Contact nou')
@section('content')
<div class="head"><div><h1>{{ $contact ? $contact->displayName() : 'Contact nou' }}</h1><p>Email și telefonul sunt opționale; dacă există, identifică același om pe toate canalele.</p></div></div>
@if (session('duplicate'))
  <div class="alert alert-warn">Există deja un contact cu aceste date: <a href="{{ route('portal.contacts.show', [$organization->slug, session('duplicate')]) }}">deschide contactul</a>.</div>
@endif
<form method="post" action="{{ $contact ? route('portal.contacts.update', [$organization->slug, $contact->id]) : route('portal.contacts.store', $organization->slug) }}" class="card" style="max-width:720px">
  @csrf @if ($contact) @method('put') @endif
  <div class="row">
    <div class="fl"><label for="first_name">Prenume</label><input id="first_name" type="text" name="first_name" value="{{ old('first_name', $contact?->first_name) }}"></div>
    <div class="fl"><label for="last_name">Nume</label><input id="last_name" type="text" name="last_name" value="{{ old('last_name', $contact?->last_name) }}"></div>
  </div>
  <div class="row">
    <div class="fl"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email', $contact?->email) }}"></div>
    <div class="fl"><label for="phone">Telefon</label><input id="phone" type="text" name="phone" value="{{ old('phone', $contact?->phone) }}" placeholder="07xx xxx xxx sau +40..."></div>
  </div>
  <div class="row">
    <div class="fl"><label for="company">Firmă</label><input id="company" type="text" name="company" value="{{ old('company', $contact?->company) }}"></div>
    <div class="fl"><label for="language">Limbă</label><input id="language" type="text" name="language" value="{{ old('language', $contact?->language) }}" maxlength="2" placeholder="ro"></div>
  </div>
  <button class="btn btn-p" type="submit">Salvează</button>
</form>
@endsection
