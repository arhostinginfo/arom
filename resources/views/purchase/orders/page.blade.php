{{--
  Purchase › Purchase order (PURCHASE tab, prototype screen "po"): entry bar, PO no and date, the approval bar, the short
  view (Party name) with "Show all headings" for delivery date, payment terms, currency, transporter, addresses and
  remarks (PURCHASE B11 / C11), the items with UOM and Tax from their masters, GST totals, Generate PDF.
  As on the Sales page, Delete (Draft / Rejected) and Cancel with a reason (Pending / Approved) stay on the entry page.
--}}
@php
  $u = auth()->user();
  $df = config('nursery.date_format');
  $num = fn ($x, $d = 3) => $x === null || $x === '' ? '' : rtrim(rtrim(number_format((float) $x, $d, '.', ''), '0'), '.');
  $isNew = ! $po->exists;
  $canSave = $isNew ? $u->can('purchase-order.add') : ($u->can('purchase-order.edit') && $po->isEditableBy($u));
  $ps = app(\App\Services\PageSetupService::class)->form('purchase-order');
  $v = fn ($f) => old($f, $po->{$f});
  $vd = fn ($f) => old($f, $po->{$f}?->format($df));
  $received = $po->exists && $po->lines->contains(fn ($l) => (float) $l->received_quantity > 0);
  $showReceived = $po->exists && in_array($po->status, [\App\Models\PurchaseOrder::APPROVED, \App\Models\PurchaseOrder::PARTLY_RECEIVED, \App\Models\PurchaseOrder::RECEIVED, \App\Models\PurchaseOrder::CLOSED], true);
  $options = $entries->map(fn ($e) => ['id' => $e->id, 'url' => route('purchase.orders.show', $e), 'label' => $e->po_no.' · '.($e->party?->name ?? ''), 'on' => $po->exists && $e->id === $po->id])->all();
  $buttons = array_values(array_filter([
    ['href' => route('purchase.orders.list'), 'icon' => 'list', 'label' => 'Purchase order list', 'nav' => true],
    $po->exists ? ['href' => route('purchase.orders.pdf', $po), 'icon' => 'file', 'label' => 'Generate PDF', 'kind' => 'pdf'] : null,
  ]));
  // Lines: after a failed save the rows as typed (posted as one JSON field), otherwise the saved ones.
  $typed = old('lines_json') !== null || old('lines') !== null;
  $oldRows = $typed ? (old('lines') ?: json_decode((string) old('lines_json', '[]'), true)) : null;
  $savedLines = $po->lines->keyBy('id');
  $lines = $typed
    ? collect(is_array($oldRows) ? array_values(array_filter($oldRows, 'is_array')) : [])->map(fn ($l) => (object) [
        'id' => $l['id'] ?? null, 'item_id' => $l['item_id'] ?? null, 'description' => $l['description'] ?? '', 'quantity' => $l['quantity'] ?? '',
        'uom_id' => $l['uom_id'] ?? '', 'rate' => $l['rate'] ?? '', 'discount_percent' => $l['discount_percent'] ?? '', 'tax_structure_id' => $l['tax_structure_id'] ?? '',
        'delivery_date' => $l['delivery_date'] ?? '', 'received' => ! empty($l['id']) ? (float) $savedLines->get($l['id'])?->received_quantity : 0.0, 'total' => null,
      ])->all()
    : $po->lines->map(fn ($l) => (object) [
        'id' => $l->id, 'item_id' => $l->item_id, 'description' => $l->description, 'quantity' => $num($l->quantity), 'uom_id' => $l->uom_id,
        'rate' => $num($l->rate, 4), 'discount_percent' => (float) $l->discount_percent ? $num($l->discount_percent, 2) : '', 'tax_structure_id' => $l->tax_structure_id,
        'delivery_date' => $l->delivery_date?->format($df), 'received' => (float) $l->received_quantity, 'total' => $l->taxable_value,
      ])->all();
  if (! $lines) { $lines = [(object) ['id' => null, 'item_id' => null, 'description' => '', 'quantity' => '', 'uom_id' => '', 'rate' => '', 'discount_percent' => '', 'tax_structure_id' => '', 'delivery_date' => '', 'received' => 0.0, 'total' => null]]; }
  $lineItems = \App\Models\Item::with('currency:id,code')->withTrashed()->whereIn('id', array_filter(array_column(array_map(fn ($l) => (array) $l, $lines), 'item_id')))->get()->keyBy('id');
  $itemAttrs = fn ($it) => $it ? collect(\App\Http\Controllers\Purchase\PurchaseOrderController::itemData($it))->map(fn ($x, $k) => 'data-'.$k.'="'.e($x).'"')->implode(' ') : '';
  $partyId = $v('party_id');
  $currencyId = $v('currency_id');
  $currencyCode = $currencies->firstWhere('id', (int) $currencyId)?->code ?? 'INR';
  $uomOpts = function ($val) use ($uoms) {
      $h = '<option value="">Item UOM</option>';
      foreach ($uoms as $o) { $h .= '<option value="'.$o->id.'"'.((string) $val === (string) $o->id ? ' selected' : '').($o->description ? ' title="'.e($o->description).'"' : '').'>'.e($o->code).'</option>'; }
      return $h;
  };
  $taxOpts = function ($val) use ($taxes) {
      $h = '<option value="">Item tax</option>';
      foreach ($taxes as $t) { $h .= '<option value="'.$t->id.'" data-c="'.(float) $t->cgst_rate.'" data-s="'.(float) $t->sgst_rate.'" data-i="'.(float) $t->igst_rate.'"'.((string) $val === (string) $t->id ? ' selected' : '').'>'.e($t->name).'</option>'; }
      return $h;
  };
  $itemOpts = function ($val, $label) use ($items, $itemAttrs) {
      $h = '<option value="">Select</option>';
      foreach ($items as $id => $it) { $h .= '<option value="'.$id.'" '.$itemAttrs($it).((string) $val === (string) $id ? ' selected' : '').'>'.e($it->pickerLabel()).'</option>'; }
      return $h;
  };
  $itemsUrl = route('purchase.items.lookup', array_filter(['party' => $partyId]));
  // "Show all headings" (PURCHASE B11): open when a heading has an error or is mandatory in Page setup.
  $headings = ['delivery_date', 'payment_terms', 'currency_id', 'transporter_id', 'billing_address', 'shipping_address', 'remarks'];
  $allOpen = $errors->hasAny($headings) || collect($headings)->contains(fn ($f) => $ps->show($f) && $ps->req($f));
  $cur = $currencyCode !== 'INR' ? $currencyCode.' ' : '₹';
  $isPending = $po->status === \App\Models\PurchaseOrder::PENDING;
  $canDelete = $po->exists && $u->can('purchase-order.delete') && $po->isEditableBy($u) && ! $received && in_array($po->status, [\App\Models\PurchaseOrder::DRAFT, \App\Models\PurchaseOrder::REJECTED], true);
  $canCancel = $po->exists && $u->can('purchase-order.delete') && ! $received && in_array($po->status, [\App\Models\PurchaseOrder::PENDING, \App\Models\PurchaseOrder::APPROVED], true);
@endphp
@extends('layouts.app', ['title' => $po->exists ? $po->po_no : 'Purchase order', 'crumbs' => [['Purchase', route('purchase.orders.index')], ['Purchase order', null]]])

@section('content')
<style>
  /* Item lines fit the screen (no sideways scroll): a table on wide screens, one card per item below 1180 px. */
  #poTable td.wide .inp + .inp{margin-top:6px}
  @media (max-width:1180px){
    .tablecard:has(> table.pocards){overflow:visible!important;border:0!important;background:none!important;box-shadow:none!important}
    table.pocards,table.pocards tbody,table.pocards tfoot{display:block;width:100%;min-width:0!important}
    table.pocards thead{display:none}
    table.pocards tbody tr{position:relative;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px 12px;padding:12px 14px;margin-bottom:10px;border:1px solid var(--border);border-radius:12px;background:var(--surface)}
    table.pocards td{display:block;padding:0!important;border:0!important;text-align:left!important;min-width:0;white-space:normal;height:auto!important;position:static!important}
    table.pocards td::before{content:attr(data-label);display:block;font-size:10.5px;letter-spacing:.06em;text-transform:uppercase;font-weight:600;color:var(--text-muted);margin-bottom:4px}
    table.pocards td.wide{grid-column:1/-1;padding-right:34px!important}
    table.pocards td.del{position:absolute!important;top:8px;right:8px;width:auto}
    table.pocards td.del::before{display:none}
    table.pocards .inp{width:100%;min-width:0!important}
  }
  @media (max-width:640px){table.pocards tbody tr{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @media (max-width:380px){table.pocards tbody tr{grid-template-columns:minmax(0,1fr)}}
</style>

<div class="pagehd">
  <h1>Purchase order</h1>
  <span class="sp"></span>
  <span class="cap muted">Unit: {{ $unit?->name ?? 'none' }}</span>
</div>
@unless ($company->exists && $company->gstin)
  @can('masters.edit')
    <div class="alert info"><svg class="ico"><use href="#i-info"/></svg><span>Enter your company's GSTIN in <a href="{{ route('admin.company.edit') }}">Admin › Masters › Company</a>. It decides CGST + SGST or IGST on purchase orders.</span></div>
  @endcan
@endunless

<div class="sectcard" id="poCard">
  <div class="sh">
    <h2>Purchase order</h2>
    @if ($po->exists)<span class="badge {{ $po->badgeClass() }}">{{ $po->status }}</span>@endif
    <div class="autono"><span>PO no <b>{{ $po->po_no ?? 'on save' }}</b></span><span>PO date <b>{{ $po->po_date?->format($df) ?? today()->format($df) }}</b></span></div>
    <span class="sp"></span>
    <button class="btn sec sm" type="button" id="poHeadings" aria-expanded="{{ $allOpen ? 'true' : 'false' }}" aria-controls="allHd">{{ $allOpen ? 'Hide headings' : 'Show all headings' }}</button>
  </div>
  <div class="sb">
    @include('crm.partials.entrybar', [
      'action' => route('purchase.orders.index'), 'lookupUrl' => route('purchase.orders.lookup'), 'q' => $q, 'options' => $options, 'current' => $po->exists,
      'newLabel' => 'New purchase order', 'placeholder' => 'PO no or supplier name',
      'addUrl' => $u->can('purchase-order.add') ? route('purchase.orders.create') : null, 'buttons' => $buttons,
    ])

    @if ($errors->any())
      <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>{{ $errors->hasAny(['delete', 'status', 'form']) ? 'This was not done.' : 'Check the fields marked in red. Nothing was saved.' }}</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
    @endif

    {{-- Approval bar (D3): the status and the next actions for it only. --}}
    <div class="apbar statusbar" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <span class="cap muted">Status</span>
      <span class="badge {{ $po->badgeClass() }}">{{ $po->status }}</span>
      @if ($isPending)<span class="cap muted">Waiting for an approver (Purchase order approval)</span>@endif
      @if ($po->approved_at && in_array($po->status, [\App\Models\PurchaseOrder::APPROVED, \App\Models\PurchaseOrder::PARTLY_RECEIVED, \App\Models\PurchaseOrder::RECEIVED, \App\Models\PurchaseOrder::CLOSED], true))
        <span class="cap muted">Approved{{ $po->approver ? ' by '.$po->approver->name : ' automatically' }} on {{ $po->approved_at->format($df) }}</span>
      @endif
      @if ($po->status_reason)<span class="cap" style="color:var(--error-700)">Reason: {{ $po->status_reason }}</span>@endif
      <span class="sp" style="flex:1"></span>
      @if ($isNew)
        <span class="cap muted">Save the entry, then send it for approval</span>
      @elseif (in_array($po->status, [\App\Models\PurchaseOrder::DRAFT, \App\Models\PurchaseOrder::REJECTED], true) && $u->can('purchase-order.edit'))
        <form method="POST" action="{{ route('purchase.orders.submit', $po) }}" data-saving @if ($ask = \App\Support\ApprovalNotice::message($rule, (float) $po->taxable_value, $po->currencyCode(), 'purchase order')) data-confirm-title="Send {{ $po->po_no }} for approval" data-confirm="{{ $ask }} Save any unsaved changes first." @endif>@csrf
          <button class="btn pri sm" type="submit"><svg class="ico"><use href="#i-check"/></svg>Send for approval</button>
        </form>
      @endif
      @if ($isPending)
        @can('purchase-order-approval.edit')
          <button class="btn sec sm" type="button" data-open="#poRejectLayer" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Reject</button>
          <form method="POST" action="{{ route('purchase.orders.approve', $po) }}" data-saving>@csrf<button class="btn pri sm" type="submit"><svg class="ico"><use href="#i-check"/></svg>Approve</button></form>
        @else
          <span class="cap muted">Log in as an approver to approve</span>
        @endcan
      @endif
    </div>
    @if ($po->exists && ! $canSave && $u->can('purchase-order.edit'))
      <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>{{ $po->po_no }} is {{ $po->status }}, so {{ in_array($po->status, [\App\Models\PurchaseOrder::CANCELLED, \App\Models\PurchaseOrder::RECEIVED, \App\Models\PurchaseOrder::CLOSED], true) ? 'it cannot be changed' : 'only an Admin can change it' }}.</span></div>
    @elseif ($po->exists && ! $u->can('purchase-order.edit'))
      <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>You can view this purchase order. Your role cannot change purchase orders.</span></div>
    @endif

    <form method="POST" action="{{ $isNew ? route('purchase.orders.store') : route('purchase.orders.update', $po) }}" novalidate id="poForm" data-dirty-guard data-saving data-lines-form="#poLines" data-supply="{{ $supply }}" data-currency="{{ $currencyCode }}">
      @csrf
      @unless ($isNew) @method('PUT') @endunless
      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0;display:grid;gap:14px">
      <div class="fgrid">
        <div class="field">
          <label for="party_id">Party name<span class="req">*</span></label>
          <div class="picker" data-picker="field" data-url="{{ route('purchase.parties.lookup') }}" data-placeholder="Type a supplier name or GST no">
            <select class="inp @error('party_id') err @enderror" id="party_id" name="party_id" aria-required="true" data-info-url="{{ route('purchase.parties.info', ['party' => '__ID__']) }}">
              <option value="">Select</option>
              @foreach ($suppliers as $id => $name)<option value="{{ $id }}" @selected((string) $partyId === (string) $id)>{{ $name }}</option>@endforeach
            </select>
          </div>
          <span class="hint txt">Suppliers (Supplier or Both) from the Party master.</span>
          @include('partials.ferr', ['name' => 'party_id'])
        </div>
        <div class="field">
          <label for="po_date">PO date<span class="req">*</span></label>
          <input class="inp num @error('po_date') err @enderror" id="po_date" name="po_date" value="{{ $vd('po_date') }}" placeholder="dd/mm/yyyy" inputmode="numeric" required>
          @include('partials.ferr', ['name' => 'po_date'])
        </div>
      </div>

      <div id="allHd" @unless ($allOpen) hidden @endunless>
        <div class="fgrid">
          @if ($ps->show('delivery_date'))
          <div class="field">
            <label for="delivery_date">Delivery date{{ $ps->star('delivery_date') }}</label>
            <input class="inp num @error('delivery_date') err @enderror" id="delivery_date" name="delivery_date" value="{{ $vd('delivery_date') }}" placeholder="dd/mm/yyyy" inputmode="numeric" @required($ps->req('delivery_date'))>
            <span class="hint txt">Used for items without their own delivery date.</span>
            @include('partials.ferr', ['name' => 'delivery_date'])
          </div>
          @endif
          @if ($ps->show('payment_terms'))
          <div class="field">
            <label for="payment_terms">Payment terms{{ $ps->star('payment_terms') }}</label>
            <input class="inp @error('payment_terms') err @enderror" id="payment_terms" name="payment_terms" value="{{ $v('payment_terms') }}" maxlength="255" @required($ps->req('payment_terms'))>
            <span class="hint txt">Filled in from the supplier's payment terms.</span>
            @include('partials.ferr', ['name' => 'payment_terms'])
          </div>
          @endif
          @if ($ps->show('currency_id'))
          <div class="field">
            <label for="currency_id">Currency{{ $ps->star('currency_id') }}</label>
            <select class="inp @error('currency_id') err @enderror" id="currency_id" name="currency_id" @required($ps->req('currency_id'))>
              <option value="" data-code="">Supplier's currency</option>
              @foreach ($currencies as $c)<option value="{{ $c->id }}" data-code="{{ $c->code }}" @selected((string) $currencyId === (string) $c->id)>{{ $c->code }}{{ $c->country ? ' · '.$c->country : '' }}</option>@endforeach
            </select>
            <span class="hint txt">The supplier's currency from the Party master; it follows the supplier.</span>
            @include('partials.ferr', ['name' => 'currency_id'])
          </div>
          @endif
          @if ($ps->show('transporter_id'))
          <div class="field">
            <label for="transporter_id">Transporter{{ $ps->star('transporter_id') }}</label>
            <select class="inp @error('transporter_id') err @enderror" id="transporter_id" name="transporter_id" @required($ps->req('transporter_id'))>
              <option value="">Select</option>
              @foreach ($transporters as $t)<option value="{{ $t->id }}" @selected((string) $v('transporter_id') === (string) $t->id)>{{ $t->name }}</option>@endforeach
            </select>
            @if ($transporters->isEmpty())<span class="hint txt">No transporters yet. Add them in Admin › Masters › Transporter master.</span>@endif
            @include('partials.ferr', ['name' => 'transporter_id'])
          </div>
          @endif
          @if ($ps->show('billing_address'))
          <div class="field">
            <label for="billing_address">Billing address{{ $ps->star('billing_address') }}</label>
            <textarea class="inp @error('billing_address') err @enderror" id="billing_address" name="billing_address" rows="2" maxlength="1000" style="height:auto;padding-top:8px" @required($ps->req('billing_address'))>{{ $v('billing_address') }}</textarea>
            <span class="hint txt">Your company address (Company master), where the supplier sends the bill.</span>
            @include('partials.ferr', ['name' => 'billing_address'])
          </div>
          @endif
          @if ($ps->show('shipping_address'))
          <div class="field">
            <label for="shipping_address">Shipping address{{ $ps->star('shipping_address') }}</label>
            <textarea class="inp @error('shipping_address') err @enderror" id="shipping_address" name="shipping_address" rows="2" maxlength="1000" style="height:auto;padding-top:8px" @required($ps->req('shipping_address'))>{{ $v('shipping_address') }}</textarea>
            <span class="hint txt">Where the goods are delivered.</span>
            @include('partials.ferr', ['name' => 'shipping_address'])
          </div>
          @endif
          @if ($ps->show('remarks'))
          <div class="field" style="grid-column:1/-1">
            <label for="remarks">Remarks{{ $ps->star('remarks') }}</label>
            <input class="inp @error('remarks') err @enderror" id="remarks" name="remarks" value="{{ $v('remarks') }}" maxlength="1000" @required($ps->req('remarks'))>
            @include('partials.ferr', ['name' => 'remarks'])
          </div>
          @endif
        </div>
      </div>
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>Short view by default; "Show all headings" shows delivery date, payment terms, currency, transporter, addresses and remarks.</span></div>

      <div class="lbl">Items<span class="req">*</span></div>
      <div class="tablecard">
        <table class="atable lines pocards" id="poTable">
          <thead><tr><th style="min-width:220px">Item no / Description</th><th class="r" style="width:96px">Quantity<span class="req">*</span></th><th style="width:92px">UOM</th><th class="r" style="width:104px">Rate</th><th class="r" style="width:76px">Disc %</th><th style="width:116px">Tax</th><th style="width:124px">Delivery date</th><th class="r" style="width:110px">Amount</th>@if ($showReceived)<th class="r" style="width:90px">Received</th>@endif<th><span class="sr">Remove</span></th></tr></thead>
          <tbody id="poLines" data-items-url="{{ $itemsUrl }}">
            @foreach ($lines as $i => $l)
              @php($it = $l->item_id ? $lineItems->get($l->item_id) : null)
              @php($locked = (float) $l->received > 0)
              <tr {!! $itemAttrs($it) !!}>
                <td data-label="Item" class="wide">
                  @if ($l->id)<input type="hidden" name="lines[{{ $i }}][id]" value="{{ $l->id }}">@endif
                  <div class="picker" data-picker="field" data-url="{{ $itemsUrl }}" data-placeholder="Type an item no or description">
                    <select class="inp @error("lines.$i.item_id") err @enderror" id="po_item_{{ $i }}" name="lines[{{ $i }}][item_id]" aria-label="Item {{ $i + 1 }}">
                      {!! $itemOpts($l->item_id, null) !!}
                      @if ($it && ! $items->has($it->id))<option value="{{ $it->id }}" {!! $itemAttrs($it) !!} selected>{{ $it->pickerLabel() }}</option>@endif
                    </select>
                  </div>
                  <input class="inp @error("lines.$i.description") err @enderror" name="lines[{{ $i }}][description]" value="{{ $l->description }}" maxlength="255" placeholder="Description (blank = item description)" aria-label="Description, item {{ $i + 1 }}">
                  @include('partials.ferr', ['name' => "lines.$i.item_id"])
                  @include('partials.ferr', ['name' => "lines.$i.description"])
                </td>
                <td data-label="Quantity"><input class="inp num @error("lines.$i.quantity") err @enderror" name="lines[{{ $i }}][quantity]" value="{{ $l->quantity }}" inputmode="decimal" data-calc aria-label="Quantity, item {{ $i + 1 }}">@include('partials.ferr', ['name' => "lines.$i.quantity"])</td>
                <td data-label="UOM"><select class="inp @error("lines.$i.uom_id") err @enderror" name="lines[{{ $i }}][uom_id]" aria-label="UOM, item {{ $i + 1 }}">{!! $uomOpts($l->uom_id) !!}</select>@include('partials.ferr', ['name' => "lines.$i.uom_id"])</td>
                <td data-label="Rate"><input class="inp num @error("lines.$i.rate") err @enderror" name="lines[{{ $i }}][rate]" value="{{ $l->rate }}" inputmode="decimal" data-calc placeholder="Item price" aria-label="Rate, item {{ $i + 1 }}" @if ($locked) readonly @endif>@include('partials.ferr', ['name' => "lines.$i.rate"])</td>
                <td data-label="Disc %"><input class="inp num @error("lines.$i.discount_percent") err @enderror" name="lines[{{ $i }}][discount_percent]" value="{{ $l->discount_percent }}" inputmode="decimal" data-calc aria-label="Discount %, item {{ $i + 1 }}">@include('partials.ferr', ['name' => "lines.$i.discount_percent"])</td>
                <td data-label="Tax"><select class="inp @error("lines.$i.tax_structure_id") err @enderror" name="lines[{{ $i }}][tax_structure_id]" data-calc aria-label="Tax, item {{ $i + 1 }}">{!! $taxOpts($l->tax_structure_id) !!}</select>@include('partials.ferr', ['name' => "lines.$i.tax_structure_id"])</td>
                <td data-label="Delivery date"><input class="inp num @error("lines.$i.delivery_date") err @enderror" name="lines[{{ $i }}][delivery_date]" value="{{ $l->delivery_date }}" placeholder="dd/mm/yyyy" inputmode="numeric" aria-label="Delivery date, item {{ $i + 1 }}">@include('partials.ferr', ['name' => "lines.$i.delivery_date"])</td>
                <td class="r num" data-label="Amount" data-amount>{{ $l->total !== null ? \App\Support\Money::indian($l->total, 2) : '' }}</td>
                @if ($showReceived)<td class="r num" data-label="Received">{{ $l->received ? \App\Support\Money::qty($l->received) : '' }}</td>@endif
                <td class="del">@if ($canSave && ! $locked)<button class="btn gh icon sm" type="button" data-del-line aria-label="Remove item {{ $i + 1 }}"><svg class="ico"><use href="#i-x"/></svg></button>@endif</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @include('partials.ferr', ['name' => 'lines'])
      @if ($canSave)<div><button class="btn add sm" type="button" data-add-line="#poLines"><svg class="ico"><use href="#i-plus"/></svg>Add item</button></div>@endif

      {{-- Totals while typing; the server works them out again on save. --}}
      <div class="card" id="poTotals" style="background:var(--surface-sunken);max-width:440px;width:100%;margin-left:auto" aria-live="polite">
        <div class="cb2" style="padding:12px 16px">
          <dl class="kv num" style="grid-template-columns:1fr auto">
            <dt>Taxable value</dt><dd style="text-align:right" data-t="taxable">{{ \App\Support\Money::indian($po->taxable_value ?? 0, 2) }}</dd>
            <dt data-row="intra">CGST</dt><dd style="text-align:right" data-row="intra" data-t="cgst">{{ \App\Support\Money::indian($po->cgst_amount ?? 0, 2) }}</dd>
            <dt data-row="intra">SGST</dt><dd style="text-align:right" data-row="intra" data-t="sgst">{{ \App\Support\Money::indian($po->sgst_amount ?? 0, 2) }}</dd>
            <dt data-row="inter">IGST</dt><dd style="text-align:right" data-row="inter" data-t="igst">{{ \App\Support\Money::indian($po->igst_amount ?? 0, 2) }}</dd>
            <dt data-row="import">GST</dt><dd style="text-align:right" data-row="import">Nil on the PO (import)</dd>
            <dt style="font-weight:600;color:var(--text-primary)">Total</dt><dd style="text-align:right;font-size:16px;font-weight:600"><span data-t="cur">{{ $cur }}</span><span data-t="grand">{{ \App\Support\Money::indian($po->grand_total ?? 0, 2) }}</span></dd>
          </dl>
          <div class="cap muted" data-t="note">{{ $po->exists ? 'As saved.' : 'Choose the supplier to see GST.' }} Worked out again on save.</div>
        </div>
      </div>
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>{{ $ps->mandatory() ?: 'Mandatory: Party name and PO date.' }} Items are the supplier's purchase items (Purchase or Both) from the Item master; UOM, Tax and Rate left blank are the item's (the rate only when the item is priced in the PO's currency). Amount = Quantity × Rate less Disc %. GST: CGST + SGST from a supplier in your state, IGST from another state (by GSTIN), none on the PO for a supplier in another currency. Approval as set in Admin › Approval: above {{ $rule->approve_above_amount !== null ? '₹'.\App\Support\Money::indian($rule->approve_above_amount, 2) : 'any amount' }} (taxable value) it goes to an approver.@if ($received) A received item stays on the PO with its item and rate, and cannot go below what was received.@endif</span></div>
      </fieldset>
      @include('crm.partials.audit', ['m' => $po])
      <div class="formfoot">
        @if ($canDelete)
          <button class="btn sec" type="submit" form="poDelForm" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Delete purchase order</button>
        @endif
        @if ($canCancel)
          <button class="btn sec" type="button" data-open="#poCancelLayer" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Cancel purchase order</button>
        @endif
        <span class="sp"></span>
        @if ($po->exists)@include('partials.iobtn', ['kind' => 'pdf', 'label' => 'Generate PDF', 'href' => route('purchase.orders.pdf', $po)])@endif
        @if ($canSave)<button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>{{ $isNew ? 'Save purchase order' : 'Save changes' }}</button>@endif
      </div>
    </form>

    @if ($canDelete)
      <form id="poDelForm" method="POST" action="{{ route('purchase.orders.destroy', $po) }}" data-confirm="Delete purchase order {{ $po->po_no }}? Its number is not used again.">@csrf @method('DELETE')</form>
    @endif

    @if ($approvals->isNotEmpty())
      <div class="sectcard">
        <div class="sh"><h2>Approval history</h2></div>
        <div class="sb">
          @foreach ($approvals as $a)
            <div class="timeline"><div class="ev"><span></span><div><b>{{ $a->action }}</b> · value {{ \App\Support\Money::indian($a->amount, 2) }}@if ($a->remarks) · {{ $a->remarks }}@endif<div class="cap muted">{{ $a->actor?->name ?? 'System' }} · {{ $a->acted_at->format(config('nursery.datetime_format')) }}</div></div></div></div>
          @endforeach
        </div>
      </div>
    @endif
    @include('crm.partials.history', ['history' => $history])
  </div>
</div>

<template id="poLineTpl">
  <tr>
    <td data-label="Item" class="wide">
      <div class="picker" data-picker="field" data-url="__URL__" data-placeholder="Type an item no or description">
        <select class="inp" id="po_item___i__" name="lines[__i__][item_id]" aria-label="Item __n__">{!! $itemOpts(null, null) !!}</select>
      </div>
      <input class="inp" name="lines[__i__][description]" maxlength="255" placeholder="Description (blank = item description)" aria-label="Description, item __n__">
    </td>
    <td data-label="Quantity"><input class="inp num" name="lines[__i__][quantity]" inputmode="decimal" data-calc aria-label="Quantity, item __n__"></td>
    <td data-label="UOM"><select class="inp" name="lines[__i__][uom_id]" aria-label="UOM, item __n__">{!! $uomOpts('') !!}</select></td>
    <td data-label="Rate"><input class="inp num" name="lines[__i__][rate]" inputmode="decimal" data-calc placeholder="Item price" aria-label="Rate, item __n__"></td>
    <td data-label="Disc %"><input class="inp num" name="lines[__i__][discount_percent]" inputmode="decimal" data-calc aria-label="Discount %, item __n__"></td>
    <td data-label="Tax"><select class="inp" name="lines[__i__][tax_structure_id]" data-calc aria-label="Tax, item __n__">{!! $taxOpts('') !!}</select></td>
    <td data-label="Delivery date"><input class="inp num" name="lines[__i__][delivery_date]" placeholder="dd/mm/yyyy" inputmode="numeric" aria-label="Delivery date, item __n__"></td>
    <td class="r num" data-label="Amount" data-amount></td>
    @if ($showReceived)<td class="r num" data-label="Received"></td>@endif
    <td class="del"><button class="btn gh icon sm" type="button" data-del-line aria-label="Remove item __n__"><svg class="ico"><use href="#i-x"/></svg></button></td>
  </tr>
</template>

@if ($po->exists && $isPending && $u->can('purchase-order-approval.edit'))
  <div class="overlay" id="poRejectLayer" hidden @if ($errors->has('remarks') && old('_action') === 'reject') data-autoopen @endif>
    <div class="scrim" data-x></div>
    <form class="modal" role="dialog" aria-modal="true" aria-labelledby="poRejT" method="POST" action="{{ route('purchase.orders.reject', $po) }}" data-saving>
      @csrf
      <input type="hidden" name="_action" value="reject">
      <div class="mh"><h3 id="poRejT">Reject {{ $po->po_no }}</h3></div>
      <div class="mb">
        <div class="field">
          <label for="po_reject_remarks">Reason<span class="req">*</span></label>
          <textarea class="inp @error('remarks') err @enderror" id="po_reject_remarks" name="remarks" rows="3" maxlength="500" style="height:auto;padding-top:8px" required></textarea>
          @include('partials.ferr', ['name' => 'remarks'])
        </div>
      </div>
      <div class="mf" style="display:flex;gap:8px;justify-content:flex-end;padding:12px 20px 18px">
        <button class="btn gh" type="button" data-x>Back</button>
        <button class="btn pri" type="submit" style="background:var(--error-600)">Reject</button>
      </div>
    </form>
  </div>
@endif

@if ($canCancel)
  <div class="overlay" id="poCancelLayer" hidden @if ($errors->has('remarks') && old('_action') === 'cancel') data-autoopen @endif>
    <div class="scrim" data-x></div>
    <form class="modal" role="dialog" aria-modal="true" aria-labelledby="poCanT" method="POST" action="{{ route('purchase.orders.cancel', $po) }}" data-saving>
      @csrf
      <input type="hidden" name="_action" value="cancel">
      <div class="mh"><h3 id="poCanT">Cancel {{ $po->po_no }}</h3></div>
      <div class="mb">
        <div class="field">
          <label for="po_cancel_remarks">Reason<span class="req">*</span></label>
          <textarea class="inp @error('remarks') err @enderror" id="po_cancel_remarks" name="remarks" rows="3" maxlength="500" style="height:auto;padding-top:8px" required></textarea>
          @include('partials.ferr', ['name' => 'remarks'])
        </div>
        <div class="small muted">The PO is kept as Cancelled with its number and the reason; no goods can be received against it.</div>
      </div>
      <div class="mf" style="display:flex;gap:8px;justify-content:flex-end;padding:12px 20px 18px">
        <button class="btn gh" type="button" data-x>Keep purchase order</button>
        <button class="btn pri" type="submit" style="background:var(--error-600)">Cancel purchase order</button>
      </div>
    </form>
  </div>
@endif
@endsection

@push('scripts')
<script src="{{ asset('js/crm.js') }}?v={{ filemtime(public_path('js/crm.js')) }}"></script>
<script src="{{ asset('js/purchase.js') }}?v={{ filemtime(public_path('js/purchase.js')) }}"></script>
@endpush
