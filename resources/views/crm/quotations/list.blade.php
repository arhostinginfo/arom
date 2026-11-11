@php($df = config('nursery.date_format'))
@extends('layouts.app', ['title' => 'Quotation list', 'crumbs' => [['CRM', route('crm.enquiries.index')], ['Quotation list', null]]])

@section('content')
<div class="pagehd">
  <h1>Quotation list</h1>
  <span class="badge b-neu">{{ number_format($rows->total()) }}</span>
  <span class="sp"></span>
  <span class="cap muted">Current revision of each quotation</span>
</div>

@include('partials.listbar', ['route' => 'crm.quotations.list', 'label' => 'Quotation views', 'views' => $views, 'view' => $view, 'q' => $q,
  'placeholder' => 'Quotation no or enquiry no', 'sorts' => ['date' => 'Date', 'no' => 'Quotation no', 'total' => 'Total'], 'sort' => $sort, 'dir' => $dir])

<div class="tablecard">
  <table class="atable rtable fit">
    <thead><tr>
      @include('partials.sorth', ['label' => 'Quotation no', 'key' => 'no'])
      <th class="c1">Rev</th><th class="c2">Enquiry no</th><th>Client</th>
      @include('partials.sorth', ['label' => 'Date', 'key' => 'date', 'cls' => 'c2'])
      <th class="c3">Sales person</th>
      @include('partials.sorth', ['label' => 'Total', 'key' => 'total'])
      <th>Status</th><th class="ph"><span class="sr-only">Actions</span></th>
    </tr></thead>
    <tbody>
    @forelse ($rows as $r)
      <tr class="clk" onclick="location.href='{{ route('crm.quotations.show', $r) }}'">
        <td data-label="Quotation no" class="wide"><a class="rowlink" href="{{ route('crm.quotations.show', $r) }}"><b>{{ $r->quotation_no }}</b></a></td>
        <td data-label="Rev" class="num c1">{{ $r->revision_no }}</td>
        <td data-label="Enquiry no" class="c2">{{ $r->enquiry_no }}</td>
        <td data-label="Client">{{ $r->customerName() }}</td>
        <td data-label="Date" class="num c2">{{ $r->quotation_date?->format($df) }}</td>
        <td data-label="Sales person" class="c3">{{ $r->salesPerson?->name }}</td>
        <td data-label="Total" class="num r">{{ $r->currency?->code }} {{ \App\Support\Money::indian($r->grand_total, 2) }}</td>
        <td data-label="Status"><span class="badge {{ $r->status->badgeClass() }}">{{ $r->status->value }}</span></td>
        <td data-label="" class="ph act"><a class="btn sec sm" href="{{ route('crm.quotations.show', $r) }}">Open</a></td>
      </tr>
    @empty
      <tr><td colspan="9"><div class="empty">No quotations in this view.</div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@include('partials.pager', ['p' => $rows])
@endsection
