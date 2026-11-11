@extends('layouts.app', ['title' => $title, 'crumbs' => [['Reports', route('app.page', ['page' => 'reports'])], ['CRM · '.$title, null]]])

@section('content')
<div class="repcard">
  <div class="pagehd"><h1>{{ $title }}</h1><span class="badge b-neu">CRM</span></div>

  @if ($errors->any())
    <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check the options marked in red.</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
  @endif

  <form class="sectcard" method="GET" action="{{ route('reports.crm.download', $report) }}">
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
        @if ($report === 'prospects')
          <div class="field">
            <label for="market">Domestic / Export</label>
            <select class="inp" id="market" name="market">
              <option value="">Both</option>
              @foreach (\App\Enums\Market::values() as $m)<option @selected(old('market') === $m)>{{ $m }}</option>@endforeach
            </select>
          </div>
        @else
          @if ($report !== 'lead-ageing')
          <div class="field">
            <label for="status">Status</label>
            <select class="inp" id="status" name="status">
              <option value="">All</option>
              @foreach ($statuses as $s)<option @selected(old('status') === $s)>{{ $s }}</option>@endforeach
            </select>
          </div>
          @endif
          <div class="field">
            <label for="rating">Lead rating</label>
            <select class="inp" id="rating" name="rating">
              <option value="">All</option>
              @foreach ($ratings as $s)<option @selected(old('rating') === $s)>{{ $s }}</option>@endforeach
            </select>
          </div>
        @endif
        <div class="field">
          {{-- Format as a drop-down instead of the segmented radio buttons (02/11/2026). --}}
          <label for="format">Format</label>
          <select class="inp" id="format" name="format">
            <option value="csv" @selected(old('format', 'csv') === 'csv')>Excel (CSV)</option>
            <option value="pdf" @selected(old('format') === 'pdf')>PDF</option>
          </select>
        </div>
      </div>
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>
        @if ($report === 'prospects')
          Lists prospects added between the two dates, with every prospect field.
        @elseif ($report === 'enquiries')
          Lists the enquiries (leads) whose enquiry date is between the two dates, with every enquiry field.
        @elseif ($report === 'lead-ageing')
          Lists the open leads whose enquiry date is between the two dates, with their age in days since the enquiry date and their ageing group (0 to 7, 8 to 15, 16 to 30, 31 to 60, over 60 days).
        @else
          Lists each lead with all its activity from lead to PO: @module('site_visit')site visits, @endmodule follow-ups, the current quotation and its status, and the PO / agreement.
        @endif
        Dates default to the current financial year.
      </span></div>
      <div style="display:flex;gap:8px;justify-content:flex-end"><button class="btn pri" type="submit"><svg class="ico"><use href="#i-download"/></svg>Download</button></div>
    </div>
  </form>
  <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>The report downloads on this page, without opening a new page. Reports are made on the server.</span></div>
</div>
@endsection
