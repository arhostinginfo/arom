@extends('layouts.app', ['title' => 'Activity log', 'crumbs' => [['Admin', route('admin.users.index')], ['Activity log', null]]])

@section('content')
<div class="pagehd">
  <h1>Activity log</h1>
  <span class="sp"></span>
 
</div>

<form class="filterbar" method="GET" action="{{ route('admin.activity.index') }}" role="search">
  <div class="field grow"><label for="q">Search</label><input class="inp" id="q" name="q" value="{{ request('q') }}" placeholder="User, role or description"></div>
  <div class="field"><label for="action">Operation</label>
    <select class="inp" id="action" name="action"><option value="">All operations</option>@foreach ($actions as $k => $label)<option value="{{ $k }}" @selected(request('action') === $k)>{{ $label }}</option>@endforeach</select></div>
  <div class="field"><label for="user">Done by</label>
    <div class="picker" data-picker="field" data-url="{{ route('lookup.users') }}" data-placeholder="Anyone · type a name" style="min-width:200px">
      <select class="inp" id="user" name="user"><option value="">Anyone</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected((string) request('user') === (string) $u->id)>{{ $u->name }}</option>@endforeach</select>
    </div></div>
  <div class="field"><label for="from">From date</label><input class="inp num @error('from') err @enderror" id="from" name="from" value="{{ request('from') }}" placeholder="dd/mm/yyyy" inputmode="numeric" style="min-width:120px;width:130px" @error('from') aria-invalid="true" aria-describedby="from-err" @enderror>@include('partials.ferr', ['name' => 'from'])</div>
  <div class="field"><label for="to">To date</label><input class="inp num @error('to') err @enderror" id="to" name="to" value="{{ request('to') }}" placeholder="dd/mm/yyyy" inputmode="numeric" style="min-width:120px;width:130px" @error('to') aria-invalid="true" aria-describedby="to-err" @enderror>@include('partials.ferr', ['name' => 'to'])</div>
  @foreach (['sort', 'dir', 'per_page'] as $keep)@if (request()->filled($keep))<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">@endif @endforeach
  <button class="btn sec" type="submit"><svg class="ico"><use href="#i-filter"/></svg>Filter</button>
  @if (request()->hasAny(['q', 'action', 'user', 'from', 'to']))<a class="btn gh" href="{{ route('admin.activity.index') }}">Clear</a>@endif
</form>

<div class="tablecard">
  <table class="atable rtable">
    <thead><tr>@include('partials.sorth', ['label' => 'Date and time', 'key' => 'date'])<th>Operation</th><th>Description</th><th>Done by</th><th>IP address</th></tr></thead>
    <tbody>
    @forelse ($logs as $log)
      <tr class="clk" onclick="location.href='{{ route('admin.activity.show', $log) }}'">
        <td data-label="Date and time" class="wide num"><a class="rowlink" href="{{ route('admin.activity.show', $log) }}">{{ $log->created_at->format(config('nursery.datetime_format')) }}</a></td>
        <td data-label="Operation"><span class="badge {{ $log->badgeClass() }}">{{ $log->actionLabel() }}</span></td>
        <td data-label="Description" style="white-space:normal;min-width:260px">{{ $log->description }}</td>
        <td data-label="Done by">{{ $log->user_name ?? '—' }}</td>
        <td data-label="IP address" class="num">{{ $log->ip_address }}</td>
      </tr>
    @empty
      <tr><td colspan="5"><div class="empty">No log entries match these filters.</div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@include('partials.pager', ['p' => $logs])
@endsection
