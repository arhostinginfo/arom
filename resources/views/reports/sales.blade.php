{{-- Sales reports: the download options open on the same page (A21); the file is made on the server (A20, S3). --}}
@extends('layouts.app', ['title' => $title, 'crumbs' => [['Reports', route('app.page', ['page' => 'reports'])], [$group.' · '.$title, null]]])

@section('content')
<div class="repcard">
  <div class="pagehd"><h1>{{ $title }}</h1><span class="badge b-neu">{{ $group }}</span></div>

  @if ($errors->any())
    <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check the options marked in red.</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
  @endif
  @if ($report === 'stock')
    <div class="alert info"><svg class="ico"><use href="#i-info"/></svg><span>{{ \App\Http\Controllers\Reports\SalesReportController::STOCK_NOTE }}</span></div>
  @endif

  <form class="sectcard" method="GET" action="{{ $report === 'stock' ? route('reports.sales.stock-download') : route('reports.sales.download', $report) }}">
    <div class="sh"><h2>Download options</h2><span class="sp"></span></div>
    <div class="sb">
      <div class="fgrid">
        <div class="field">
          <label for="from">From date<span class="req">*</span></label>
          <input class="inp num @error('from') err @enderror" id="from" name="from" value="{{ old('from', $from) }}" placeholder="dd/mm/yyyy" inputmode="numeric" required>
          <span class="hint">{{ $dateLabel }}</span>
        </div>
        <div class="field">
          <label for="to">To date<span class="req">*</span></label>
          <input class="inp num @error('to') err @enderror" id="to" name="to" value="{{ old('to', $to) }}" placeholder="dd/mm/yyyy" inputmode="numeric" required>
        </div>
        <div class="field">
          <label for="unit">Unit</label>
          <select class="inp" id="unit" name="unit">
            <option value="">All my units</option>
            @foreach ($units as $u)<option value="{{ $u->id }}" @selected((string) old('unit') === (string) $u->id)>{{ $u->name }}</option>@endforeach
          </select>
        </div>
        @if ($report !== 'stock')
          <div class="field">
            <label for="party">Party</label>
            <div class="picker" data-picker="field" data-url="{{ route('sales.parties.lookup') }}" data-placeholder="All customers · type a name">
              <select class="inp" id="party" name="party">
                <option value="">All customers</option>
                @foreach ($parties as $id => $name)<option value="{{ $id }}" @selected((string) old('party') === (string) $id)>{{ $name }}</option>@endforeach
              </select>
            </div>
          </div>
        @endif
        @if ($report === 'orders')
          <div class="field">
            <label for="status">Status</label>
            <select class="inp" id="status" name="status">
              <option value="">All</option>
              @foreach (\App\Models\SalesOrder::STATUSES as $s)<option @selected(old('status') === $s)>{{ $s }}</option>@endforeach
            </select>
          </div>
        @elseif ($report === 'stock')
          <div class="field">
            <span style="font-size:13px;font-weight:500">Show</span>
            <label class="chk"><input type="hidden" name="below" value="0"><input type="checkbox" name="below" value="1" @checked(old('below'))> Below minimum stock only</label>
          </div>
        @else
          <div class="field">
            {{-- Summary / Detailed and Format are drop-downs instead of the segmented radio buttons (02/11/2026). --}}
            <label for="detail">Summary / Detailed</label>
            <select class="inp" id="detail" name="detail">
              <option value="summary" @selected(old('detail', 'summary') === 'summary')>Summary</option>
              <option value="detailed" @selected(old('detail') === 'detailed')>Detailed</option>
            </select>
          </div>
        @endif
        <div class="field">
          <label for="format">Format</label>
          <select class="inp" id="format" name="format">
            <option value="csv" @selected(old('format', 'csv') === 'csv')>Excel (CSV)</option>
            <option value="pdf" @selected(old('format') === 'pdf')>PDF</option>
          </select>
        </div>
      </div>
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>
        @if ($report === 'orders')
          Lists each item of the sales orders whose start date is between the two dates, with the quantity invoiced and the balance.
        @elseif ($report === 'sales')
          Lists the tax invoices dated between the two dates: one row per invoice (Summary) or per item (Detailed), with taxable value, GST and the currency (export invoices are in the customer's currency).
        @elseif ($report === 'export')
          Lists the tax invoices to export customers (billed in a foreign currency, without GST under LUT) dated between the two dates.
        @elseif ($report === 'credit-debit')
          Lists the credit notes and debit notes to customers dated between the two dates.
        @else
          Lists each active item with its stock at the start of the period, stock in and out in the period, the closing stock and whether it is below its minimum. Tax invoices take stock out and credit notes bring it back; approved GRNs add the accepted quantity and approved stock transfers move stock between units.
        @endif
        Cancelled invoices are left out. Dates default to the current financial year.
      </span></div>
      <div style="display:flex;gap:8px;justify-content:flex-end"><button class="btn pri" type="submit"><svg class="ico"><use href="#i-download"/></svg>Download</button></div>
    </div>
  </form>
  <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>The report downloads on this page, without opening a new page. Reports are made on the server.</span></div>
</div>
@endsection
