@php
  // CRM › 6 PO / Agreement received. Separate PO menu removed (02/11/2026): this page is CRM's only again (it was
  // po/form.blade.php from 01/11/2026), with the Quotation picker and no Customer field or link block.
  $isNew = ! $po->exists;
  $u = auth()->user();
  $canSave = $isNew ? $u->can('customer-po.add') : $u->can('customer-po.edit');
  $df = config('nursery.date_format');
  $options = $entries->map(fn ($e) => ['id' => $e->id, 'url' => route('crm.customer-pos.show', ['customer_po' => $e, 'q' => $q ?: null]), 'label' => $e->po_no.' · '.$e->clientName(), 'on' => $po->exists && $e->id === $po->id])->all();
  $v = fn ($f) => old($f, $po->{$f});
  $d = fn ($f) => old($f, $po->{$f}?->format($df));
  $n = fn ($f) => old($f, $po->{$f} !== null ? rtrim(rtrim((string) $po->{$f}, '0'), '.') : '');
  $num = fn ($x, $dp = 3) => $x === null || $x === '' ? '' : rtrim(rtrim(number_format((float) $x, $dp, '.', ''), '0'), '.');
  // Admin > Page setup: which fields show and which are mandatory.
  $ps = app(\App\Services\PageSetupService::class)->form('customer-po');
  // Items from the Item master (Part master, 29/10/2026) unless hidden in Page setup.
  $crmLines = $ps->show('crm_lines');
  $typed = old('lines_json') !== null || old('lines') !== null;
  $oldRows = $typed ? (old('lines') ?: json_decode((string) old('lines_json', '[]'), true)) : null;
  $lines = $typed
    // Only plain values are shown again: a tampered row (an array in a field) must not break the page (tester review, 29/10/2026).
    ? collect(is_array($oldRows) ? array_values(array_filter($oldRows, 'is_array')) : [])->map(fn ($l) => array_map(fn ($x) => is_scalar($x) ? $x : null, $l))->map(fn ($l) => (object) [
        'id' => $l['id'] ?? null, 'item_id' => $l['item_id'] ?? null, 'description' => $l['description'] ?? '', 'quantity' => $l['quantity'] ?? '',
        'uom_id' => $l['uom_id'] ?? '', 'rate' => $l['rate'] ?? '', 'amount' => null,
      ])->all()
    : $po->lines->map(fn ($l) => (object) [
        'id' => $l->id, 'item_id' => $l->item_id, 'description' => $l->description, 'quantity' => $num($l->quantity), 'uom_id' => $l->uom_id,
        'rate' => $num($l->rate, 4), 'amount' => $l->amount,
      ])->all();
  if (! $lines) { $lines = [(object) ['id' => null, 'item_id' => null, 'description' => '', 'quantity' => '', 'uom_id' => '', 'rate' => '', 'amount' => null]]; }
  $lineItems = \App\Models\Item::withTrashed()->whereIn('id', array_filter(array_column(array_map(fn ($l) => (array) $l, $lines), 'item_id')))->get()->keyBy('id');
  $itemAttrs = fn ($it) => $it ? collect(\App\Http\Controllers\Crm\CustomerPoController::itemData($it))->map(fn ($x, $k) => 'data-'.$k.'="'.e($x).'"')->implode(' ') : '';
  $itemOpts = function ($val) use ($itemOptions, $itemAttrs) {
      $h = '<option value="">Select</option>';
      foreach ($itemOptions as $id => $it) { $h .= '<option value="'.$id.'" '.$itemAttrs($it).((string) $val === (string) $id ? ' selected' : '').'>'.e($it->pickerLabel()).'</option>'; }
      return $h;
  };
  $uomOpts = function ($val) use ($uoms) {
      $h = '<option value="">Item UOM</option>';
      foreach ($uoms as $o) { $h .= '<option value="'.$o->id.'"'.((string) $val === (string) $o->id ? ' selected' : '').($o->description ? ' title="'.e($o->description).'"' : '').'>'.e($o->code).'</option>'; }
      return $h;
  };
  $hasLines = collect($lines)->contains(fn ($l) => ! empty($l->item_id));
  $lineErrs = $errors->has('lines') || collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'lines.'));
  // Create Sales Order (02/11/2026): for users with Sales order · Add; one active order per PO, then "Open sales order".
  $poSo = $po->exists ? $po->salesOrder : null;
  $canSeeSo = $u->canAny(['sales-order.view', 'sales-order-approval.view']);
  $canMakeSo = $po->exists && ! $poSo && $u->can('sales-order.add');
  $noSoWhy = $canMakeSo ? \App\Services\SalesOrderService::whyNoOrderFrom($po) : null;
@endphp
@extends('layouts.app', ['title' => $isNew ? 'PO / Agreement' : 'PO '.$po->po_no, 'crumbs' => [['CRM', route('crm.enquiries.index')], ['PO / Agreement', null]]])

@section('content')
<style>
  /* Item lines fit the screen (no sideways scroll): a table on wide screens, one card per item below 900 px. */
  #cpoTable td.wide .inp + .inp{margin-top:6px}
  @media (max-width:900px){
    .tablecard:has(> table.cpocards){overflow:visible!important;border:0!important;background:none!important;box-shadow:none!important}
    table.cpocards,table.cpocards tbody{display:block;width:100%;min-width:0!important}
    table.cpocards thead{display:none}
    table.cpocards tbody tr{position:relative;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px 12px;padding:12px 14px;margin-bottom:10px;border:1px solid var(--border);border-radius:12px;background:var(--surface)}
    table.cpocards td{display:block;padding:0!important;border:0!important;text-align:left!important;min-width:0;white-space:normal;height:auto!important;position:static!important}
    table.cpocards td::before{content:attr(data-label);display:block;font-size:10.5px;letter-spacing:.06em;text-transform:uppercase;font-weight:600;color:var(--text-muted);margin-bottom:4px}
    table.cpocards td.wide{grid-column:1/-1;padding-right:34px!important}
    table.cpocards td.del{position:absolute!important;top:8px;right:8px;width:auto}
    table.cpocards td.del::before{display:none}
    table.cpocards .inp{width:100%;min-width:0!important}
  }
  @media (max-width:520px){table.cpocards tbody tr{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>

<div class="pagehd">
  <h1>PO / Agreement received</h1>
  <span class="sp"></span>
  <a class="btn gh sm" href="{{ route('crm.customer-pos.list') }}"><svg class="ico"><use href="#i-list"/></svg>PO list</a>
  {{-- Create Sales Order (02/11/2026): a Draft order in Sales from this PO; once made, it opens from here. --}}
  @if ($poSo && $canSeeSo)
    <a class="btn sec sm" href="{{ route('sales.orders.show', $poSo) }}"><svg class="ico"><use href="#i-file"/></svg>Open sales order</a>
  @elseif ($canMakeSo)
    <form method="POST" action="{{ route('crm.customer-pos.sales-order', $po) }}" data-saving style="display:inline">@csrf
      <button class="btn pri sm" type="submit" id="poMakeSo" @disabled($noSoWhy) @if ($noSoWhy) title="{{ $noSoWhy }}" aria-describedby="poSoWhy" @endif><svg class="ico"><use href="#i-file"/></svg>Create Sales Order</button>
    </form>
  @endif
</div>
@if ($errors->has('sales_order'))
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>The sales order was not made.</b><ul>@foreach ($errors->get('sales_order') as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif
@if ($poSo)
  <div class="alert info" id="poSoLink"><svg class="ico"><use href="#i-link"/></svg><span>Sales order @if ($canSeeSo)<a href="{{ route('sales.orders.show', $poSo) }}"><b>{{ $poSo->so_no }}</b></a>@else<b>{{ $poSo->so_no }}</b>@endif · {{ $poSo->status }}, made from this PO.</span></div>
@elseif ($noSoWhy)
  <p class="cap muted" id="poSoWhy" style="margin:-6px 0 12px;text-align:right">To create a sales order: {{ $noSoWhy }}</p>
@endif

@include('crm.partials.entrybar', [
  'action' => route('crm.customer-pos.index'), 'lookupUrl' => route('crm.customer-pos.lookup'), 'q' => $q, 'options' => $options, 'current' => $po->exists,
  'newLabel' => 'New PO / agreement', 'placeholder' => 'PO no, enquiry no or customer name',
  'addUrl' => $u->can('customer-po.add') ? route('crm.customer-pos.create') : null, 'buttons' => [],
])

@if ($errors->any() && ! $errors->hasAny(['delete', 'sales_order']))
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check the fields marked in red.</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif
@if (! $canSave && ($po->exists || ! $u->can('customer-po.add')))
  <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>{{ $po->exists ? 'You can view this PO. Your role cannot change POs.' : 'There are no POs yet, and your role cannot add them.' }}</span></div>
@endif

{{-- Fri 23/10/2026: a saved PO shows its documents first, clearly labelled, with Upload for users who can edit POs;
     users who can only view still see and open the documents. No Delete on CRM pages (Thu 22/10/2026). --}}
@if ($po->exists)
  @include('crm.partials.documents', ['type' => 'customer_po', 'record' => $po, 'docs' => $docs, 'canEdit' => $u->can('customer-po.edit'),
    'title' => 'PO / Agreement documents', 'default' => 'Customer PO', 'deletable' => false, 'uploadLabel' => 'Upload PO / Agreement document',
    'hint' => 'Choose Customer PO or Agreement as the document type, then the file. Upload saves it straight away.'])
@endif

<form method="POST" action="{{ $isNew ? route('crm.customer-pos.store') : route('crm.customer-pos.update', $po) }}" enctype="multipart/form-data" novalidate id="poForm" data-dirty-guard data-saving data-crm-lines="{{ $crmLines ? '1' : '0' }}" data-crm-lines-req="{{ $ps->req('crm_lines') ? '1' : '0' }}">
  @csrf
  @unless ($isNew) @method('PUT') @endunless
  <div class="sectcard">
    <div class="sh"><h2>PO / Agreement details</h2>
      <span class="sp"></span>
      @if ($po->enquiry && $u->can('enquiry.view'))<a class="btn gh sm" href="{{ route('crm.enquiries.show', $po->enquiry_id) }}"><svg class="ico"><use href="#i-link"/></svg>{{ $po->enquiry->enquiry_no }}</a>@endif
      @if ($po->quotation && $u->can('quotation.view'))<a class="btn gh sm" href="{{ route('crm.quotations.show', $po->quotation_id) }}"><svg class="ico"><use href="#i-link"/></svg>{{ $po->quotation->quotation_no }}</a>@endif
    </div>
    <div class="sb">
      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0;display:grid;gap:14px">
      <div class="fgrid">
        {{-- As before 29/10/2026: the quotation is mandatory and gives the client and enquiry; only a quotation sent to the
             customer (current revision, not Rejected / Expired) is offered. --}}
        <div class="field" style="grid-column:1/-1">
          <label for="quotation_id">Quotation<span class="req">*</span></label>
          <div class="picker" data-picker="field" data-url="{{ route('crm.quotations.lookup') }}" data-placeholder="Type a quotation no or client">
            <select class="inp @error('quotation_id') err @enderror" id="quotation_id" name="quotation_id" aria-required="true" data-items-url="{{ route('crm.customer-pos.items', ['quotation' => '__ID__']) }}">
              <option value="">Select</option>
              @foreach ($quotationOptions as $id => $label)<option value="{{ $id }}" @selected((string) $v('quotation_id') === (string) $id)>{{ $label }}</option>@endforeach
            </select>
          </div>
          <span class="hint txt">The client and enquiry come from the quotation. A PO is entered against the current revision of a quotation sent to the customer.@if ($po->exists) Client: <b>{{ $po->clientName() }}</b>.@endif</span>
          @include('partials.ferr', ['name' => 'quotation_id'])
        </div>
        <div class="field">
          <label for="po_no">PO / Agreement no<span class="req">*</span></label>
          <input class="inp @error('po_no') err @enderror" id="po_no" name="po_no" value="{{ $v('po_no') }}" maxlength="60" required>
          @include('partials.ferr', ['name' => 'po_no'])
        </div>
        <div class="field">
          <label for="po_date">PO date<span class="req">*</span></label>
          <input class="inp num @error('po_date') err @enderror" id="po_date" name="po_date" value="{{ $d('po_date') }}" placeholder="dd/mm/yyyy" inputmode="numeric" required>
          @include('partials.ferr', ['name' => 'po_date'])
        </div>
        @if ($ps->show('site'))
        <div class="field">
          <label for="site">Site{{ $ps->star('site') }}</label>
          <input class="inp @error('site') err @enderror" id="site" @required($ps->req('site')) name="site" value="{{ $v('site') }}" maxlength="255">
          @include('partials.ferr', ['name' => 'site'])
        </div>
        @endif
        <div class="field">
          <label for="po_value">PO value (₹)<span class="req" id="poValueReq" @if ($hasLines && $crmLines) hidden @endif>*</span></label>
          <input class="inp num @error('po_value') err @enderror" id="po_value" name="po_value" value="{{ $n('po_value') }}" inputmode="decimal" @if ($hasLines && $crmLines) readonly tabindex="-1" @endif>
          <span class="hint txt" id="poValueHint">{{ $crmLines ? ($hasLines ? 'Sum of the items, worked out again on save.' : 'Enter the value, or add items: with items it is their sum.') : '' }}</span>
          @include('partials.ferr', ['name' => 'po_value'])
        </div>
        @if ($ps->show('po_quantity'))
        <div class="field">
          <label for="po_quantity">PO quantity{{ $ps->star('po_quantity') }}</label>
          <input class="inp num @error('po_quantity') err @enderror" id="po_quantity" @required($ps->req('po_quantity')) name="po_quantity" value="{{ $n('po_quantity') }}" inputmode="decimal">
          @include('partials.ferr', ['name' => 'po_quantity'])
        </div>
        @endif
        @if ($ps->show('validity'))
        <div class="field">
          <label for="valid_from">Validity start date{{ $ps->star('validity') }}</label>
          <input class="inp num @error('valid_from') err @enderror" id="valid_from" @required($ps->req('validity')) name="valid_from" value="{{ $d('valid_from') }}" placeholder="dd/mm/yyyy" inputmode="numeric">
          @include('partials.ferr', ['name' => 'valid_from'])
        </div>
        @endif
        @if ($ps->show('validity'))
        <div class="field">
          <label for="valid_to">Validity end date{{ $ps->star('validity') }}</label>
          <input class="inp num @error('valid_to') err @enderror" id="valid_to" @required($ps->req('validity')) name="valid_to" value="{{ $d('valid_to') }}" placeholder="dd/mm/yyyy" inputmode="numeric">
          @include('partials.ferr', ['name' => 'valid_to'])
        </div>
        @endif
        @if ($ps->show('payment_terms'))
        <div class="field">
          <label for="payment_terms">Payment terms{{ $ps->star('payment_terms') }}</label>
          <input class="inp @error('payment_terms') err @enderror" id="payment_terms" @required($ps->req('payment_terms')) name="payment_terms" value="{{ $v('payment_terms') }}" maxlength="255">
          @include('partials.ferr', ['name' => 'payment_terms'])
        </div>
        @endif
        @if ($ps->show('tax_terms'))
        <div class="field">
          <label for="tax_terms">GST / tax{{ $ps->star('tax_terms') }}</label>
          <input class="inp @error('tax_terms') err @enderror" id="tax_terms" @required($ps->req('tax_terms')) name="tax_terms" value="{{ $v('tax_terms') }}" maxlength="150">
          @include('partials.ferr', ['name' => 'tax_terms'])
        </div>
        @endif
        @if ($ps->show('contact_name'))
        <div class="field">
          <label for="contact_name">Customer contact{{ $ps->star('contact_name') }}</label>
          <input class="inp @error('contact_name') err @enderror" id="contact_name" @required($ps->req('contact_name')) name="contact_name" value="{{ $v('contact_name') }}" maxlength="150">
          @include('partials.ferr', ['name' => 'contact_name'])
          @if (($ph = $po?->enquiry?->clientPhone()))<span class="hint txt">Phone {{ $ph }} (from the Prospect)</span>@endif

        </div>
        @endif
        @if ($ps->show('received_by'))
        <div class="field">
          <label for="received_by">Received by{{ $ps->star('received_by') }}</label>
          <div class="picker" data-picker="field" data-url="{{ route('lookup.employees') }}" data-placeholder="Type an employee name">
            <select class="inp @error('received_by') err @enderror" id="received_by" @if ($ps->req('received_by')) aria-required="true" @endif name="received_by">
              <option value="">Select</option>
              @foreach ($employees as $id => $name)<option value="{{ $id }}" @selected((string) $v('received_by') === (string) $id)>{{ $name }}</option>@endforeach
            </select>
          </div>
          @include('partials.ferr', ['name' => 'received_by'])
        </div>
        @endif
        <div class="field">
          <label for="received_date">Received date<span class="req">*</span></label>
          <input class="inp num @error('received_date') err @enderror" id="received_date" name="received_date" value="{{ $d('received_date') }}" placeholder="dd/mm/yyyy" inputmode="numeric" required>
          @include('partials.ferr', ['name' => 'received_date'])
        </div>
        @if ($ps->show('remarks'))
        <div class="field" style="grid-column:1/-1">
          <label for="remarks">Remarks{{ $ps->star('remarks') }}</label>
          <textarea class="inp @error('remarks') err @enderror" id="remarks" @required($ps->req('remarks')) name="remarks" rows="2" maxlength="2000" style="height:auto;padding-top:8px">{{ $v('remarks') }}</textarea>
          @include('partials.ferr', ['name' => 'remarks'])
        </div>
        @endif
      </div>

      {{-- 29/10/2026: items from the Item master (Part master), as set in Admin > Page setup ("Items (Part master)"); with
           items the PO value is their sum. --}}
      <div id="poItems" @unless ($crmLines) hidden @endunless>
        <div class="lbl">Items (Part master)<span class="req" id="poItemsReq" @unless ($ps->req('crm_lines')) hidden @endunless>*</span></div>
        <div class="tablecard">
          <table class="atable lines cpocards" id="cpoTable">
            <thead><tr><th style="min-width:240px">Part no / Description</th><th class="r" style="width:110px">Quantity<span class="req">*</span></th><th style="width:100px">UOM</th><th class="r" style="width:120px">Rate (₹)</th><th class="r" style="width:120px">Amount (₹)</th><th><span class="sr">Remove</span></th></tr></thead>
            <tbody id="poLines" data-items-url="{{ $itemsUrl }}">
              @foreach ($lines as $i => $l)
                @php($it = $l->item_id ? $lineItems->get($l->item_id) : null)
                <tr {!! $itemAttrs($it) !!}>
                  <td data-label="Part" class="wide">
                    @if ($l->id)<input type="hidden" name="lines[{{ $i }}][id]" value="{{ $l->id }}">@endif
                    <div class="picker" data-picker="field" data-url="{{ $itemsUrl }}" data-placeholder="Type a part no or description">
                      <select class="inp @error("lines.$i.item_id") err @enderror" id="cpo_item_{{ $i }}" name="lines[{{ $i }}][item_id]" aria-label="Part, item {{ $i + 1 }}">
                        {!! $itemOpts($l->item_id) !!}
                        @if ($it && ! $itemOptions->has($it->id))<option value="{{ $it->id }}" {!! $itemAttrs($it) !!} selected>{{ $it->pickerLabel() }}</option>@endif
                      </select>
                    </div>
                    <input class="inp @error("lines.$i.description") err @enderror" name="lines[{{ $i }}][description]" value="{{ $l->description }}" maxlength="255" placeholder="Description (blank = item description)" aria-label="Description, item {{ $i + 1 }}">
                    @include('partials.ferr', ['name' => "lines.$i.item_id"])
                    @include('partials.ferr', ['name' => "lines.$i.description"])
                  </td>
                  <td data-label="Quantity"><input class="inp num @error("lines.$i.quantity") err @enderror" name="lines[{{ $i }}][quantity]" value="{{ $l->quantity }}" inputmode="decimal" data-calc aria-label="Quantity, item {{ $i + 1 }}">@include('partials.ferr', ['name' => "lines.$i.quantity"])</td>
                  <td data-label="UOM"><select class="inp @error("lines.$i.uom_id") err @enderror" name="lines[{{ $i }}][uom_id]" aria-label="UOM, item {{ $i + 1 }}">{!! $uomOpts($l->uom_id) !!}</select>@include('partials.ferr', ['name' => "lines.$i.uom_id"])</td>
                  <td data-label="Rate"><input class="inp num @error("lines.$i.rate") err @enderror" name="lines[{{ $i }}][rate]" value="{{ $l->rate }}" inputmode="decimal" data-calc placeholder="Item price" aria-label="Rate, item {{ $i + 1 }}">@include('partials.ferr', ['name' => "lines.$i.rate"])</td>
                  <td class="r num" data-label="Amount" data-amount>{{ $l->amount !== null ? \App\Support\Money::indian($l->amount, 2) : '' }}</td>
                  <td class="del">@if ($canSave)<button class="btn gh icon sm" type="button" data-del-line aria-label="Remove item {{ $i + 1 }}"><svg class="ico"><use href="#i-x"/></svg></button>@endif</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @include('partials.ferr', ['name' => 'lines'])
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:8px">
          @if ($canSave)<button class="btn add sm" type="button" data-add-line="#poLines"><svg class="ico"><use href="#i-plus"/></svg>Add item</button>@endif
          <span class="sp" style="flex:1"></span>
          <span class="cap muted" aria-live="polite">Items total <b class="num" id="poLinesTotal">{{ \App\Support\Money::indian($po->lines->sum('amount'), 2) }}</b></span>
        </div>
      </div>

      {{-- Fri 23/10/2026: a new PO takes its PO / agreement documents here and saves them with the PO (no second step).
           Same types, size and checks as the Upload box of a saved PO (DocumentRequest); the browser cannot keep a chosen
           file after an error, so they are chosen again then. --}}
      @if ($isNew && $canSave)
        @php($docCats = \App\Services\DocumentService::categories('customer_po'))
        @php($docMaxKb = \App\Http\Requests\Crm\DocumentRequest::maxKb('customer_po'))
        @php($docErrs = collect($errors->get('doc_files.*'))->flatten()->merge($errors->get('doc_files'))->unique())
        <div class="podocs" id="poDocs">
          <h3><svg class="ico" aria-hidden="true"><use href="#i-upload"/></svg>Upload PO / Agreement document</h3>
          <div class="fgrid">
            <div class="field">
              <label for="doc_category">Document type</label>
              <select class="inp @error('doc_category') err @enderror" id="doc_category" name="doc_category">
                @foreach ($docCats as $c)<option @selected(old('doc_category', 'Customer PO') === $c)>{{ $c }}</option>@endforeach
              </select>
              @include('partials.ferr', ['name' => 'doc_category'])
            </div>
            <div class="field">
              <label for="doc_files">Files</label>
              <input class="inp {{ $docErrs->isNotEmpty() ? 'err' : '' }}" id="doc_files" type="file" name="doc_files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt,.eml,.msg" data-max-kb="{{ $docMaxKb }}" style="padding-top:6px" @if ($docErrs->isNotEmpty()) aria-invalid="true" aria-describedby="doc_files-err" @endif>
              <span class="hint">Optional. Up to {{ \App\Http\Requests\Crm\CustomerPoRequest::MAX_DOCS }} files: PDF, image, Word, Excel, CSV, text or email. Max {{ \App\Support\UploadLimit::label($docMaxKb) }} each.</span>
              @if ($docErrs->isNotEmpty())<span class="ferr" id="doc_files-err"><svg class="ico"><use href="#i-alert"/></svg>{{ $docErrs->implode(' ') }} Choose the files again.</span>@endif
            </div>
          </div>
        </div>
      @endif
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>{{ $ps->mandatory() }} A PO needs items or a PO value; with items the value is their sum. Items are the quotation customer's sales items (Sales or Both) and items of no party from the Item master (any sales item for a prospect); Rate left blank is the item's price. {{ $isNew ? 'Choose the PO / agreement documents above to save them with the PO; more can be uploaded after saving.' : 'The PO and agreement documents are in the box above this form.' }}</span></div>
      </fieldset>
      @include('crm.partials.audit', ['m' => $po])
    </div>
  </div>

  {{-- Thu 22/10/2026: no Delete PO button on this page (the destroy route stays). --}}
  <div class="formfoot">
    <span class="sp"></span>
    @if ($canSave)<button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>{{ $isNew ? 'Save PO' : 'Save changes' }}</button>@endif
  </div>
</form>

<template id="poLineTpl">
  <tr>
    <td data-label="Part" class="wide">
      <div class="picker" data-picker="field" data-url="__URL__" data-placeholder="Type a part no or description">
        <select class="inp" id="cpo_item___i__" name="lines[__i__][item_id]" aria-label="Part, item __n__">{!! $itemOpts(null) !!}</select>
      </div>
      <input class="inp" name="lines[__i__][description]" maxlength="255" placeholder="Description (blank = item description)" aria-label="Description, item __n__">
    </td>
    <td data-label="Quantity"><input class="inp num" name="lines[__i__][quantity]" inputmode="decimal" data-calc aria-label="Quantity, item __n__"></td>
    <td data-label="UOM"><select class="inp" name="lines[__i__][uom_id]" aria-label="UOM, item __n__">{!! $uomOpts('') !!}</select></td>
    <td data-label="Rate"><input class="inp num" name="lines[__i__][rate]" inputmode="decimal" data-calc placeholder="Item price" aria-label="Rate, item __n__"></td>
    <td class="r num" data-label="Amount" data-amount></td>
    <td class="del"><button class="btn gh icon sm" type="button" data-del-line aria-label="Remove item __n__"><svg class="ico"><use href="#i-x"/></svg></button></td>
  </tr>
</template>

@include('crm.partials.history', ['history' => $history])
@endsection

@push('scripts')
<script src="{{ asset('js/crm.js') }}?v={{ filemtime(public_path('js/crm.js')) }}"></script>
<script src="{{ asset('js/customer-po.js') }}?v={{ filemtime(public_path('js/customer-po.js')) }}"></script>
@endpush
