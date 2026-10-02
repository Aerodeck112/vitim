@extends('layouts.auth')
@section('title', 'Dezabonare')
@section('content')
@if ($test)
  <p>Acesta este linkul de dezabonare dintr-un mesaj de test. În mesajele reale, aici destinatarul se dezabonează.</p>
@elseif (! $company)
  <p>Linkul nu mai este valid.</p>
@elseif ($done)
  <h2 style="margin-top:0">Te-ai dezabonat</h2>
  <p>Nu mai primești mesaje de marketing pe acest canal de la <strong>{{ $company }}</strong>. Dacă te răzgândești, le poți spune direct.</p>
@else
  <h2 style="margin-top:0">Dezabonare</h2>
  <p>Nu mai vrei să primești mesaje de marketing de la <strong>{{ $company }}</strong> pe acest canal?</p>
  <form method="post" action="{{ route('unsubscribe.confirm', $code) }}"><button class="btn btn-p" type="submit">Da, dezabonează-mă</button></form>
@endif
@endsection
