<!doctype html>
<html lang="en-IN" data-theme="light" data-palette="grove">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('title') · Nursery</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="{{ asset('css/nursery.css') }}"><link rel="stylesheet" href="{{ asset('css/app.css') }}"></head>
<body><main class="errpage"><div class="box">
  <div class="code">@yield('code')</div><h1>@yield('title')</h1><p>@yield('message')</p>
  @yield('links')
  <a class="btn pri" href="{{ url('/') }}">Go to my start page</a>
</div></main></body></html>
