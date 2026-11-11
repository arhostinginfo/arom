{{-- Admin › Masters › Party master: customers and suppliers, paged, searched and filtered on the server (rule P1). --}}
@extends('layouts.app', ['title' => 'Party master', 'crumbs' => [['Admin', route('admin.users.index')], ['Masters', route('admin.masters.index')], ['Party master', null]]])

@section('content')
@php($cps = app(\App\Services\PageSetupService::class)->form('party'))
<div class="pagehd">
  <h1>Party master</h1>
  <span class="badge b-neu">{{ \App\Support\Money::indian($rows->total()) }}</span>
  <span class="sp"></span>
  @can('masters.add')@include('partials.iobtn', ['kind' => 'import', 'label' => 'Import', 'open' => '#importLayer'])@endcan
  @include('partials.iobtn', ['kind' => 'csv', 'label' => 'Download CSV', 'href' => route('admin.parties.export')])
  @can('masters.add')<a class="btn add" href="{{ route('admin.parties.create') }}"><svg class="ico"><use href="#i-plus"/></svg>Add party</a>@endcan
</div>

@if ($errors->any())
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>{{ $errors->has('file') ? 'The file was not imported. Nothing was saved.' : 'Something needs your attention.' }}</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif

<form class="filterbar" method="GET" action="{{ route('admin.parties.index') }}" role="search">
  <div class="field grow"><label for="q">Search</label><input class="inp" id="q" name="q" value="{{ $q }}" placeholder="Name or GST no starts with"></div>
  <div class="field"><label for="type">Type</label>
    <select class="inp" id="type" name="type"><option value="">All</option>@foreach (\App\Models\Party::TYPES as $t)<option @selected($type === $t)>{{ $t }}</option>@endforeach</select></div>
  <div class="field"><label for="status">Status</label>
    <select class="inp" id="status" name="status"><option value="">All</option>@foreach (['Active', 'Inactive'] as $s)<option @selected($status === $s)>{{ $s }}</option>@endforeach</select></div>
  <button class="btn sec" type="submit"><svg class="ico"><use href="#i-search"/></svg>Search</button>
  @if ($q !== '' || $type || $status)<a class="btn gh" href="{{ route('admin.parties.index') }}">Clear</a>@endif
</form>

<div class="tablecard">
  <table class="atable rtable">
    <thead><tr>
      @include('partials.sorth', ['label' => 'Name', 'key' => 'name'])
      @if ($cps->show('gstin'))<th>GST no</th>@endif
      <th>Type</th>
      @if ($cps->show('payment_terms'))<th>Payment terms</th>@endif
      @if ($cps->show('currency_id'))<th>Currency</th>@endif
      <th>Status</th>
    </tr></thead>
    <tbody>
    @forelse ($rows as $p)
      <tr class="clk" onclick="location.href='{{ route('admin.parties.edit', $p) }}'">
        <td data-label="Name" class="wide"><a class="rowlink" href="{{ route('admin.parties.edit', $p) }}"><b>{{ $p->name }}</b></a></td>
        @if ($cps->show('gstin'))<td data-label="GST no" class="num">{{ $p->gstin }}</td>@endif
        <td data-label="Type">{{ $p->type }}</td>
        @if ($cps->show('payment_terms'))<td data-label="Payment terms">{{ $p->payment_terms }}</td>@endif
        @if ($cps->show('currency_id'))<td data-label="Currency">{{ $p->currency?->code }}</td>@endif
        <td data-label="Status"><span class="badge {{ $p->status === 'Active' ? 'b-ok' : 'b-neu' }}">{{ $p->status }}</span></td>
      </tr>
    @empty
      <tr><td colspan="6"><div class="empty">{{ $q !== '' || $type || $status ? 'No parties match this search.' : 'No parties yet.' }}</div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@include('partials.pager', ['p' => $rows])

@can('masters.add')
  @include('crm.partials.import', [
    'id' => 'importLayer', 'title' => 'Import parties', 'action' => route('admin.parties.import'), 'template' => route('admin.parties.template'),
    'help' => '<p>One row per party. <b>Name</b> and <b>Type</b> (Customer, Supplier or Both) are needed; Status defaults to Active and Currency to INR (a code from the Currency master). GST no must be 15 characters and cannot repeat.</p><p>The whole file is checked first. If anything is wrong, nothing is saved and the rows to fix are listed.</p>',
  ])
@endcan
@endsection

@push('scripts')
<script src="{{ asset('js/crm.js') }}?v={{ filemtime(public_path('js/crm.js')) }}"></script>
@endpush
