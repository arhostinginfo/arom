@php($dtf = config('nursery.datetime_format'))
@extends('layouts.app', ['title' => 'Site visit list', 'crumbs' => [['CRM', route('crm.enquiries.index')], ['Site visit list', null]]])

@section('content')
<div class="pagehd">
  <h1>Site visit list</h1>
  <span class="badge b-neu">{{ number_format($rows->total()) }}</span>
  <span class="sp"></span>
  @can('site-visit.add')<a class="btn add" href="{{ route('crm.site-visits.create') }}"><svg class="ico"><use href="#i-plus"/></svg>Add site visit</a>@endcan
</div>

@include('partials.listbar', ['route' => 'crm.site-visits.list', 'label' => 'Site visit views', 'views' => $views, 'view' => $view, 'q' => '',
  'search' => false, 'placeholder' => '', 'sorts' => ['visit' => 'Visit date and time'], 'sort' => $sort, 'dir' => $dir])

<div class="tablecard">
  <table class="atable rtable fit">
    <thead><tr>
      @include('partials.sorth', ['label' => 'Visit date and time', 'key' => 'visit'])
      <th class="c2">Enquiry</th><th>Client</th><th class="c3">POC</th><th class="c2">Technical person</th><th class="c1">Next action</th><th>Status</th><th class="ph"><span class="sr-only">Actions</span></th>
    </tr></thead>
    <tbody>
    @forelse ($rows as $v)
      <tr class="clk" onclick="location.href='{{ route('crm.site-visits.show', $v) }}'">
        <td data-label="Visit" class="wide num"><a class="rowlink" href="{{ route('crm.site-visits.show', $v) }}"><b>{{ $v->visit_at->format($dtf) }}</b></a></td>
        <td data-label="Enquiry" class="c2">{{ $v->enquiry?->enquiry_no }}</td>
        <td data-label="Client">{{ $v->enquiry?->clientName() }}</td>
        <td data-label="POC" class="c3">{{ $v->poc }}</td>
        <td data-label="Technical person" class="c2">{{ $v->technicalPerson?->name }}</td>
        <td data-label="Next action" class="c1">{{ $v->next_action }}</td>
        <td data-label="Status"><span class="badge {{ $v->status === 'Done' ? 'b-ok' : 'b-warn' }}">{{ $v->status }}</span></td>
        <td data-label="" class="ph act"><a class="btn sec sm" href="{{ route('crm.site-visits.show', $v) }}">Open</a></td>
      </tr>
    @empty
      <tr><td colspan="8"><div class="empty">No site visits in this view.</div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@include('partials.pager', ['p' => $rows])
@endsection
