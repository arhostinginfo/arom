{{-- Sales order list: the unit and year on the top bar, paged, searched and sorted on the server (rule P1). --}}
@php($df = config('nursery.date_format'))
@extends('layouts.app', ['title' => 'Sales order list', 'crumbs' => [['Sales', route('sales.invoices.index')], ['Sales order', route('sales.orders.index')], ['Sales order list', null]]])

@section('content')
<div class="pagehd">
  <h1>Sales order list</h1>
  <span class="badge b-neu">{{ \App\Support\Money::indian($rows->total()) }}</span>
  <span class="sp"></span>
  @can('sales-order.add')<a class="btn add" href="{{ route('sales.orders.create') }}"><svg class="ico"><use href="#i-plus"/></svg>New sales order</a>@endcan
</div>

<nav class="tabs" aria-label="Sales order views" style="margin-bottom:12px">
  @foreach ($views as $k => $label)
    <a class="tabl {{ $view === $k ? 'on' : '' }}" href="{{ route('sales.orders.list', array_filter(['view' => $k === 'all' ? null : $k, 'q' => $q ?: null])) }}" @if ($view === $k) aria-current="page" @endif>{{ $label }}</a>
  @endforeach
</nav>

<form class="filterbar" method="GET" action="{{ route('sales.orders.list') }}" role="search">
  @if ($view !== 'all')<input type="hidden" name="view" value="{{ $view }}">@endif
  <div class="field grow"><label for="q">Search</label><input class="inp" id="q" name="q" value="{{ $q }}" placeholder="SO no, PO number or party name starts with"></div>
  <button class="btn sec" type="submit"><svg class="ico"><use href="#i-search"/></svg>Search</button>
  @if ($q !== '')<a class="btn gh" href="{{ route('sales.orders.list', array_filter(['view' => $view === 'all' ? null : $view])) }}">Clear</a>@endif
</form>

<div class="tablecard">
  <table class="atable rtable">
    <thead><tr>
      @include('partials.sorth', ['label' => 'SO no', 'key' => 'no'])
      <th>Party</th><th>PO number</th>
      @include('partials.sorth', ['label' => 'Start date', 'key' => 'date'])
      <th>End date</th>
      @include('partials.sorth', ['label' => 'Total', 'key' => 'total'])
      <th>Status</th>
    </tr></thead>
    <tbody>
    @forelse ($rows as $r)
      <tr class="clk" onclick="location.href='{{ route('sales.orders.show', $r) }}'">
        <td data-label="SO no" class="wide"><a class="rowlink" href="{{ route('sales.orders.show', $r) }}"><b>{{ $r->so_no }}</b></a></td>
        <td data-label="Party">{{ $r->party?->name }}</td>
        <td data-label="PO number">{{ $r->po_number }}</td>
        <td data-label="Start date" class="num">{{ $r->start_date?->format($df) }}</td>
        <td data-label="End date" class="num">{{ $r->end_date?->format($df) }}</td>
        <td data-label="Total" class="num r">{{ \App\Support\Money::indian($r->total_value, 2) }}</td>
        <td data-label="Status"><span class="badge {{ $r->badgeClass() }}">{{ $r->status }}</span></td>
      </tr>
    @empty
      <tr><td colspan="7"><div class="empty">No sales orders in this view.</div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@include('partials.pager', ['p' => $rows])
@endsection
