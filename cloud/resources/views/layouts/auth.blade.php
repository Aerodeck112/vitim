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
<main class="auth">
  <div class="card">
    <div class="logo">VITIM <span class="muted">AI</span></div>
    @include('partials.flash')
    @yield('content')
  </div>
</main>
</body>
</html>
