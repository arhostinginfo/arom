{{-- Admin › Masters › Item master: paged, searched and filtered on the server (rule P1). --}}
@extends('layouts.app', ['title' => 'Item master', 'crumbs' => [['Admin', route('admin.users.index')], ['Masters', route('admin.masters.index')], ['Item master', null]]])

@section('content')
@php($cps = app(\App\Services\PageSetupService::class)->form('item'))
<div class="pagehd">
  <h1>Item master{{ $partyName ? ' · '.$partyName : '' }}</h1>
  <span class="badge b-neu">{{ \App\Support\Money::indian($rows->total()) }}</span>
  <span class="sp"></span>
  @can('masters.add')@include('partials.iobtn', ['kind' => 'import', 'label' => 'Import', 'open' => '#importLayer'])@endcan
  @include('partials.iobtn', ['kind' => 'csv', 'label' => 'Download CSV', 'href' => route('admin.items.export')])
  @can('masters.add')<a class="btn add" href="{{ route('admin.items.create') }}"><svg class="ico"><use href="#i-plus"/></svg>Add item</a>@endcan
</div>

@if ($errors->any())
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>{{ $errors->has('file') ? 'The file was not imported. Nothing was saved.' : 'Something needs your attention.' }}</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif

<form class="filterbar" method="GET" action="{{ route('admin.items.index') }}" role="search">
  @if (request('party'))<input type="hidden" name="party" value="{{ (int) request('party') }}">@endif
  <div class="field grow"><label for="q">Search</label><input class="inp" id="q" name="q" value="{{ $q }}" placeholder="Item no, IPN or description starts with"></div>
  <div class="field"><label for="type">Type</label>
    <select class="inp" id="type" name="type"><option value="">All</option>@foreach (\App\Models\Item::TYPES as $t)<option @selected($type === $t)>{{ $t }}</option>@endforeach</select></div>
  <div class="field"><label for="status">Status</label>
    <select class="inp" id="status" name="status"><option value="">All</option>@foreach (['Active', 'Inactive'] as $s)<option @selected($status === $s)>{{ $s }}</option>@endforeach</select></div>
  <button class="btn sec" type="submit"><svg class="ico"><use href="#i-search"/></svg>Search</button>
  @if ($q !== '' || $type || $status || request('party'))<a class="btn gh" href="{{ route('admin.items.index') }}">{{ request('party') ? 'All items' : 'Clear' }}</a>@endif
</form>

<div class="tablecard">
  <table class="atable rtable">
    <thead><tr>
      @include('partials.sorth', ['label' => 'Item no', 'key' => 'no'])
      @include('partials.sorth', ['label' => 'Description', 'key' => 'description'])
      @if ($cps->show('internal_item_no'))<th>IPN</th>@endif
      @if ($cps->show('party_id'))<th>Party</th>@endif
      <th>Type</th><th>HSN/SAC</th><th>UOM</th>
      @if ($cps->show('price'))<th class="r">Price</th>@endif
      <th>Status</th>
    </tr></thead>
    <tbody>
    @forelse ($rows as $i)
      <tr class="clk" onclick="location.href='{{ route('admin.items.edit', $i) }}'">
        <td data-label="Item no" class="num"><a class="rowlink" href="{{ route('admin.items.edit', $i) }}"><b>{{ $i->item_no }}</b></a></td>
        <td data-label="Description" class="wide">{{ $i->description }}</td>
        @if ($cps->show('internal_item_no'))<td data-label="IPN" class="num">{{ $i->internal_item_no }}</td>@endif
        @if ($cps->show('party_id'))<td data-label="Party">{{ $i->party?->name }}</td>@endif
        <td data-label="Type">{{ $i->type }}</td>
        <td data-label="HSN/SAC" class="num">{{ $i->hsn?->code }}</td>
        <td data-label="UOM">{{ $i->uom?->code }}</td>
        @if ($cps->show('price'))<td data-label="Price" class="r num">{{ \App\Support\Money::indian($i->price, 2) }} {{ $i->currency?->code !== 'INR' ? $i->currency?->code : '' }}</td>@endif
        <td data-label="Status"><span class="badge {{ $i->status === 'Active' ? 'b-ok' : 'b-neu' }}">{{ $i->status }}</span></td>
      </tr>
    @empty
      <tr><td colspan="9"><div class="empty">{{ $q !== '' || $type || $status ? 'No items match this search.' : 'No items yet.' }}</div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@include('partials.pager', ['p' => $rows])

@can('masters.add')
  @include('crm.partials.import', [
    'id' => 'importLayer', 'title' => 'Import items', 'action' => route('admin.items.import'), 'template' => route('admin.items.template'),
    'help' => '<p>One row per item. Item description, Type (Sales, Purchase or Both), HSN/SAC, UOM and Tax structure are needed and must match their masters (Party name, Category and Currency too, when given). Leave Item no blank for an automatic number from the category\'s prefix.</p><p>The whole file is checked first. If anything is wrong, nothing is saved and the rows to fix are listed.</p>',
  ])
@endcan
@endsection

@push('scripts')
<script src="{{ asset('js/crm.js') }}?v={{ filemtime(public_path('js/crm.js')) }}"></script>
@endpush
