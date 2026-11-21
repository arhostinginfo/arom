{{-- AMC › Return & Replace: paged, searched and filtered on the server (rule P1), Download CSV. --}}
@php($df = config('nursery.date_format'))
@extends('layouts.app', ['title' => 'Return & Replace'])

@section('content')
@include('amc.partials.assets')
<div class="pagehd">
  <h1>Return &amp; Replace</h1>
  <span class="badge b-neu">{{ \App\Support\Money::indian($rows->total()) }}</span>
  <span class="sp"></span>
  @include('partials.iobtn', ['kind' => 'csv', 'label' => 'Download CSV', 'href' => route('amc.returns.export', array_filter(['q' => $q ?: null, 'quality' => $quality]))])
  @can('amc-return.add')<a class="btn add" href="{{ route('amc.returns.create') }}"><svg class="ico"><use href="#i-plus"/></svg>Add new</a>@endcan
</div>

@include('amc.partials.errors')

<form class="filterbar" method="GET" action="{{ route('amc.returns.index') }}" role="search">
  <div class="field grow"><label for="q">Search</label><input class="inp" id="q" name="q" value="{{ $q }}" placeholder="Customer name starts with"></div>
  <div class="field"><label for="quality">Quality</label>
    <select class="inp" id="quality" name="quality"><option value="">All</option>@foreach (config('amc.qualities') as $o)<option @selected($quality === $o)>{{ $o }}</option>@endforeach</select></div>
  <button class="btn sec" type="submit"><svg class="ico"><use href="#i-search"/></svg>Search</button>
  @if ($q !== '' || $quality)<a class="btn gh" href="{{ route('amc.returns.index') }}">Clear</a>@endif
</form>

<div class="tablecard">
  <table class="atable rtable">
    <thead><tr>
      @include('partials.sorth', ['label' => 'Entry no', 'key' => 'added'])
      @include('partials.sorth', ['label' => 'Request date', 'key' => 'request'])
      <th>Customer</th><th>Location</th><th>Plant</th><th class="r">Qty</th><th>Replace date</th><th>Quality</th>
    </tr></thead>
    <tbody>
    @forelse ($rows as $r)
      <tr class="clk" onclick="location.href='{{ route('amc.returns.edit', $r) }}'">
        <td data-label="Entry no" class="num"><a class="rowlink" href="{{ route('amc.returns.edit', $r) }}"><b>{{ $r->number() }}</b></a></td>
        <td data-label="Request date" class="num">{{ $r->request_date?->format($df) }}</td>
        <td data-label="Customer" class="wide">{{ $r->client?->name }}</td>
        <td data-label="Location">{{ implode(' · ', array_filter([$r->location_1, $r->location_2])) }}</td>
        <td data-label="Plant">{{ $r->plant?->name }}{{ $r->plant_size ? ' ('.$r->plant_size.')' : '' }}</td>
        <td data-label="Qty" class="r num">{{ \App\Support\Money::indian($r->qty) }}</td>
        <td data-label="Replace date" class="num">{{ $r->replace_date?->format($df) }}</td>
        <td data-label="Quality"><span class="badge {{ $r->qualityBadge() }}">{{ $r->quality }}</span></td>
      </tr>
    @empty
      <tr><td colspan="8"><div class="empty">{{ $q !== '' || $quality ? 'No entries match this search.' : 'No Return & Replace entries yet.' }}</div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@include('partials.pager', ['p' => $rows])
@endsection
