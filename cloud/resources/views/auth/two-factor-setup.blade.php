@extends('layouts.auth')
@section('title', 'Autentificare în doi pași')
@section('content')
@if ($required)
  <div class="alert alert-warn small">Contul echipei VITIM are acces la datele clienților: autentificarea în doi pași este obligatorie.</div>
@endif
<ol class="small" style="padding-left:18px;margin-top:0">
  <li>Deschide Google Authenticator, Microsoft Authenticator sau 2FAS.</li>
  <li>Adaugă un cont nou cu cheia de mai jos (sau, de pe telefon, <a href="{{ $uri }}">apasă aici</a>).</li>
  <li>Introdu codul de 6 cifre afișat de aplicație.</li>
</ol>
<pre class="code">{{ $secret }}</pre>
<form method="post" action="{{ url('/2fa/activare') }}" style="margin-top:14px">
  @csrf
  <div class="fl"><label for="code">Cod</label><input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus></div>
  <button class="btn btn-p" type="submit">Activează</button>
</form>
@endsection
