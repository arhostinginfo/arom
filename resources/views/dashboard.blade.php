@php
  $df = config('nursery.date_format');
  // Indian grouping (₹34,60,000), the same as the rest of the app.
  $money = fn ($v) => \App\Support\Money::rupees($v);
  $num = fn ($v, $dec = 0) => \App\Support\Money::indian($v, $dec);
@endphp
@extends('layouts.app', ['title' => 'Dashboard', 'crumbs' => [['Dashboard', null]]])

@section('content')
<div class="pagehd">
  <h1>Dashboard</h1>
  <span class="sp"></span>
  @if ($d)<span class="cap muted">CRM · FY {{ $d['year'] }} · {{ $unit?->name }}</span>@endif
</div>

@if ($crmTiles)
  {{-- CRM page counts (22/10/2026): one tile per CRM page the user may open, in menu order; each opens its list. --}}
  <section class="dashsect" aria-labelledby="dash-crm-h">
    <div class="dashsect-h"><h2 id="dash-crm-h">CRM</h2><span class="cap muted">Select a count to open its list</span></div>
    <div class="kpis dashkpis">
      @foreach ($crmTiles as $t)
        <a class="card kpi {{ ! empty($t['alert']) ? 'kpidue' : '' }}" href="{{ $t['url'] }}" aria-label="{{ $num($t['count']) }} {{ $t['noun'] }} – open list" data-tile="{{ $t['key'] }}"><span class="ovl">{{ $t['label'] }}</span><span class="v">@if (! empty($t['alert']))<svg class="ico" aria-hidden="true"><use href="#i-alert"/></svg>@endif{{ $num($t['count']) }}</span><span class="d">{{ $t['hint'] }}</span></a>
      @endforeach
    </div>
  </section>
@endif

@if (! $d)
  @if (! $crmTiles || ! $unit)
  <div class="alert info"><svg class="ico"><use href="#i-info"/></svg><span>{{ $unit ? 'Your dashboard tiles come with your modules as they are built.' : 'No unit is allowed for your user.' }}</span></div>
  @endif
@else
  <div class="kpis dashkpis">
    <a class="card kpi" href="{{ route('crm.enquiries.list') }}"><span class="ovl">Enquiry vs PO received</span><span class="v">{{ $num($d['kpis']['enquiries']) }} / {{ $num($d['kpis']['pos']) }}</span><span class="d">{{ $d['kpis']['conversion'] }}% converted this year</span></a>
    <a class="card kpi" href="{{ route('crm.enquiries.list', ['view' => 'open']) }}"><span class="ovl">Total open leads</span><span class="v">{{ $num($d['kpis']['open']) }}</span><span class="d">Leads without a PO, not lost</span></a>
    <a class="card kpi" href="{{ route('crm.enquiries.list', ['view' => 'hot']) }}"><span class="ovl">Hot leads</span><span class="v">{{ $num($d['kpis']['hot']) }}</span><span class="d">Open leads rated Hot</span></a>
    <a class="card kpi {{ $due['count'] ? 'kpidue' : '' }}" href="{{ route('crm.enquiries.list', ['view' => 'due']) }}"><span class="ovl">Activities due / overdue</span><span class="v">@if ($due['count'])<svg class="ico" aria-hidden="true"><use href="#i-alert"/></svg>@endif{{ $num($due['count']) }}</span><span class="d">{{ $num($due['overdue']) }} overdue</span></a>
    <a class="card kpi" href="{{ route('crm.quotations.list', ['view' => 'po-pending']) }}"><span class="ovl">Verbal wins / PO pending</span><span class="v">{{ $num($d['kpis']['po_pending']) }}</span><span class="d">Accepted quotations without a PO</span></a>
    <a class="card kpi" href="{{ route('crm.enquiries.list', ['view' => 'closing']) }}"><span class="ovl">Expected closing this month</span><span class="v">{{ $num($d['kpis']['closing']) }}</span><span class="d">{{ $money($d['kpis']['closing_value']) }} estimated</span></a>
  </div>

  <div class="grid2">
    <div class="sectcard chartwide">
      <div class="sh"><h2>Month wise enquiries and PO received</h2></div>
      <div class="sb">
        @include('partials.chart-bars', ['c' => $d['monthly'], 'label' => 'Enquiries received and PO received by month'])
        <details class="chartdata"><summary>Show the numbers</summary>
          <table class="atable"><thead><tr><th>Month</th><th class="r">Enquiries received</th><th class="r">PO received</th></tr></thead>
            <tbody>@foreach ($d['monthlyTable'] as $r)<tr><td>{{ $r['month'] }}</td><td class="r num">{{ $num($r['enquiries']) }}</td><td class="r num">{{ $num($r['pos']) }}</td></tr>@endforeach</tbody></table>
        </details>
        <div class="cap muted">PO value this year: {{ $money($d['kpis']['po_value']) }}</div>
      </div>
    </div>
    <div class="sectcard">
      <div class="sh"><h2>Lead ageing</h2><span class="sp"></span>@can('reports-crm.view')<a class="btn gh sm" href="{{ route('reports.crm.lead-ageing') }}">Lead ageing report</a>@endcan</div>
      <div class="sb">
        <div class="hbars">
          @foreach ($d['ageing'] as $label => $n)
            <div class="hbar"><span class="hl">{{ $label }}</span><span class="ht"><span class="hf" style="width:{{ round($n / $d['ageingMax'] * 100) }}%"></span></span><span class="hn num">{{ \App\Support\Money::indian($n) }}</span></div>
          @endforeach
        </div>
        <div class="cap muted">Open leads by days since the enquiry date.</div>
      </div>
    </div>
    <div class="sectcard">
      <div class="sh"><h2>Enquiry source performance</h2></div>
      <div class="sb">@include('partials.chart-pie', ['c' => $d['sources'], 'label' => 'Source wise contribution of enquiries this year'])</div>
    </div>
    <div class="sectcard">
      <div class="sh"><h2>Enquiries by category</h2></div>
      <div class="sb">@include('partials.chart-pie', ['c' => $d['categories'], 'label' => 'Enquiries by service category this year'])</div>
    </div>
  </div>

  <div class="grid2" style="margin-top:16px">
    <div class="sectcard">
      <div class="sh"><h2>Activities due / overdue</h2><span class="sp"></span><a class="btn gh sm" href="{{ route('crm.enquiries.list', ['view' => 'due']) }}">View all</a></div>
      <div class="sb" style="padding-top:6px">
        @if (! $due['list'])
          <div class="empty">Nothing here now.</div>
        @else
          <div class="tablecard"><table class="atable rtable">
            <thead><tr><th>Enquiry</th><th>Client</th><th>Next action</th><th>Date</th></tr></thead>
            <tbody>
            @foreach ($due['list'] as $f)
              <tr class="clk" onclick="location.href='{{ $f['url'] }}'">
                <td data-label="Enquiry" class="wide"><a class="rowlink" href="{{ $f['url'] }}"><b>{{ $f['no'] }}</b></a></td>
                <td data-label="Client">{{ $f['client'] }}</td>
                <td data-label="Next action">{{ $f['action'] }}</td>
                <td data-label="Date" class="num duetxt">{{ $f['date'] }} @include('crm.partials.duetag', ['label' => $f['late'] ? $f['late'].' day(s) late' : 'Due today'])</td>
              </tr>
            @endforeach
            </tbody>
          </table></div>
        @endif
      </div>
    </div>
    @foreach ([
      ['Hot leads', $d['hot'], route('crm.enquiries.list', ['view' => 'hot'])],
      ['Expected closing this month', $d['closing'], route('crm.enquiries.list', ['view' => 'closing'])],
    ] as [$title, $list, $all])
      <div class="sectcard">
        <div class="sh"><h2>{{ $title }}</h2><span class="sp"></span><a class="btn gh sm" href="{{ $all }}">View all</a></div>
        <div class="sb" style="padding-top:6px">
          @if ($list->isEmpty())
            <div class="empty">Nothing here now.</div>
          @else
            <div class="tablecard"><table class="atable rtable">
              <thead><tr><th>Enquiry</th><th>Client</th><th>Next action</th><th>Date</th></tr></thead>
              <tbody>
              @foreach ($list as $e)
                <tr class="clk" onclick="location.href='{{ route('crm.enquiries.show', $e) }}'">
                  <td data-label="Enquiry" class="wide"><a class="rowlink" href="{{ route('crm.enquiries.show', $e) }}"><b>{{ $e->enquiry_no }}</b></a></td>
                  <td data-label="Client">{{ $e->clientName() }}</td>
                  <td data-label="Next action">{{ $e->next_action }}</td>
                  <td data-label="Date" class="num {{ $e->isDue() ? 'duetxt' : '' }}">{{ ($title === 'Expected closing this month' ? $e->expected_closing_date : $e->next_action_date)?->format($df) }}@if ($title !== 'Expected closing this month' && $e->dueLabel()) @include('crm.partials.duetag', ['label' => $e->dueLabel()])@endif</td>
                </tr>
              @endforeach
              </tbody>
            </table></div>
          @endif
        </div>
      </div>
    @endforeach
    <div class="sectcard">
      <div class="sh"><h2>Verbal wins / PO pending</h2><span class="sp"></span><a class="btn gh sm" href="{{ route('crm.quotations.list', ['view' => 'po-pending']) }}">View all</a></div>
      <div class="sb" style="padding-top:6px">
        @if ($d['poPending']->isEmpty())
          <div class="empty">Nothing here now.</div>
        @else
          <div class="tablecard"><table class="atable rtable">
            <thead><tr><th>Quotation</th><th>Client</th><th class="r">Total</th></tr></thead>
            <tbody>
            @foreach ($d['poPending'] as $q)
              <tr class="clk" onclick="location.href='{{ route('crm.quotations.show', $q) }}'">
                <td data-label="Quotation" class="wide"><a class="rowlink" href="{{ route('crm.quotations.show', $q) }}"><b>{{ $q->quotation_no }} Rev {{ $q->revision_no }}</b></a></td>
                <td data-label="Client">{{ $q->customerName() }}</td>
                <td data-label="Total" class="r num">{{ $num($q->grand_total, 2) }}</td>
              </tr>
            @endforeach
            </tbody>
          </table></div>
        @endif
      </div>
    </div>
  </div>
  @can('reports-crm.view')
    <div class="ruleh" style="margin-top:12px"><svg class="ico"><use href="#i-info"/></svg><span>Lead to PO status of every lead: <a href="{{ route('reports.crm.lead-report') }}">Lead report</a>. Figures are for the unit on the top bar and refresh when entries change.</span></div>
  @endcan
@endif
@endsection
