@extends('layouts.auth')
@section('title', 'Parolă nouă')
@section('content')
<form method="post" action="{{ route('password.update') }}">
  @csrf
  <input type="hidden" name="token" value="{{ $token }}">
  <div class="fl"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email', $email) }}" autocomplete="username" required></div>
  <div class="fl"><label for="password">Parolă nouă</label><input id="password" type="password" name="password" autocomplete="new-password" minlength="12" required><div class="hint">Minimum 12 caractere.</div></div>
  <div class="fl"><label for="password_confirmation">Repetă parola</label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required></div>
  <button class="btn btn-p" type="submit">Salvează parola</button>
</form>
@endsection
