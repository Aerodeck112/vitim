<!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title') · VITIM AI</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="shell">
  <nav class="side" aria-label="Navigare">
    <div class="brand">VITIM AI<small>{{ auth()->user()->isPlatformStaff() ? 'echipa VITIM' : 'portal client' }}</small></div>
    @if (auth()->user()->isPlatformStaff())
      <a href="{{ route('admin.organizations.index') }}" @class(['on' => request()->routeIs('admin.organizations.*')])>Clienți</a>
    @endif
    @isset($organization)
      <a href="{{ auth()->user()->isPlatformStaff() ? route('admin.organizations.show', $organization->slug) : route('portal.home', $organization->slug) }}" class="on">{{ $organization->name }}</a>
    @endisset
    <div class="grow"></div>
    <span class="small" style="padding:8px 12px">{{ auth()->user()->email }}</span>
    <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Ieșire</button></form>
  </nav>
  <main class="main">
    @include('partials.flash')
    @yield('content')
  </main>
</div>
</body>
</html>
