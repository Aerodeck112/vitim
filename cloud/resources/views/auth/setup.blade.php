@extends('layouts.auth')
@section('title', 'Primul administrator')
@section('content')
<p class="muted small" style="margin-top:0">Contul administratorului VITIM. Tokenul este valoarea SETUP_TOKEN din fișierul .env.</p>
<form method="post" action="{{ url('/setup') }}">
  @csrf
  <div class="fl"><label for="token">Token de instalare</label><input id="token" type="password" name="token" required></div>
  <div class="fl"><label for="name">Nume</label><input id="name" type="text" name="name" value="{{ old('name') }}" required></div>
  <div class="fl"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required></div>
  <div class="fl"><label for="password">Parolă</label><input id="password" type="password" name="password" minlength="12" autocomplete="new-password" required><div class="hint">Minimum 12 caractere.</div></div>
  <div class="fl"><label for="password_confirmation">Repetă parola</label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required></div>
  <button class="btn btn-p" type="submit">Creează contul</button>
</form>
@endsection
