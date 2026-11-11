@extends('layouts.app', ['title' => 'Users list', 'crumbs' => [['Admin', route('admin.users.index')], ['Users list', null]]])

@section('content')
<div class="pagehd">
  <h1>Users list</h1>
  <span class="badge b-neu">{{ $users->total() }} {{ \Illuminate\Support\Str::plural('user', $users->total()) }}</span>
  <span class="sp"></span>
 
  @can('users.add')
    <a class="btn add" href="{{ route('admin.users.create') }}"><svg class="ico"><use href="#i-plus"/></svg>Add user</a>
  @endcan
</div>

<form class="filterbar" method="GET" action="{{ route('admin.users.index') }}" role="search">
  <div class="field grow"><label for="q">Search</label><input class="inp" id="q" name="q" value="{{ request('q') }}" placeholder="Name, login email or mobile"></div>
  <div class="field"><label for="role">Role</label>
    <select class="inp" id="role" name="role"><option value="">All roles</option>@foreach ($roles as $r)<option @selected(request('role') === $r)>{{ $r }}</option>@endforeach</select></div>
  <div class="field"><label for="status">Status</label>
    <select class="inp" id="status" name="status"><option value="">All</option>@foreach (['Active', 'Inactive'] as $s)<option @selected(request('status') === $s)>{{ $s }}</option>@endforeach</select></div>
  {{-- On a phone the table header is hidden, so sorting is offered here (shown at phone width only). --}}
  <div class="field only-m"><label for="sort">Sort by</label>
    <select class="inp" id="sort" name="sort">@foreach ($sortOptions as $k => $label)<option value="{{ $k }}" @selected($sort === $k)>{{ $label }}</option>@endforeach</select></div>
  <div class="field only-m"><label for="dir">Order</label>
    <select class="inp" id="dir" name="dir"><option value="asc" @selected($dir === 'asc')>Ascending</option><option value="desc" @selected($dir === 'desc')>Descending</option></select></div>
  @if (request()->filled('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
  <button class="btn sec" type="submit"><svg class="ico"><use href="#i-search"/></svg>Search</button>
  @if (request()->hasAny(['q', 'role', 'status']))<a class="btn gh" href="{{ route('admin.users.index') }}">Clear</a>@endif
</form>

<div class="tablecard">
  <table class="atable rtable">
    <thead><tr>
      @include('partials.sorth', ['label' => 'Name', 'key' => 'name'])
      @include('partials.sorth', ['label' => 'Login email', 'key' => 'email'])
      <th>Mobile</th><th>Role</th><th>Units allowed</th>
      @include('partials.sorth', ['label' => 'Status', 'key' => 'status'])
      @include('partials.sorth', ['label' => 'Last login', 'key' => 'last_login'])
    </tr></thead>
    <tbody>
    @forelse ($users as $u)
      <tr class="clk" onclick="location.href='{{ route('admin.users.edit', $u) }}'">
        <td data-label="Name" class="wide"><a class="rowlink" href="{{ route('admin.users.edit', $u) }}"><b>{{ $u->name }}</b></a>@if ($u->is(auth()->user())) <span class="badge b-pri">You</span>@endif</td>
        <td data-label="Login email">{{ $u->email }}</td>
        <td data-label="Mobile" class="num">{{ $u->mobileDisplay() }}</td>
        <td data-label="Role">{{ $u->roleName() }}@if ($u->permissions->count()) <span class="badge b-acc" title="Extra permissions given to this user">+{{ $u->permissions->count() }}</span>@endif</td>
        <td data-label="Units allowed">{{ $u->units->count() === $unitCount && $unitCount > 1 ? 'All' : $u->units->pluck('name')->implode(', ') }}</td>
        <td data-label="Status"><span class="badge {{ $u->isActive() ? 'b-ok' : 'b-neu' }}">{{ $u->status->value }}</span></td>
        <td data-label="Last login" class="num">{{ $u->last_login_at?->format(config('nursery.datetime_format')) ?? 'Never' }}</td>
      </tr>
    @empty
      <tr><td colspan="7"><div class="empty">No users match this search.</div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@include('partials.pager', ['p' => $users])
@endsection
