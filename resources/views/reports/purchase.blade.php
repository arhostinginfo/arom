{{-- Purchase reports: options on the same page (A21). Show lists the rows here, paged on the server (P1); Download makes the CSV or PDF on the server (A20, S3). --}}
@extends('layouts.app', ['title' => $title, 'crumbs' => [['Reports', route('app.page', ['page' => 'reports'])], ['Purchase · '.$title, null]]])

@php($q = fn ($k, $d = null) => request()->query($k, $d))
@section('content')
<div class="repcard">
  <div class="pagehd"><h1>{{ $title }}</h1><span class="badge b-neu">Purchase</span></div>

  @if ($errors->any())
    <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check the options marked in red.</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
  @endif

  <form class="sectcard" method="GET" action="{{ route('reports.purchase.'.$report, array_filter(['in' => request('in')])) }}">
    @if (request('in'))<input type="hidden" name="in" value="{{ request('in') }}">@endif
    <div class="sh"><h2>Report options</h2><span class="sp"></span></div>
    <div class="sb">
      <div class="fgrid">
        <div class="field">
          <label for="from">From date<span class="req">*</span></label>
          <input class="inp num @error('from') err @enderror" id="from" name="from" value="{{ old('from', $from) }}" placeholder="dd/mm/yyyy" inputmode="numeric" required>
          <span class="hint">{{ $dateLabel }}@if (in_array($report, ['grn', 'credit-debit'], true)) (invoice date while pending match)@endif</span>
        </div>
        <div class="field">
          <label for="to">To date<span class="req">*</span></label>
          <input class="inp num @error('to') err @enderror" id="to" name="to" value="{{ old('to', $to) }}" placeholder="dd/mm/yyyy" inputmode="numeric" required>
        </div>
        @if ($report === 'stock-transfers')
          @foreach (['from_unit' => 'Location from', 'to_unit' => 'Location to'] as $k => $label)
            <div class="field">
              <label for="{{ $k }}">{{ $label }}</label>
              <select class="inp @error($k) err @enderror" id="{{ $k }}" name="{{ $k }}">
                <option value="">All units</option>
                @foreach ($allUnits as $u)<option value="{{ $u->id }}" @selected((string) old($k, $q($k)) === (string) $u->id)>{{ $u->name }}</option>@endforeach
              </select>
            </div>
          @endforeach
        @else
          <div class="field">
            <label for="unit">Unit</label>
            <select class="inp @error('unit') err @enderror" id="unit" name="unit">
              <option value="">All my units</option>
              @foreach ($units as $u)<option value="{{ $u->id }}" @selected((string) old('unit', $q('unit')) === (string) $u->id)>{{ $u->name }}</option>@endforeach
            </select>
          </div>
        @endif
        @if (in_array($report, ['po-summary', 'grn', 'credit-debit'], true))
          <div class="field">
            <label for="party">Supplier</label>
            <div class="picker" data-picker="field" data-url="{{ route('reports.purchase.suppliers') }}" data-placeholder="All suppliers · type a name">
              <select class="inp" id="party" name="party">
                <option value="">All suppliers</option>
                @foreach ($parties as $id => $name)<option value="{{ $id }}" @selected((string) old('party', $q('party')) === (string) $id)>{{ $name }}</option>@endforeach
              </select>
            </div>
          </div>
        @endif
        @if (in_array($report, ['stock-transfers', 'stock-ledger', 'grn'], true))
          <div class="field">
            <label for="item">Item @if ($report === 'stock-ledger')<span class="req">*</span>@endif</label>
            <div class="picker" data-picker="field" data-url="{{ route('reports.purchase.items') }}" data-placeholder="{{ $report === 'stock-ledger' ? 'Choose an item · type its number' : 'All items · type an item no' }}">
              <select class="inp @error('item') err @enderror" id="item" name="item">
                <option value="">{{ $report === 'stock-ledger' ? 'Choose an item' : 'All items' }}</option>
                @foreach ($items as $id => $label)<option value="{{ $id }}" @selected((string) old('item', $q('item')) === (string) $id)>{{ $label }}</option>@endforeach
              </select>
            </div>
          </div>
        @endif
        @if (in_array($report, ['po-summary', 'stock-transfers'], true))
          <div class="field">
            <label for="status">Status</label>
            <select class="inp @error('status') err @enderror" id="status" name="status">
              <option value="">All but Cancelled</option>
              @foreach ($statuses as $s)<option @selected(old('status', $q('status')) === $s)>{{ $s }}</option>@endforeach
            </select>
          </div>
        @elseif ($report === 'grn')
          <div class="field">
            <label for="match">Match status</label>
            <select class="inp" id="match" name="match">
              <option value="">All</option>
              @foreach ($matches as $s)<option @selected(old('match', $q('match')) === $s)>{{ $s }}</option>@endforeach
            </select>
          </div>
          <div class="field">
            <label for="quality">Quality approval</label>
            <select class="inp" id="quality" name="quality">
              <option value="">All</option>
              @foreach ($qualities as $s)<option @selected(old('quality', $q('quality')) === $s)>{{ $s }}</option>@endforeach
            </select>
          </div>
        @elseif ($report === 'credit-debit')
          <div class="field">
            <label for="type">Type</label>
            <select class="inp" id="type" name="type">
              <option value="">Credit and debit notes</option>
              @foreach ($types as $s)<option value="{{ $s }}" @selected(old('type', $q('type')) === $s)>{{ \App\Models\Grn::TITLES[$s] }}</option>@endforeach
            </select>
          </div>
        @endif
        <div class="field">
          {{-- Download format as a drop-down instead of the segmented radio buttons (02/11/2026). --}}
          <label for="format">Download format</label>
          <select class="inp" id="format" name="format">
            <option value="csv" @selected(old('format', $q('format', 'csv')) === 'csv')>Excel (CSV)</option>
            <option value="pdf" @selected(old('format', $q('format')) === 'pdf')>PDF</option>
          </select>
        </div>
      </div>
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>
        @if ($report === 'po-summary')
          Lists the purchase orders dated between the two dates: items, value before GST, GST, total and how much has been received. Import POs are in the supplier's currency, so totals are given per currency.
        @elseif ($report === 'stock-transfers')
          Lists each item of the stock transfers from or to your units dated between the two dates, with who approved them. Stock moves only when a transfer is approved.
        @elseif ($report === 'grn')
          Lists each item of the purchase GRNs (goods received against POs) between the two dates: the supplier invoice, the PO, the invoice, accepted and rejected quantities and the quality approval. Only the accepted quantity comes into stock, when quality is approved.
        @elseif ($report === 'credit-debit')
          Lists the supplier credit notes and debit notes entered on GRN entry between the two dates, with totals per type.
        @else
          Shows the opening stock of the item at the From date, every stock in and out (tax invoices, credit and debit notes, stock transfers in and out, GRNs) and the running and closing balance.
        @endif
        @if (in_array($report, ['po-summary', 'stock-transfers'], true)) Cancelled documents are left out unless chosen in Status. @elseif (in_array($report, ['grn', 'credit-debit'], true)) Cancelled GRNs are left out. @endif
        Dates default to the current financial year.
      </span></div>
      <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">
        <button class="btn sec" type="submit" name="show" value="1"><svg class="ico"><use href="#i-search"/></svg>Show</button>
        <button class="btn pri" type="submit" formaction="{{ route('reports.purchase.download', $report) }}"><svg class="ico"><use href="#i-download"/></svg>Download</button>
      </div>
    </div>
  </form>

  @if (! empty($prompt))
    <div class="alert info"><svg class="ico"><use href="#i-info"/></svg><span>Choose an item to see its stock ledger.</span></div>
  @endif

  @if ($result)
    @php($n = count($result['headers']))
    @if ($report === 'stock-ledger')
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span><b>{{ $result['item']?->pickerLabel() }}</b>@if ($result['item']?->uom) · {{ $result['item']->uom->code }}@endif</span></div>
    @endif
    <div class="tablecard">
      <table class="atable rtable" id="reportTable">
        <thead><tr>@foreach ($result['headers'] as $i => $h)<th @class(['r' => in_array($i, $result['numeric'], true)])>{{ $h }}</th>@endforeach</tr></thead>
        <tbody>
        @if ($report === 'stock-ledger' && $result['page']->onFirstPage())
          <tr class="sel"><td data-label="Date" class="num">{{ $result['fromLabel'] }}</td><td colspan="{{ $n - 2 }}"><b>Opening balance</b></td><td data-label="Balance" class="num r"><b>{{ $result['opening'] }}</b></td></tr>
        @endif
        @forelse ($result['rows'] as $row)
          <tr>
            @foreach ($row['cells'] as $i => $cell)
              <td data-label="{{ $result['headers'][$i] }}" @class(['num r' => in_array($i, $result['numeric'], true), 'wide' => $i === $row['linkCol']])>
                @if ($i === $row['linkCol'] && $row['link'])<a href="{{ $row['link'] }}"><b>{{ $cell }}</b></a>@else{{ $cell }}@endif
              </td>
            @endforeach
          </tr>
        @empty
          <tr><td colspan="{{ $n }}"><div class="empty">{{ $report === 'stock-ledger' ? 'No stock movements of this item between these dates.' : 'Nothing matches these options.' }}</div></td></tr>
        @endforelse
        @if ($report === 'stock-ledger' && ! $result['page']->hasMorePages())
          <tr class="sel"><td data-label="Date" class="num">{{ $result['toLabel'] }}</td><td colspan="{{ $n - 2 }}"><b>Closing balance</b></td><td data-label="Balance" class="num r"><b>{{ $result['closing'] }}</b></td></tr>
        @endif
        </tbody>
        @if (! empty($result['totals']))
          <tfoot>
            @foreach ($result['totals'] as $t)
              <tr class="sel">@foreach ($t as $i => $cell)<td @if ($cell !== '') data-label="{{ $i ? $result['headers'][$i] : 'Total' }}" @endif @class(['num r' => in_array($i, $result['numeric'], true)])><b>{{ $cell }}</b></td>@endforeach</tr>
            @endforeach
          </tfoot>
        @endif
      </table>
    </div>
    @include('partials.pager', ['p' => $result['page']])
  @endif
  <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>Show lists the rows on this page; Download makes the file on the server without opening a new page. PDF of this report is limited to {{ number_format($pdfCap) }} rows; use CSV for more.</span></div>
</div>
@endsection
