{{-- AMC › Dashboard (21/11/2026): active clients, monthly AMC revenue for the financial year, renewals, overdue and new orders. --}}
@php
  $df = config('nursery.date_format');
  $money = fn ($v) => \App\Support\Money::rupees($v);
  $num = fn ($v) => \App\Support\Money::indian($v);
@endphp
@extends('layouts.app', ['title' => 'AMC dashboard'])

@section('content')
@include('amc.partials.assets')
<div class="pagehd">
  <h1>AMC dashboard</h1>
  <span class="sp"></span>
  <span class="cap muted">AMC · FY {{ $fy->label() }}</span>
</div>

<div class="kpis dashkpis">
  <a class="card kpi" href="{{ route('reports.amc.report', ['status' => 'Active', 'in' => 'amc']) }}" data-tile="active-clients"><span class="ovl">Total active clients</span><span class="v">{{ $num($d['activeClients']) }}</span><span class="d">With an Active contract today</span></a>
  <div class="card kpi" data-tile="month-revenue"><span class="ovl">AMC revenue · {{ $d['thisMonthLabel'] }}</span><span class="v">{{ $money($d['thisMonth'] ?? 0) }}</span><span class="d">{{ $money($d['revenueTotal']) }} in FY {{ $fy->label() }}</span></div>
  <a class="card kpi {{ $d['renewalCount'] ? 'kpidue' : '' }}" href="{{ route('reports.amc.report', ['renewal' => 1, 'in' => 'amc']) }}" data-tile="renewals"><span class="ovl">Renewals due</span><span class="v">{{ $num($d['renewalCount']) }}</span><span class="d">Ending in the next {{ config('amc.renew_days') }} days</span></a>
  <a class="card kpi {{ $d['overdueCount'] ? 'kpidue' : '' }}" href="{{ route('reports.amc.report', ['colour' => 'black', 'in' => 'amc']) }}" data-tile="overdue"><span class="ovl">Overdue</span><span class="v">@if ($d['overdueCount'])<svg class="ico" aria-hidden="true"><use href="#i-alert"/></svg>@endif{{ $num($d['overdueCount']) }}</span><span class="d">End date passed, still Active</span></a>
  <div class="card kpi" data-tile="new-orders"><span class="ovl">New orders</span><span class="v">{{ $num($d['newCount']) }}</span><span class="d">Contracts added in the last 60 days</span></div>
</div>

<div class="grid2">
  <div class="sectcard">
    <div class="sh"><h2>Renewal alerts</h2><span class="badge b-warn">{{ $num($d['renewalCount']) }}</span></div>
    <div class="sb">
      @if ($d['renewals']->isEmpty())
        <div class="empty">No contract ends in the next {{ config('amc.renew_days') }} days.</div>
      @else
        <ul class="amclistmini">
          @foreach ($d['renewals'] as $c)
            @php($left = $c->daysLeft())
            <li><a href="{{ route('amc.contract.show', $c) }}"><b>{{ $c->number() }}</b> · {{ $c->client?->name }}</a><span class="badge {{ $left <= config('amc.due_days') ? 'b-err' : 'b-warn' }}">{{ $c->end_date->format($df) }} · {{ $left === 0 ? 'today' : $left.' day(s) left' }}</span></li>
          @endforeach
        </ul>
      @endif
    </div>
  </div>
  <div class="sectcard">
    <div class="sh"><h2>Overdue</h2><span class="badge b-black">{{ $num($d['overdueCount']) }}</span>@if ($d['overdueCount'] > count($d['overdue']))<span class="sp"></span><a class="btn gh sm" href="{{ route('reports.amc.report', ['colour' => 'black', 'in' => 'amc']) }}">View all</a>@endif</div>
    <div class="sb">
      @if ($d['overdue']->isEmpty())
        <div class="empty">No overdue contracts.</div>
      @else
        <ul class="amclistmini">
          @foreach ($d['overdue'] as $c)
            <li><a href="{{ route('amc.contract.show', $c) }}"><b>{{ $c->number() }}</b> · {{ $c->client?->name }}</a><span class="badge b-black">Ended {{ $c->end_date->format($df) }} · {{ abs($c->daysLeft()) }} day(s) ago</span></li>
          @endforeach
        </ul>
      @endif
    </div>
  </div>
  <div class="sectcard">
    <div class="sh"><h2>Monthly AMC revenue · FY {{ $fy->label() }}</h2></div>
    <div class="sb">
      <div class="tablecard">
        <table class="atable rtable">
          <thead><tr><th>Month</th><th class="r">AMC revenue</th></tr></thead>
          <tbody>
            @foreach ($d['revenue'] as $m)
              <tr><td data-label="Month">{{ $m['month'] }}</td><td data-label="AMC revenue" class="r num">{{ \App\Support\Money::indian($m['amount'], 2) }}</td></tr>
            @endforeach
          </tbody>
          <tfoot><tr><th>Total</th><th class="r num">{{ \App\Support\Money::indian($d['revenueTotal'], 2) }}</th></tr></tfoot>
        </table>
      </div>
      <div class="cap muted">Each Active contract's AMC amount is spread evenly over the months of its period.</div>
    </div>
  </div>
  <div class="sectcard">
    <div class="sh"><h2>New orders received · last 60 days</h2><span class="badge b-ok">{{ $num($d['newCount']) }}</span></div>
    <div class="sb">
      @if ($d['newOrders']->isEmpty())
        <div class="empty">No contracts added in the last 60 days.</div>
      @else
        <ul class="amclistmini">
          @foreach ($d['newOrders'] as $c)
            <li><a href="{{ route('amc.contract.show', $c) }}"><b>{{ $c->number() }}</b> · {{ $c->client?->name }}</a><span class="cap muted">{{ $c->created_at->format($df) }} · {{ $money($c->amount) }}</span></li>
          @endforeach
        </ul>
        @if ($d['newCount'] > count($d['newOrders']))<div class="cap muted">Showing the latest {{ count($d['newOrders']) }}.</div>@endif
      @endif
    </div>
  </div>
</div>
@endsection
