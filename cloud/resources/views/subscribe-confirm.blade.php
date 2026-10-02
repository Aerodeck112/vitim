@extends('layouts.auth')
@section('title', 'Confirmarea abonării')
@section('content')
@if (! $company)
  <h2 style="margin-top:0">Linkul nu mai este valid</h2>
  <p>Linkul de confirmare a expirat sau nu e complet. Abonează-te din nou de pe site.</p>
@elseif ($done)
  <h2 style="margin-top:0">{{ $title ?: 'Abonarea e confirmată' }}</h2>
  <p>{{ $text ?: 'Mulțumim! De acum primești pe email noutățile și ofertele '.$company.'.' }}</p>
  @if ($coupon)<p style="margin:18px 0">Codul tău: <strong style="font-size:22px;letter-spacing:.06em;padding:6px 12px;border:2px dashed currentColor;border-radius:8px;display:inline-block">{{ $coupon }}</strong></p>@endif
  <p class="small muted">Te poți dezabona oricând, din linkul aflat în fiecare email.</p>
@else
  <h2 style="margin-top:0">Confirmă abonarea</h2>
  <p>Confirmi că vrei să primești pe email noutățile și ofertele <strong>{{ $company }}</strong>?</p>
  <form method="post" action="{{ route('forms.confirm.store', $code) }}"><button class="btn btn-p" type="submit">Da, confirm abonarea</button></form>
@endif
@endsection
