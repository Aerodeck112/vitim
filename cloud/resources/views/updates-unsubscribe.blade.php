@extends('layouts.auth')
@section('title', 'Noutăți VITIM')
@section('content')
@if ($done)
  <h2 style="margin-top:0">Gata, nu mai primești noutățile VITIM</h2>
  <p>Emailurile importante despre contul tău (invitații, rapoarte, securitate) vor continua să ajungă la tine. Noutățile le găsești oricând în panou, la „Noutăți VITIM”.</p>
@else
  <h2 style="margin-top:0">Nu mai vrei noutățile VITIM pe email?</h2>
  <p>Nu vei mai primi anunțurile despre platformă și rezumatul lunar. Emailurile importante despre contul tău continuă.</p>
  <form method="post" action="{{ $url }}"><button class="btn btn-p" type="submit">Da, oprește aceste emailuri</button></form>
@endif
@endsection
