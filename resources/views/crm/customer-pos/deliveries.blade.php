{{-- (26/11/2026) CRM › Pending deliveries: POs not delivered yet, the earliest due first; overdue ones in red. --}}
@php($df = config('nursery.date_format'))
@extends('layouts.app', ['title' => 'Pending deliveries'])

@section('content')
<div class="pagehd">
  <h1>Pending deliveries</h1>
  <span class="badge b-neu">{{ number_format($rows->total()) }}</span>
  @if ($overdueCount)<span class="badge b-err">{{ number_format($overdueCount) }} overdue</span>@endif
  <span class="sp"></span>
  <a class="btn gh sm" href="{{ route('crm.customer-pos.list') }}"><svg class="ico"><use href="#i-list"/></svg>PO list</a>
</div>

<form class="filterbar" method="GET" action="{{ route('crm.customer-pos.deliveries') }}" style="margin-bottom:10px">
  <div class="field"><label for="show">Show</label>
    <select class="inp" id="show" name="show" onchange="this.form.submit()">
      <option value="" @selected(! $overdueOnly)>All pending deliveries</option>
      <option value="overdue" @selected($overdueOnly)>Overdue only</option>
    </select></div>
  <noscript><button class="btn sec" type="submit">Show</button></noscript>
</form>

<div class="tablecard">
  <table class="atable rtable fit">
    <thead><tr><th>Client name</th><th>PO no</th><th>Target delivery date</th><th>Revised delivery date</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($rows as $r)
      @php($late = $r->deliveryOverdue())
      <tr class="clk" onclick="location.href='{{ route('crm.customer-pos.show', $r) }}'">
        <td data-label="Client name" class="wide">{{ $r->clientName() }}</td>
        <td data-label="PO no"><a class="rowlink" href="{{ route('crm.customer-pos.show', $r) }}"><b>{{ $r->po_no }}</b></a></td>
        <td data-label="Target delivery date" class="num">{{ $r->target_delivery_date?->format($df) }}</td>
        <td data-label="Revised delivery date" class="num">{{ $r->revised_delivery_date?->format($df) }}@if ($r->revised_reason)<div class="small muted">{{ $r->revised_reason }}</div>@endif</td>
        <td data-label="Status">@if ($late)<span class="badge b-err">Overdue {{ (int) $r->deliveryDue()->diffInDays(today()) }} day(s)</span>@else<span class="badge b-warn">Due {{ $r->deliveryDue()->format($df) }}</span>@endif</td>
      </tr>
    @empty
      <tr><td colspan="5"><div class="empty">{{ $overdueOnly ? 'No delivery is overdue.' : 'No pending deliveries: every PO with a target delivery date has been delivered.' }}</div></td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@include('partials.pager', ['p' => $rows])
@endsection
