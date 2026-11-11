<!doctype html>
<html lang="en-IN" data-theme="light" data-palette="grove">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign in · Nursery</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="{{ asset('css/nursery.css') }}?v={{ filemtime(public_path('css/nursery.css')) }}">
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
@include('partials.icons')
<div class="login">
  <section class="brandside" aria-hidden="true">
    <div class="mark"><span class="bm">N</span>Nursery</div>
    <div>
      <div class="pitch">
        <h2>Orders, purchases and accounts in one place.</h2>
        <p>Sign in to work on the pages your role allows. Your administrator manages users, roles and access rights.</p>
      </div>
      <div class="mods"><span>CRM</span><span>Sales</span><span>Purchase</span><span>Accounts</span><span>Admin</span><span>Reports</span></div>
    </div>
    <div class="foot">Every sign-in and admin change is recorded in the activity log.</div>
  </section>
  <main class="formside">
    <form class="lcard" method="POST" action="{{ route('login.attempt') }}" novalidate>
      @csrf
      <div class="mobilemark"><span class="bm">N</span>Nursery</div>
      <div>
        <h1>Sign in</h1>
        <p class="lead">Use the login email and password your administrator gave you.</p>
      </div>
      @if (session('status'))
        <div class="alert ok" role="status"><svg class="ico"><use href="#i-check-c"/></svg><span>{{ session('status') }}</span></div>
      @endif
      @if ($errors->any())
        <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><span>{{ $errors->first() }}</span></div>
      @endif
      <div class="field">
        <label for="email">Login email<span class="req">*</span></label>
        <input class="inp{{ $errors->has('email') ? ' err' : '' }}" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" inputmode="email" required autofocus>
      </div>
      <div class="field">
        <label for="password">Password<span class="req">*</span></label>
        <div class="pw">
          <input class="inp{{ $errors->has('password') ? ' err' : '' }}" id="password" name="password" type="password" autocomplete="current-password" required>
          <button type="button" class="btn gh icon sm" id="pwToggle" aria-label="Show password" aria-pressed="false"><svg class="ico"><use href="#i-lock"/></svg></button>
        </div>
      </div>
      <div class="row">
        <label><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Keep me signed in</label>
        <span class="cap muted">Forgot it? Ask your administrator.</span>
      </div>
      <button class="btn pri" type="submit">Sign in</button>
    </form>
  </main>
</div>
<script>
document.getElementById('pwToggle').addEventListener('click',function(){var i=document.getElementById('password'),s=i.type==='password';i.type=s?'text':'password';this.setAttribute('aria-pressed',String(s));this.setAttribute('aria-label',s?'Hide password':'Show password')});
</script>
</body>
</html>
