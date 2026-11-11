@php($df = config('nursery.date_format'))
@extends('layouts.app', ['title' => 'Enquiry list', 'crumbs' => [['CRM', route('crm.enquiries.index')], ['Enquiry list', null]]])

@section('content')
<div class="pagehd">
  <h1>Enquiry list</h1>
  <span class="badge b-neu">{{ number_format($rows->total()) }}</span>
  <span class="sp"></span>
  @can('enquiry.add')<a class="btn add" href="{{ route('crm.enquiries.create') }}"><svg class="ico"><use href="#i-plus"/></svg>Add enquiry</a>@endcan
</div>

@include('partials.listbar', ['route' => 'crm.enquiries.list', 'label' => 'Enquiry views', 'views' => $views, 'view' => $view, 'q' => $q,
  'placeholder' => 'Enquiry no or client name', 'sorts' => ['date' => 'Enquiry date', 'next' => 'Next action date', 'closing' => 'Expected closing', 'no' => 'Enquiry no'], 'sort' => $sort, 'dir' => $dir])

<div class="tablecard">
  <table class="atable rtable fit">
    <thead><tr>
      @include('partials.sorth', ['label' => 'Enquiry no', 'key' => 'no'])
      @include('partials.sorth', ['label' => 'Enquiry date', 'key' => 'date', 'cls' => 'c2'])
      <th>Client</th><th class="c3">Source</th><th class="c1">Rating</th><th class="c2">Assigned to</th><th class="c3">Next action</th>
      @include('partials.sorth', ['label' => 'Next action date', 'key' => 'next'])
      @include('partials.sorth', ['label' => 'Expected closing', 'key' => 'closing', 'cls' => 'c3'])
      <th>Status</th><th class="ph"><span class="sr-only">Actions</span></th>
    </tr></thead>
    <tbody>
    @forelse ($rows as $e)
      <tr class="clk" onclick="location.href='{{ route('crm.enquiries.show', $e) }}'">
        <td data-label="Enquiry no" class="wide"><a class="rowlink" href="{{ route('crm.enquiries.show', $e) }}"><b>{{ $e->enquiry_no }}</b></a></td>
        <td data-label="Enquiry date" class="num c2">{{ $e->enquiry_date->format($df) }}</td>
        <td data-label="Client">{{ $e->clientName() }}</td>
        <td data-label="Source" class="c3">{{ $e->source?->name }}</td>
        <td data-label="Rating" class="c1">@if ($e->lead_rating)<span class="badge {{ $e->lead_rating->badgeClass() }}">{{ $e->lead_rating->value }}</span>@endif</td>
        <td data-label="Assigned to" class="c2">{{ $e->assignee?->name }}</td>
        <td data-label="Next action" class="c3">{{ $e->next_action }}</td>
        <td data-label="Next follow-up" class="num {{ $e->isDue() ? 'duetxt' : '' }}">{{ $e->next_action_date?->format($df) }}@if ($e->dueLabel()) @include('crm.partials.duetag', ['label' => $e->dueLabel()])@endif</td>
        <td data-label="Expected closing" class="num c3">{{ $e->expected_closing_date?->format($df) }}</td>
        <td data-label="Status"><span class="badge {{ $e->status->badgeClass() }}">{{ $e->status->value }}</span></td>
        <td data-label="" class="ph act"><a class="btn sec sm" href="{{ route('crm.enquiries.show', $e) }}">Open</a></td>
      </tr>
    @empty
      <tr><td colspan="11"><div class="empty">No enquiries in this view.</div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@include('partials.pager', ['p' => $rows])
@endsection
