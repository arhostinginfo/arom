{{-- AMC › AMC Client Master: paged, searched and filtered on the server (rule P1), Import and Download CSV. --}}
@extends('layouts.app', ['title' => 'AMC Client Master'])

@section('content')
@include('amc.partials.assets')
<div class="pagehd">
  <h1>AMC Client Master</h1>
  <span class="badge b-neu">{{ \App\Support\Money::indian($rows->total()) }}</span>
  <span class="sp"></span>
  @can('amc-client-import.add')@include('partials.iobtn', ['kind' => 'import', 'label' => 'Import', 'open' => '#importLayer'])@endcan
  @include('partials.iobtn', ['kind' => 'csv', 'label' => 'Download CSV', 'href' => route('amc.clients.export')])
  @can('amc-client.add')<a class="btn add" href="{{ route('amc.clients.create') }}"><svg class="ico"><use href="#i-plus"/></svg>Add new</a>@endcan
</div>

@include('amc.partials.errors')

<form class="filterbar" method="GET" action="{{ route('amc.clients.index') }}" role="search">
  <div class="field grow"><label for="q">Search</label><input class="inp" id="q" name="q" value="{{ $q }}" placeholder="Customer name starts with"></div>
  <div class="field"><label for="vertical">Business segment</label>
    <select class="inp" id="vertical" name="vertical"><option value="">All</option>@foreach ($verticals as $o)<option value="{{ $o->id }}" @selected($vertical === $o->id)>{{ $o->name }}</option>@endforeach</select></div>
  <button class="btn sec" type="submit"><svg class="ico"><use href="#i-search"/></svg>Search</button>
  @if ($q !== '' || $vertical)<a class="btn gh" href="{{ route('amc.clients.index') }}">Clear</a>@endif
</form>

<div class="tablecard">
  <table class="atable rtable">
    <thead><tr>
      @include('partials.sorth', ['label' => 'Customer name', 'key' => 'name'])
      <th>Business segment</th><th class="r">Lines</th><th>Gardener</th>
      @include('partials.sorth', ['label' => 'Updated', 'key' => 'updated'])
    </tr></thead>
    <tbody>
    @forelse ($rows as $c)
      <tr class="clk" onclick="location.href='{{ route('amc.clients.edit', $c) }}'">
        <td data-label="Customer name" class="wide"><a class="rowlink" href="{{ route('amc.clients.edit', $c) }}"><b>{{ $c->name }}</b></a></td>
        <td data-label="Business segment">{{ $c->vertical?->name }}</td>
        <td data-label="Lines" class="r num">{{ $c->lines_count }}</td>
        <td data-label="Gardener">{{ $c->activeContract?->gardener?->name }}</td>
        <td data-label="Updated" class="num">{{ $c->updated_at?->format(config('nursery.date_format')) }}</td>
      </tr>
    @empty
      <tr><td colspan="5"><div class="empty">{{ $q !== '' || $vertical ? 'No clients match this search.' : 'No AMC clients yet.' }}</div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@include('partials.pager', ['p' => $rows])

@can('amc-client-import.add')
  @include('crm.partials.import', [
    'id' => 'importLayer', 'title' => 'Import AMC clients', 'action' => route('amc.clients.import'), 'template' => route('amc.clients.template'),
    'help' => '<p>One row per location line. Rows with the same Customer name make one client with several lines. Customer name and Business segment are needed; Location 1, Location 2, Planter, Planter qty, Plant and Plant qty are optional. Business segment, Planter and Plant must match their AMC masters by name. A customer already in the master is not imported again: change it on its page.</p><p>The whole file is checked first. If anything is wrong, nothing is saved and the rows to fix are listed.</p>',
  ])
@endcan
@endsection
