@extends('layouts.app')
@section('title', $section[0])
@section('content')
<div class="head"><div><h1>{{ $section[0] }}</h1><p>În dezvoltare, încă indisponibil.</p></div></div>
<div class="card" style="max-width:640px">
  <p style="margin-top:0">{{ $section[1] }}</p>
  <p class="muted" style="margin-bottom:0">Planificat: {{ $section[2] }}. Până atunci, această secțiune nu colectează și nu afișează date.</p>
</div>
@endsection
