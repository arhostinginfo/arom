@extends('layouts.app', ['title' => 'Employee master', 'crumbs' => [['Admin', route('admin.users.index')], ['Masters', route('admin.masters.index')], ['Employee master', null]]])

@section('content')
<div class="pagehd">
  <h1>Employee master</h1>
  <span class="badge b-neu">{{ number_format($rows->total()) }}</span>
  <span class="sp"></span>
  @can('masters.add')<a class="btn add" href="{{ route('admin.employees.create') }}"><svg class="ico"><use href="#i-plus"/></svg>Add employee</a>@endcan
</div>

<form class="filterbar" method="GET" action="{{ route('admin.employees.index') }}" role="search">
  <div class="field grow"><label for="q">Search</label><input class="inp" id="q" name="q" value="{{ $q }}" placeholder="Name or employee code starts with"></div>
  <div class="field"><label for="status">Status</label>
    <select class="inp" id="status" name="status"><option value="">All</option>@foreach (['Active', 'Inactive'] as $s)<option @selected($status === $s)>{{ $s }}</option>@endforeach</select></div>
  <button class="btn sec" type="submit"><svg class="ico"><use href="#i-search"/></svg>Search</button>
  @if ($q !== '' || $status)<a class="btn gh" href="{{ route('admin.employees.index') }}">Clear</a>@endif
</form>

<div class="tablecard">
  <table class="atable rtable">
    <thead><tr>
      @include('partials.sorth', ['label' => 'Code', 'key' => 'code'])
      @include('partials.sorth', ['label' => 'Name', 'key' => 'name'])
      <th>Designation</th><th>Department</th><th>Mobile</th><th>Login user</th><th>Status</th>
    </tr></thead>
    <tbody>
    @forelse ($rows as $e)
      <tr class="clk" onclick="location.href='{{ route('admin.employees.edit', $e) }}'">
        <td data-label="Code" class="num">{{ $e->code }}</td>
        <td data-label="Name" class="wide"><a class="rowlink" href="{{ route('admin.employees.edit', $e) }}"><b>{{ $e->name }}</b></a></td>
        <td data-label="Designation">{{ $e->designation }}</td>
        <td data-label="Department">{{ $e->department }}</td>
        <td data-label="Mobile" class="num">{{ $e->mobile }}</td>
        <td data-label="Login user">{{ $e->user?->name }}</td>
        <td data-label="Status"><span class="badge {{ $e->status === 'Active' ? 'b-ok' : 'b-neu' }}">{{ $e->status }}</span></td>
      </tr>
    @empty
      <tr><td colspan="7"><div class="empty">No employees match this search.</div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@include('partials.pager', ['p' => $rows])
@endsection
