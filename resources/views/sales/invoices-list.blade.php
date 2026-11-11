{{-- Invoice list: tax invoices, credit and debit notes of the unit and year on the top bar, paged on the server (P1). --}}
@php($df = config('nursery.date_format'))
@extends('layouts.app', ['title' => 'Invoice list', 'crumbs' => [['Sales', route('sales.invoices.index')], ['Invoice list', null]]])

@section('content')
<div class="pagehd">
  <h1>Invoice list</h1>
  <span class="badge b-neu">{{ \App\Support\Money::indian($rows->total()) }}</span>
  <span class="sp"></span>
  @can('tax-invoice.add')<a class="btn add" href="{{ route('sales.invoices.create') }}"><svg class="ico"><use href="#i-plus"/></svg>New tax invoice</a>@endcan
</div>

<nav class="tabs" aria-label="Invoice views" style="margin-bottom:12px">
  @foreach ($views as $k => $label)
    <a class="tabl {{ $view === $k ? 'on' : '' }}" href="{{ route('sales.invoices.list', array_filter(['view' => $k === 'all' ? null : $k, 'q' => $q ?: null])) }}" @if ($view === $k) aria-current="page" @endif>{{ $label }}</a>
  @endforeach
</nav>

<form class="filterbar" method="GET" action="{{ route('sales.invoices.list') }}" role="search">
  @if ($view !== 'all')<input type="hidden" name="view" value="{{ $view }}">@endif
  <div class="field grow"><label for="q">Search</label><input class="inp" id="q" name="q" value="{{ $q }}" placeholder="Invoice no or party name starts with"></div>
  <button class="btn sec" type="submit"><svg class="ico"><use href="#i-search"/></svg>Search</button>
  @if ($q !== '')<a class="btn gh" href="{{ route('sales.invoices.list', array_filter(['view' => $view === 'all' ? null : $view])) }}">Clear</a>@endif
</form>

<div class="tablecard">
  <table class="atable rtable">
    <thead><tr>
      @include('partials.sorth', ['label' => 'Invoice no', 'key' => 'no'])
      <th>Type</th><th>Party</th>
      @include('partials.sorth', ['label' => 'Date', 'key' => 'date'])
      <th class="r">Taxable value</th>
      @include('partials.sorth', ['label' => 'Grand total', 'key' => 'total'])
      <th>Status</th>
    </tr></thead>
    <tbody>
    @forelse ($rows as $r)
      <tr class="clk" onclick="location.href='{{ route('sales.invoices.show', $r) }}'">
        <td data-label="Invoice no" class="wide"><a class="rowlink" href="{{ route('sales.invoices.show', $r) }}"><b>{{ $r->invoice_no }}</b></a></td>
        <td data-label="Type">{{ $r->title() }}</td>
        <td data-label="Party">{{ $r->party?->name }}</td>
        <td data-label="Date" class="num">{{ $r->invoice_date?->format($df) }}</td>
        @php($cc = $r->currency && $r->currency->code !== 'INR' ? $r->currency->code.' ' : '')
        <td data-label="Taxable value" class="num r">{{ $cc }}{{ \App\Support\Money::indian($r->taxable_value, 2) }}</td>
        <td data-label="Grand total" class="num r">{{ $cc }}{{ \App\Support\Money::indian($r->grand_total, 2) }}</td>
        <td data-label="Status"><span class="badge {{ $r->isCancelled() ? 'b-err' : 'b-ok' }}">{{ $r->isCancelled() ? 'Cancelled' : 'Active' }}</span></td>
      </tr>
    @empty
      <tr><td colspan="7"><div class="empty">No invoices in this view.</div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@include('partials.pager', ['p' => $rows])
@endsection
