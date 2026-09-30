@extends('layouts.auth')
@section('title', 'Cod de verificare')
@section('content')
<p class="muted small" style="margin-top:0">Introdu codul de 6 cifre din aplicația de autentificare.</p>
<form method="post" action="{{ url('/2fa') }}">
  @csrf
  <div class="fl"><label for="code">Cod</label><input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus></div>
  <button class="btn btn-p" type="submit">Verifică</button>
</form>
<form method="post" action="{{ route('logout') }}" style="margin-top:14px">@csrf<button class="btn btn-s" type="submit">Ieșire</button></form>
@endsection
