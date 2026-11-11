{{-- GRN list: the unit and year on the top bar, paged, searched and sorted on the server (rule P1). Cards on phones (rtable). --}}
@php($df = config('nursery.date_format'))
@extends('layouts.app', ['title' => 'GRN list', 'crumbs' => [['Purchase', route('purchase.grns.index')], ['GRN entry', route('purchase.grns.index')], ['GRN list', null]]])

@section('content')
<div class="pagehd">
  <h1>GRN list</h1>
  <span class="badge b-neu">{{ \App\Support\Money::indian($rows->total()) }}</span>
  <span class="sp"></span>
  @can('grn.add')<a class="btn add" href="{{ route('purchase.grns.create') }}"><svg class="ico"><use href="#i-plus"/></svg>New GRN entry</a>@endcan
</div>

<nav class="tabs" aria-label="GRN views" style="margin-bottom:12px">
  @foreach ($views as $k => $label)
    <a class="tabl {{ $view === $k ? 'on' : '' }}" href="{{ route('purchase.grns.list', array_filter(['view' => $k === 'all' ? null : $k, 'q' => $q ?: null])) }}" @if ($view === $k) aria-current="page" @endif>{{ $label }}</a>
  @endforeach
</nav>

<form class="filterbar" method="GET" action="{{ route('purchase.grns.list') }}" role="search">
  @if ($view !== 'all')<input type="hidden" name="view" value="{{ $view }}">@endif
  <div class="field grow"><label for="q">Search</label><input class="inp" id="q" name="q" value="{{ $q }}" placeholder="GRN no, invoice no or supplier name starts with"></div>
  <button class="btn sec" type="submit"><svg class="ico"><use href="#i-search"/></svg>Search</button>
  @if ($q !== '')<a class="btn gh" href="{{ route('purchase.grns.list', array_filter(['view' => $view === 'all' ? null : $view])) }}">Clear</a>@endif
</form>

<div class="tablecard">
  <table class="atable rtable">
    <thead><tr>
      @include('partials.sorth', ['label' => 'GRN no', 'key' => 'no'])
      <th>GRN date</th><th>Supplier</th><th>Invoice no</th>
      @include('partials.sorth', ['label' => 'Invoice date', 'key' => 'date'])
      @include('partials.sorth', ['label' => 'Invoice amount', 'key' => 'amount'])
      <th>Type</th><th>Status</th>
    </tr></thead>
    <tbody>
    @forelse ($rows as $r)
      <tr class="clk" onclick="location.href='{{ route('purchase.grns.show', $r) }}'">
        <td data-label="GRN no" class="wide"><a class="rowlink" href="{{ route('purchase.grns.show', $r) }}"><b>{{ $r->grn_no ?? 'Pending match' }}</b></a></td>
        <td data-label="GRN date" class="num">{{ $r->grn_date?->format($df) }}</td>
        <td data-label="Supplier">{{ $r->party?->name }}</td>
        <td data-label="Invoice no">{{ $r->invoice_no }}</td>
        <td data-label="Invoice date" class="num">{{ $r->invoice_date?->format($df) }}</td>
        <td data-label="Invoice amount" class="num r">{{ $r->currencyCode() !== 'INR' ? $r->currencyCode().' ' : '' }}{{ \App\Support\Money::indian($r->invoice_amount, 2) }}</td>
        <td data-label="Type">{{ $r->invoice_type }}</td>
        <td data-label="Status"><span class="badge {{ $r->badgeClass() }}">{{ $r->stateLabel() }}</span></td>
      </tr>
    @empty
      <tr><td colspan="8"><div class="empty">No GRNs in this view.</div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@include('partials.pager', ['p' => $rows])
@endsection
