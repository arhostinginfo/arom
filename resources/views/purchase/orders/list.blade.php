{{-- Purchase order list: the unit and year on the top bar, paged, searched and sorted on the server (rule P1). --}}
@php($df = config('nursery.date_format'))
@extends('layouts.app', ['title' => 'Purchase order list', 'crumbs' => [['Purchase', route('purchase.orders.index')], ['Purchase order', route('purchase.orders.index')], ['Purchase order list', null]]])

@section('content')
<div class="pagehd">
  <h1>Purchase order list</h1>
  <span class="badge b-neu">{{ \App\Support\Money::indian($rows->total()) }}</span>
  <span class="sp"></span>
  @can('purchase-order.add')<a class="btn add" href="{{ route('purchase.orders.create') }}"><svg class="ico"><use href="#i-plus"/></svg>New purchase order</a>@endcan
</div>

<nav class="tabs" aria-label="Purchase order views" style="margin-bottom:12px">
  @foreach ($views as $k => $label)
    <a class="tabl {{ $view === $k ? 'on' : '' }}" href="{{ route('purchase.orders.list', array_filter(['view' => $k === 'all' ? null : $k, 'q' => $q ?: null])) }}" @if ($view === $k) aria-current="page" @endif>{{ $label }}</a>
  @endforeach
</nav>

<form class="filterbar" method="GET" action="{{ route('purchase.orders.list') }}" role="search">
  @if ($view !== 'all')<input type="hidden" name="view" value="{{ $view }}">@endif
  <div class="field grow"><label for="q">Search</label><input class="inp" id="q" name="q" value="{{ $q }}" placeholder="PO no or supplier name starts with"></div>
  <button class="btn sec" type="submit"><svg class="ico"><use href="#i-search"/></svg>Search</button>
  @if ($q !== '')<a class="btn gh" href="{{ route('purchase.orders.list', array_filter(['view' => $view === 'all' ? null : $view])) }}">Clear</a>@endif
</form>

<div class="tablecard">
  <table class="atable rtable">
    <thead><tr>
      @include('partials.sorth', ['label' => 'PO no', 'key' => 'no'])
      @include('partials.sorth', ['label' => 'PO date', 'key' => 'date'])
      <th>Supplier</th><th>Delivery date</th>
      @include('partials.sorth', ['label' => 'Total', 'key' => 'total'])
      <th>Status</th>
    </tr></thead>
    <tbody>
    @forelse ($rows as $r)
      <tr class="clk" onclick="location.href='{{ route('purchase.orders.show', $r) }}'">
        <td data-label="PO no" class="wide"><a class="rowlink" href="{{ route('purchase.orders.show', $r) }}"><b>{{ $r->po_no }}</b></a></td>
        <td data-label="PO date" class="num">{{ $r->po_date?->format($df) }}</td>
        <td data-label="Supplier">{{ $r->party?->name }}</td>
        <td data-label="Delivery date" class="num">{{ $r->delivery_date?->format($df) }}</td>
        <td data-label="Total" class="num r">{{ $r->currencyCode() !== 'INR' ? $r->currencyCode().' ' : '' }}{{ \App\Support\Money::indian($r->grand_total, 2) }}</td>
        <td data-label="Status"><span class="badge {{ $r->badgeClass() }}">{{ $r->status }}</span></td>
      </tr>
    @empty
      <tr><td colspan="6"><div class="empty">No purchase orders in this view.</div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@include('partials.pager', ['p' => $rows])
@endsection
