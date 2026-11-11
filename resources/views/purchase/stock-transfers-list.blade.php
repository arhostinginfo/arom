{{-- Stock transfer list: transfers from or to the unit on the top bar in this financial year, paged, searched and sorted on the server (rule P1). --}}
@php($df = config('nursery.date_format'))
@extends('layouts.app', ['title' => 'Stock transfer list', 'crumbs' => [['Purchase', route('purchase.stock-transfers.index')], ['Stock transfer', route('purchase.stock-transfers.index')], ['Stock transfer list', null]]])

@section('content')
<div class="pagehd">
  <h1>Stock transfer list</h1>
  <span class="badge b-neu">{{ \App\Support\Money::indian($rows->total()) }}</span>
  <span class="sp"></span>
  <span class="cap muted">Unit: {{ $unit?->name ?? 'none' }}</span>
  @can('stock-transfer.add')<a class="btn add" href="{{ route('purchase.stock-transfers.create') }}"><svg class="ico"><use href="#i-plus"/></svg>New stock transfer</a>@endcan
</div>

<nav class="tabs" aria-label="Stock transfer views" style="margin-bottom:12px">
  @foreach ($views as $k => $label)
    <a class="tabl {{ $view === $k ? 'on' : '' }}" href="{{ route('purchase.stock-transfers.list', array_filter(['view' => $k === 'all' ? null : $k, 'q' => $q ?: null, 'sort' => request('sort'), 'dir' => request('dir'), 'per_page' => request('per_page')])) }}" @if ($view === $k) aria-current="page" @endif>{{ $label }}</a>
  @endforeach
</nav>

<form class="filterbar" method="GET" action="{{ route('purchase.stock-transfers.list') }}" role="search">
  @if ($view !== 'all')<input type="hidden" name="view" value="{{ $view }}">@endif
  <div class="field grow"><label for="q">Search</label><input class="inp" id="q" name="q" value="{{ $q }}" placeholder="Transfer no (or just its number)"></div>
  <button class="btn sec" type="submit"><svg class="ico"><use href="#i-search"/></svg>Search</button>
  @if ($q !== '')<a class="btn gh" href="{{ route('purchase.stock-transfers.list', array_filter(['view' => $view === 'all' ? null : $view])) }}">Clear</a>@endif
</form>

<div class="tablecard">
  <table class="atable rtable">
    <thead><tr>
      @include('partials.sorth', ['label' => 'Transfer no', 'key' => 'no'])
      @include('partials.sorth', ['label' => 'Date', 'key' => 'date'])
      <th>Location from</th><th>Location to</th><th>Source type</th><th class="r">Items</th><th>Status</th>
    </tr></thead>
    <tbody>
    @forelse ($rows as $r)
      <tr class="clk" onclick="location.href='{{ route('purchase.stock-transfers.show', $r) }}'">
        <td data-label="Transfer no" class="wide"><a class="rowlink" href="{{ route('purchase.stock-transfers.show', $r) }}"><b>{{ $r->transfer_no }}</b></a></td>
        <td data-label="Date" class="num">{{ $r->transfer_date?->format($df) }}</td>
        <td data-label="Location from">{{ $r->fromUnit?->name }}</td>
        <td data-label="Location to">{{ $r->toUnit?->name }}</td>
        <td data-label="Source type">{{ $r->source_type }}</td>
        <td data-label="Items" class="num r">{{ $r->lines_count }}</td>
        <td data-label="Status"><span class="badge {{ $r->badgeClass() }}">{{ $r->status }}</span></td>
      </tr>
    @empty
      <tr><td colspan="7"><div class="empty">No stock transfers in this view.</div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@include('partials.pager', ['p' => $rows])
@endsection
