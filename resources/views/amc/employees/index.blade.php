{{-- AMC › AMC Employee master: paged, searched and filtered on the server (rule P1). --}}
@extends('layouts.app', ['title' => 'AMC Employee master'])

@section('content')
@include('amc.partials.assets')
@include('amc.partials.master-tabs', ['on' => 'employees'])
<div class="pagehd">
  <h1>AMC Employee master</h1>
  <span class="badge b-neu">{{ \App\Support\Money::indian($rows->total()) }}</span>
  <span class="sp"></span>
  @can('amc-employee.add')<a class="btn add" href="{{ route('amc.employees.create') }}"><svg class="ico"><use href="#i-plus"/></svg>Add new</a>@endcan
</div>

@include('amc.partials.errors')

<form class="filterbar" method="GET" action="{{ route('amc.employees.index') }}" role="search">
  <div class="field grow"><label for="q">Search</label><input class="inp" id="q" name="q" value="{{ $q }}" placeholder="Name, code or mobile starts with"></div>
  <div class="field"><label for="role">Role</label>
    <select class="inp" id="role" name="role"><option value="">All</option>@foreach (config('amc.employee_roles') as $o)<option @selected($role === $o)>{{ $o }}</option>@endforeach</select></div>
  <div class="field"><label for="status">Status</label>
    <select class="inp" id="status" name="status"><option value="">All</option>@foreach (['Active', 'Inactive'] as $o)<option @selected($status === $o)>{{ $o }}</option>@endforeach</select></div>
  <button class="btn sec" type="submit"><svg class="ico"><use href="#i-search"/></svg>Search</button>
  @if ($q !== '' || $role || $status)<a class="btn gh" href="{{ route('amc.employees.index') }}">Clear</a>@endif
</form>

<div class="tablecard">
  <table class="atable rtable">
    <thead><tr>
      <th><span class="sr">Photo</span></th>
      @include('partials.sorth', ['label' => 'Code', 'key' => 'code'])
      @include('partials.sorth', ['label' => 'Name', 'key' => 'name'])
      <th>Role</th><th>Mobile</th><th>Status</th>
    </tr></thead>
    <tbody>
    @forelse ($rows as $e)
      <tr class="clk" onclick="location.href='{{ route('amc.employees.edit', $e) }}'">
        <td data-label="">@include('amc.partials.photo', ['e' => $e])</td>
        <td data-label="Code" class="num"><a class="rowlink" href="{{ route('amc.employees.edit', $e) }}"><b>{{ $e->code }}</b></a></td>
        <td data-label="Name" class="wide">{{ $e->name }}</td>
        <td data-label="Role">{{ $e->role }}</td>
        <td data-label="Mobile" class="num">{{ $e->mobile }}</td>
        <td data-label="Status"><span class="badge {{ $e->status === 'Active' ? 'b-ok' : 'b-neu' }}">{{ $e->status }}</span></td>
      </tr>
    @empty
      <tr><td colspan="6"><div class="empty">{{ $q !== '' || $role || $status ? 'No employees match this search.' : 'No AMC employees yet.' }}</div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@include('partials.pager', ['p' => $rows])
@endsection
