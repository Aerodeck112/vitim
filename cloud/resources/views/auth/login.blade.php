@extends('layouts.auth')
@section('title', 'Autentificare')
@section('content')
<form method="post" action="{{ url('/login') }}">
  @csrf
  <div class="fl"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus></div>
  <div class="fl"><label for="password">Parolă</label><input id="password" type="password" name="password" autocomplete="current-password" required></div>
  <button class="btn btn-p" type="submit">Intră în cont</button>
</form>
<p class="small muted" style="margin:16px 0 0"><a href="{{ route('password.request') }}">Ai uitat parola sau nu ai setat-o încă?</a></p>
@endsection
