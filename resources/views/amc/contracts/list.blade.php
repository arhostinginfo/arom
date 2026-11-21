{{-- AMC › AMC list: every contract, colour per row, paged, searched and filtered on the server (rule P1). --}}
@php($df = config('nursery.date_format'))
@php($colours = \App\Models\Amc\AmcContract::COLOURS)
@extends('layouts.app', ['title' => 'AMC list'])

@section('content')
@include('amc.partials.assets')
<div class="pagehd">
  <h1>AMC list</h1>
  <span class="badge b-neu">{{ \App\Support\Money::indian($rows->total()) }}</span>
  <span class="sp"></span>
  @include('partials.iobtn', ['kind' => 'csv', 'label' => 'Download CSV', 'href' => route('amc.contracts.export', array_filter(['q' => $q ?: null, 'status' => $status, 'colour' => $colour, 'renewal' => $renewal ? '1' : null]))])
  @can('amc-contract.add')<a class="btn add" href="{{ route('amc.contract.create') }}"><svg class="ico"><use href="#i-plus"/></svg>Add new</a>@endcan
</div>

@include('amc.partials.errors')

<div class="legend amclegend" aria-label="Colours">
  @foreach (['green' => 'Green: Active, end date more than '.config('amc.due_days').' days away', 'red' => 'Red: due within '.config('amc.due_days').' days',
      'black' => 'Black: overdue, end date passed', 'grey' => 'Grey: Inactive'] as $k => $text)
    <span><i class="amcdot {{ $k }}" aria-hidden="true"></i>{{ $text }}</span>
  @endforeach
</div>

<form class="filterbar" method="GET" action="{{ route('amc.contracts.list') }}" role="search">
  <div class="field grow"><label for="q">Search</label><input class="inp" id="q" name="q" value="{{ $q }}" placeholder="Customer name starts with, or AMC no"></div>
  <div class="field"><label for="status">Status</label>
    <select class="inp" id="status" name="status"><option value="">All</option>@foreach (['Active', 'Inactive'] as $o)<option @selected($status === $o)>{{ $o }}</option>@endforeach</select></div>
  <div class="field"><label for="colour">Colour</label>
    <select class="inp" id="colour" name="colour"><option value="">All</option>@foreach ($colours as $k => [$label])<option value="{{ $k }}" @selected($colour === $k)>{{ ucfirst($k) }} · {{ $label }}</option>@endforeach</select></div>
  @if ($renewal)<input type="hidden" name="renewal" value="1">@endif
  <button class="btn sec" type="submit"><svg class="ico"><use href="#i-search"/></svg>Search</button>
  @if ($q !== '' || $status || $colour || $renewal)<a class="btn gh" href="{{ route('amc.contracts.list') }}">Clear</a>@endif
</form>
@if ($renewal)<p class="hint" data-filter="renewal"><span class="badge b-warn">Renewals due</span> Active contracts ending in the next {{ config('amc.renew_days') }} days.</p>@endif

<div class="tablecard">
  <table class="atable rtable amclist">
    <thead><tr>
      @include('partials.sorth', ['label' => 'AMC no', 'key' => 'added'])
      <th>Customer</th><th>AMC type</th>
      @include('partials.sorth', ['label' => 'Start date', 'key' => 'start'])
      @include('partials.sorth', ['label' => 'End date', 'key' => 'end'])
      <th class="r">AMC amount</th><th>Billing cycle</th><th>Gardener</th><th>Colour</th>
    </tr></thead>
    <tbody>
    @forelse ($rows as $r)
      @php($col = $r->colour())
      @php($left = $r->daysLeft())
      <tr class="clk c-{{ $col }}" onclick="location.href='{{ route('amc.contract.show', $r) }}'">
        <td data-label="AMC no" class="num"><a class="rowlink" href="{{ route('amc.contract.show', $r) }}"><b>{{ $r->number() }}</b></a></td>
        <td data-label="Customer" class="wide">{{ $r->client?->name }}</td>
        <td data-label="AMC type">{{ $r->type?->name }}</td>
        <td data-label="Start date" class="num">{{ $r->start_date?->format($df) }}</td>
        <td data-label="End date" class="num amcend">{{ $r->end_date?->format($df) }}@if ($r->status === 'Active')<div class="cap">{{ $left < 0 ? abs($left).' day(s) overdue' : ($left === 0 ? 'ends today' : $left.' day(s) left') }}</div>@endif</td>
        <td data-label="AMC amount" class="r num">{{ \App\Support\Money::indian($r->amount, 2) }}</td>
        <td data-label="Billing cycle">{{ $r->billing_cycle }}</td>
        <td data-label="Gardener">{{ $r->gardener?->name }}</td>
        <td data-label="Colour"><span class="badge {{ $colours[$col][1] }}" data-colour="{{ $col }}">{{ $colours[$col][0] }}</span></td>
      </tr>
    @empty
      <tr><td colspan="9"><div class="empty">{{ $q !== '' || $status || $colour || $renewal ? 'No contracts match this search.' : 'No AMC contracts yet.' }}</div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@include('partials.pager', ['p' => $rows])

@if (auth()->user()->isAdmin())
  <form method="POST" action="{{ route('amc.contracts.purge') }}" data-confirm="Delete every deployment image uploaded more than 1 year ago? PO, agreement and delivery challan files are kept." style="margin-top:14px">@csrf
    <button class="btn gh sm" type="submit" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Delete deployment images older than 1 year</button>
    <span class="cap muted">Admin only. This also runs by itself every night.</span>
  </form>
@endif
@endsection
