{{-- AMC masters (22/11/2026): tabs to move between the masters the user may view. Param: $on (master key, or 'employees'). --}}
@php
  $u = auth()->user();
  $tabs = collect(config('amc.masters'))->filter(fn ($m) => $u->can($m['perm'].'.view'))->map(fn ($m, $k) => ['title' => $m['title'], 'url' => route("amc.masters.$k.index")])->all();
  if ($u->can('amc-employee.view')) { $tabs['employees'] = ['title' => 'AMC Employee master', 'url' => route('amc.employees.index')]; }
@endphp
<nav class="tabs amcmtabs" aria-label="AMC masters">
  <a class="tabl" href="{{ route('amc.masters.home') }}">All masters</a>
  @foreach ($tabs as $k => $t)
    <a class="tabl{{ $on === $k ? ' on' : '' }}" href="{{ $t['url'] }}" @if ($on === $k) aria-current="page" @endif>{{ $t['title'] }}</a>
  @endforeach
</nav>
