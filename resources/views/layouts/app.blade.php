<!doctype html>
<html lang="en-IN" data-theme="light" data-palette="grove">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'Nursery' }} · Nursery</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="{{ asset('css/nursery.css') }}?v={{ filemtime(public_path('css/nursery.css')) }}">
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<a class="skiplink" href="#content">Skip to content</a>
@include('partials.icons')

<div class="shell" id="shell">
  <aside class="side" id="side" aria-label="Main">@include('partials.sidebar')</aside>
  <div class="navscrim" id="navscrim"></div>
  <div class="main">
    <header class="top" id="top">
      <button class="btn gh icon sm menu-m" id="menuBtn" aria-label="Open menu"><svg class="ico"><use href="#i-menu"/></svg></button>
      {{-- Menu bar (22/10/2026), in place of the breadcrumbs: every top-level menu the user may open. Pointing at,
           tabbing to or tapping a menu opens its pages (js/app.js); clicking the menu itself opens its first page.
           The selected menu's reports show in the side menu. Phones and tablets use the menu button instead. --}}
      <nav class="topnav" id="topnav" aria-label="Main menu">
        <ul>
          @foreach ($menuBar['items'] as $m)
            <li class="tnw">
              <a class="tn{{ $m['on'] ? ' on' : '' }}" id="tn-{{ $m['key'] }}" href="{{ $m['url'] }}" @if ($m['on']) aria-current="true" @endif @if ($m['subs']) aria-haspopup="true" aria-expanded="false" aria-controls="tndd-{{ $m['key'] }}" data-tndd @endif><svg class="ico" aria-hidden="true"><use href="#i-{{ $m['icon'] }}"/></svg><span>{{ $m['title'] }}</span>@if ($m['subs'])<svg class="ico chev" aria-hidden="true"><use href="#i-down"/></svg>@endif</a>
              @if ($m['subs'])
                <div class="tndd" id="tndd-{{ $m['key'] }}" role="menu" aria-labelledby="tn-{{ $m['key'] }}" hidden>
                  @foreach ($m['subs'] as $s)
                    <a role="menuitem" href="{{ $s['url'] }}" @if ($s['on']) class="on" aria-current="page" @endif>{{ $s['title'] }}</a>
                  @endforeach
                </div>
              @endif
            </li>
          @endforeach
          {{-- "More": when the row is too narrow for every menu, js/app.js moves the last menus in here (with their pages). --}}
          <li class="tnw tnmore" hidden>
            <button class="tn" id="tn-more" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="tndd-more" data-tndd><span>More</span><svg class="ico chev" aria-hidden="true"><use href="#i-down"/></svg></button>
            <div class="tndd end" id="tndd-more" role="menu" aria-labelledby="tn-more" hidden>
              @foreach ($menuBar['items'] as $m)
                <div class="tnmg" data-for="{{ $m['key'] }}" role="none" hidden>
                  <a role="menuitem" class="tnmh{{ $m['on'] ? ' on' : '' }}" href="{{ $m['url'] }}" @if ($m['on']) aria-current="true" @endif>{{ $m['title'] }}</a>
                  @foreach ($m['subs'] as $s)
                    <a role="menuitem" class="tnms{{ $s['on'] ? ' on' : '' }}" href="{{ $s['url'] }}" @if ($s['on']) aria-current="page" @endif>{{ $s['title'] }}</a>
                  @endforeach
                </div>
              @endforeach
            </div>
          </li>
        </ul>
      </nav>
      <div class="mtitle" id="mtitle" style="display:none">{{ $title ?? 'Nursery' }}</div>
      <span class="tsp"></span>
      @if ($alerts['items'])
        <button class="btn gh icon sm bellbtn {{ $alerts['total'] ? 'has' : '' }}" id="alertBtn" type="button" aria-haspopup="menu" aria-label="Notifications and alerts{{ $alerts['total'] ? ', '.$alerts['total'].' open' : '' }}"><svg class="ico"><use href="#i-bell"/></svg>@if ($alerts['total'])<span class="bellcount" aria-hidden="true">{{ $alerts['total'] > 99 ? '99+' : $alerts['total'] }}</span>@endif</button>
      @endif
      <button class="chipbtn" id="fyChip" type="button" aria-label="Financial year"><span class="lbl">FY</span> <b>{{ $financialYear }}</b></button>
      <button class="chipbtn" id="unitChip" type="button" aria-label="Unit" aria-haspopup="menu"><span class="lbl">Unit:</span> <b id="unitName">{{ $currentUnit?->name ?? 'No unit' }}</b><svg class="ico" style="width:12px"><use href="#i-down"/></svg></button>
      <button class="avatar clk" id="profile" type="button" aria-label="Your account" aria-haspopup="menu" style="border:0">{{ auth()->user()->initials() }}</button>
    </header>
    @isset($preview)
      <div class="banner" role="note"><svg class="ico"><use href="#i-info"/></svg><span><b>Sample screen from the design.</b> <span class="hide-m">This module is not built yet. Records are sample data and nothing is saved.</span></span></div>
    @endisset
    <main class="content" id="content" tabindex="-1"><div class="view" id="view">@yield('content')</div></main>
  </div>
</div>
<div id="layer"></div>
<div id="toasts" role="status" aria-live="polite"></div>

<template id="profileMenu">
  <div style="padding:8px 10px"><b>{{ auth()->user()->name }}</b><div class="cap muted">{{ auth()->user()->email }}</div><div class="cap muted">Role: {{ auth()->user()->roleName() }}</div></div>
  <div class="sep"></div>
  <form method="POST" action="{{ route('logout') }}">@csrf<button class="mi" type="submit"><svg class="ico"><use href="#i-x-c"/></svg>Sign out</button></form>
</template>
@if ($alerts['items'])
<template id="alertMenu">
  <div style="padding:6px 10px"><b>Notifications &amp; alerts</b></div>
  @foreach ($alerts['items'] as $a)
    <a class="mi alertmi" role="menuitem" href="{{ $a['url'] }}"><span class="dotc t-{{ $a['tone'] }}{{ $a['count'] ? '' : ' zero' }}" aria-hidden="true"></span><span style="flex:1">{{ $a['label'] }}</span><b class="num">{{ $a['count'] }}</b></a>
  @endforeach
</template>
@endif
@if ($alerts['due'])
{{-- Follow-up due date: red pop up (CRM changes). Shown once per browser session for the same list; Escape or Close hides it. --}}
<div class="overlay duepop" id="duePop" hidden data-sig="{{ today()->format('Ymd') }}-{{ collect($alerts['due'])->pluck('no')->implode(',') }}">
  <div class="scrim" data-x></div>
  <div class="modal" role="alertdialog" aria-modal="true" aria-labelledby="dueT" aria-describedby="dueD">
    <div class="mh"><svg class="ico"><use href="#i-alarm"/></svg><h3 id="dueT">Follow-ups due</h3></div>
    <div class="mb">
      <p id="dueD" class="small">{{ $alerts['dueCount'] }} follow-up(s) are due today or overdue.@if ($alerts['dueCount'] > count($alerts['due'])) Showing {{ count($alerts['due']) }} of {{ $alerts['dueCount'] }}; See all lists the rest.@endif</p>
      <ul class="duelist">
        @foreach ($alerts['due'] as $f)
          <li><a href="{{ $f['url'] }}"><b>{{ $f['no'] }}</b> · {{ $f['client'] }}</a><div class="cap">{{ $f['action'] }} · {{ $f['date'] }}{{ $f['late'] ? ' · '.$f['late'].' day(s) late' : ' · today' }}</div></li>
        @endforeach
      </ul>
    </div>
    <div class="mf" style="display:flex;gap:8px;justify-content:flex-end;padding:12px 20px 18px">
      <button class="btn gh" type="button" data-x>Close</button>
      <a class="btn pri" href="{{ route('crm.enquiries.list', ['view' => 'due']) }}">See all</a>
    </div>
  </div>
</div>
@endif
<template id="unitMenu">
  @forelse ($userUnits as $u)
    <form method="POST" action="{{ route('unit.switch') }}">@csrf<input type="hidden" name="unit" value="{{ $u->id }}">
      <button class="mi{{ $currentUnit && $currentUnit->id === $u->id ? ' on' : '' }}" type="submit">@if ($currentUnit && $currentUnit->id === $u->id)<svg class="ico"><use href="#i-check"/></svg>@else<span style="width:16px"></span>@endif{{ $u->name }}</button>
    </form>
  @empty
    <div class="cap muted" style="padding:6px 10px">No unit is allowed for your user.</div>
  @endforelse
  <div class="sep"></div><div class="cap muted" style="padding:4px 10px;max-width:250px">Units are the company's plants. You see the units your user is allowed.</div>
</template>

<script>
window.NURSERY_TOASTS = @json(array_values(array_filter([session('toast'), session('status')])));
window.NURSERY_ERRORS = @json($errors->has('delete') ? [$errors->first('delete')] : []);
</script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
<script src="{{ asset('js/picker.js') }}?v={{ filemtime(public_path('js/picker.js')) }}"></script>
<script src="{{ asset('js/datepicker.js') }}?v={{ filemtime(public_path('js/datepicker.js')) }}"></script>
@stack('scripts')
</body>
</html>
