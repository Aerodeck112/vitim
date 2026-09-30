@extends('layouts.auth')
@section('title', 'Setare parolă')
@section('content')
<p class="muted small" style="margin-top:0">Primești pe email un link pentru setarea parolei.</p>
<form method="post" action="{{ route('password.email') }}">
  @csrf
  <div class="fl"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus></div>
  <button class="btn btn-p" type="submit">Trimite linkul</button>
</form>
<p class="small" style="margin:16px 0 0"><a href="{{ route('login') }}">Înapoi la autentificare</a></p>
@endsection
