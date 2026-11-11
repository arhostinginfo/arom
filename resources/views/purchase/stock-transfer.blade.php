{{--
  Purchase › Stock transfer (prototype D9): one entry as a full form with the entry picker, approval bar, items with the
  stock at the source, and the approval and change history. Stock moves only when the transfer is Approved.
--}}
@php
  $u = auth()->user();
  $df = config('nursery.date_format');
  $t = $transfer;
  $isNew = ! $t->exists;
  $canSave = $isNew ? $u->can('stock-transfer.add') : ($u->can('stock-transfer.edit') && $t->isEditable());
  $ps = app(\App\Services\PageSetupService::class)->form('stock-transfer');
  $hasOld = old('_form') === 'transfer';
  $v = fn ($f) => $hasOld ? old($f, $t->{$f}) : $t->{$f};
  $from = $isNew ? $v('from_unit_id') : $t->from_unit_id;
  $options = $entries->map(fn ($e) => ['id' => $e->id, 'url' => route('purchase.stock-transfers.show', $e),
      'label' => $e->transfer_no.' · '.($e->fromUnit?->name ?? '').' → '.($e->toUnit?->name ?? ''), 'on' => $t->exists && $e->id === $t->id])->all();
  $buttons = [['href' => route('purchase.stock-transfers.list'), 'icon' => 'list', 'label' => 'Stock transfer list', 'nav' => true]];

  // Lines: after a failed save the rows as typed (posted as one JSON field), otherwise the saved ones.
  $typed = null;
  if ($hasOld) {
      $l = old('lines') ?: json_decode((string) old('lines_json', '[]'), true);
      $typed = array_values(array_filter(is_array($l) ? $l : [], 'is_array'));
  }
  $oldItems = $typed ? \App\Models\Item::withTrashed()->with('uom:id,code')->whereIn('id', array_filter(array_column($typed, 'item_id')))->get()->keyBy('id') : collect();
  $lines = $typed !== null
    ? collect($typed)->map(fn ($l) => (object) [
        'item_id' => $l['item_id'] ?? null, 'quantity' => $l['quantity'] ?? '', 'remarks' => $l['remarks'] ?? '',
        'label' => ! empty($l['item_id']) ? $oldItems->get($l['item_id'])?->pickerLabel() : null,
        'uom' => ! empty($l['item_id']) ? $oldItems->get($l['item_id'])?->uom?->code : null,
      ])->all()
    : $t->lines->map(fn ($l) => (object) [
        'item_id' => $l->item_id, 'quantity' => rtrim(rtrim(number_format((float) $l->quantity, 3, '.', ''), '0'), '.'), 'remarks' => $l->remarks,
        'label' => $l->item?->pickerLabel(), 'uom' => $l->uom?->code ?? $l->item?->uom?->code,
      ])->all();
  if (! $lines) { $lines = [(object) ['item_id' => null, 'quantity' => '', 'remarks' => '', 'label' => null, 'uom' => null]]; }
  // Stock at the source now, for the rows on screen (an Approved transfer has already taken its quantities out).
  $stock = $from ? \App\Services\StockLedger::onHand([(int) $from], array_filter(array_map(fn ($l) => (int) $l->item_id, $lines))) : [];
  $itemsUrl = route('purchase.stock-transfers.items', array_filter(['from' => $from]));
  $showStock = ! in_array($t->status, [\App\Models\StockTransfer::APPROVED, \App\Models\StockTransfer::CANCELLED], true);
  $canCancel = $t->exists && $t->status !== \App\Models\StockTransfer::CANCELLED && $u->can('stock-transfer.delete')
      && ($t->status !== \App\Models\StockTransfer::APPROVED || $u->can('stock-transfer-approval.edit'));
  $canDelete = $t->exists && $t->isEditable() && $u->can('stock-transfer.delete');
  $sourceType = $v('source_type');
@endphp
@extends('layouts.app', ['title' => 'Stock transfer', 'crumbs' => [['Purchase', route('purchase.stock-transfers.index')], ['Stock transfer', null]]])

@section('content')
<div class="pagehd">
  <h1>Stock transfer</h1>
  <span class="sp"></span>
  <span class="cap muted">Unit: {{ $unit?->name ?? 'none' }} · transfers from or to this unit</span>
</div>

<div class="sectcard" id="stCard">
  <div class="sh">
    <h2>Stock transfer</h2>
    @if ($t->exists)<span class="badge {{ $t->badgeClass() }}">{{ $t->status }}</span>@endif
    <span class="sp"></span>
    <div class="autono"><span>Transfer no <b>{{ $t->transfer_no ?? 'on save' }}</b></span></div>
  </div>
  <div class="sb">
    @include('crm.partials.entrybar', [
      'action' => route('purchase.stock-transfers.index'), 'lookupUrl' => route('purchase.stock-transfers.lookup'), 'q' => $q, 'options' => $options, 'current' => $t->exists,
      'newLabel' => 'New stock transfer', 'placeholder' => 'Transfer no',
      'addUrl' => $u->can('stock-transfer.add') ? route('purchase.stock-transfers.create') : null, 'buttons' => $buttons,
    ])

    @if ($errors->any())
      <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>{{ $errors->hasAny(['status', 'form']) ? 'This was not done.' : 'Check the fields marked in red. Nothing was saved.' }}</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
    @endif
    @if (! $unit)
      <div class="alert info"><svg class="ico"><use href="#i-info"/></svg><span>No unit is allowed for your user, so you cannot make stock transfers. Ask an Admin to add your units in Users list.</span></div>
    @endif

    {{-- Approval bar: the next actions for the current status only. --}}
    <div class="apbar statusbar" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px">
      <span class="cap muted">Status</span>
      <span class="badge {{ $t->badgeClass() }}">{{ $t->status }}</span>
      @if ($t->status === \App\Models\StockTransfer::PENDING)<span class="cap muted">Level 1 of 1 · waiting for an approver</span>@endif
      @if ($t->approved_at)<span class="cap muted">Approved by {{ $t->approver?->name ?? 'System' }} on {{ $t->approved_at->format($df) }}</span>@endif
      @if ($t->status_reason)<span class="cap" style="color:var(--error-700)">Reason: {{ $t->status_reason }}</span>@endif
      <span class="sp" style="flex:1"></span>
      @if ($isNew)
        <span class="cap muted">Save the entry, then send it for approval. Stock is usable only after Approved.</span>
      @elseif ($t->isEditable() && $u->can('stock-transfer.edit'))
        <form method="POST" action="{{ route('purchase.stock-transfers.submit', $t) }}" data-saving
          data-confirm-title="Send {{ $t->transfer_no }} for approval"
          data-confirm="{{ $rule->is_required ? 'This stock transfer will go to an approver. The stock moves when it is approved. Save any unsaved changes first. Continue?' : 'Approval is not required for stock transfers, so the stock moves now. Save any unsaved changes first. Continue?' }}">@csrf
          <button class="btn pri sm" type="submit"><svg class="ico"><use href="#i-check"/></svg>{{ $rule->is_required ? 'Send for approval' : 'Approve and move stock' }}</button>
        </form>
      @endif
      @if ($t->status === \App\Models\StockTransfer::PENDING)
        @can('stock-transfer-approval.edit')
          <button class="btn sec sm" type="button" data-open="#stRejectLayer" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Reject</button>
          <form method="POST" action="{{ route('purchase.stock-transfers.approve', $t) }}" data-saving data-confirm-title="Approve {{ $t->transfer_no }}" data-confirm="The quantities go out of {{ $t->fromUnit?->name }} and into {{ $t->toUnit?->name }} now. Continue?">@csrf<button class="btn pri sm" type="submit"><svg class="ico"><use href="#i-check"/></svg>Approve</button></form>
        @else
          <span class="cap muted">Log in as an approver to approve.</span>
        @endcan
      @endif
    </div>
    @if ($t->exists && ! $canSave && $u->can('stock-transfer.edit'))
      <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>{{ $t->transfer_no }} is {{ $t->status }}, so it cannot be changed.@if ($t->status === \App\Models\StockTransfer::APPROVED) The stock has moved from {{ $t->fromUnit?->name }} to {{ $t->toUnit?->name }}.@endif</span></div>
    @elseif ($t->exists && ! $u->can('stock-transfer.edit'))
      <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>You can view this stock transfer. Your role cannot change it.</span></div>
    @endif

    <form method="POST" action="{{ $isNew ? route('purchase.stock-transfers.store') : route('purchase.stock-transfers.update', $t) }}" novalidate id="transferForm" data-dirty-guard data-saving data-lines-form="#stLines"
      data-stock-url="{{ route('purchase.stock-transfers.stock') }}">
      @csrf
      @unless ($isNew) @method('PUT') @endunless
      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0;display:grid;gap:14px">
      <div class="fgrid">
        <div class="field">
          <label for="transfer_date">Transfer date<span class="req">*</span></label>
          <input class="inp num @error('transfer_date') err @enderror" id="transfer_date" name="transfer_date" value="{{ $hasOld ? old('transfer_date') : $t->transfer_date?->format($df) }}" placeholder="dd/mm/yyyy" inputmode="numeric" required>
          @include('partials.ferr', ['name' => 'transfer_date'])
        </div>
        <div class="field">
          <label for="from_unit_id">Location from<span class="req">*</span></label>
          @if ($isNew)
            <select class="inp @error('from_unit_id') err @enderror" id="from_unit_id" name="from_unit_id" aria-required="true">
              <option value="">Select</option>
              @foreach ($units as $un)<option value="{{ $un->id }}" @selected((string) $from === (string) $un->id)>{{ $un->name }}</option>@endforeach
            </select>
            <span class="hint txt">The unit the stock goes out of. Its stock is shown on each item.</span>
          @else
            <input type="hidden" name="from_unit_id" id="from_unit_id" value="{{ $t->from_unit_id }}">
            <div class="kvline"><b>{{ $t->fromUnit?->name }}</b></div>
            <span class="hint txt">Fixed after saving: the transfer no belongs to this unit's series.</span>
          @endif
          @include('partials.ferr', ['name' => 'from_unit_id'])
        </div>
        <div class="field">
          <label for="to_unit_id">Location to<span class="req">*</span></label>
          <select class="inp @error('to_unit_id') err @enderror" id="to_unit_id" name="to_unit_id" aria-required="true">
            <option value="">Select</option>
            @foreach ($units as $un)<option value="{{ $un->id }}" @selected((string) $v('to_unit_id') === (string) $un->id)>{{ $un->name }}</option>@endforeach
            @if ($t->to_unit_id && ! $units->contains('id', $t->to_unit_id))<option value="{{ $t->to_unit_id }}" selected>{{ $t->toUnit?->name }}</option>@endif
          </select>
          <span class="ferr" id="stSameUnit" hidden><svg class="ico"><use href="#i-alert"/></svg>Location from and to are the same</span>
          @include('partials.ferr', ['name' => 'to_unit_id'])
        </div>
        @if ($ps->show('source_type'))
        <div class="field">
          {{-- Source type as a drop-down instead of the segmented radio buttons (02/11/2026). New transfers start on Purchase;
               an older transfer saved without one keeps a blank choice so saving it does not quietly set Purchase. --}}
          <label for="source_type">Source type{{ $ps->star('source_type') }}</label>
          <select class="inp @error('source_type') err @enderror" id="source_type" name="source_type" @if ($ps->req('source_type')) aria-required="true" @endif>
            @if (! in_array($sourceType, \App\Models\StockTransfer::SOURCE_TYPES, true))<option value="">Choose…</option>@endif
            @foreach (\App\Models\StockTransfer::SOURCE_TYPES as $st)<option value="{{ $st }}" @selected($sourceType === $st)>{{ $st }}</option>@endforeach
          </select>
          <span class="hint txt">The kind of inward document the stock came from.</span>
          @include('partials.ferr', ['name' => 'source_type'])
        </div>
        @endif
        @if ($ps->show('remarks'))
        <div class="field" style="grid-column:1/-1">
          <label for="remarks">Remarks{{ $ps->star('remarks') }}</label>
          <textarea class="inp @error('remarks') err @enderror" id="remarks" name="remarks" rows="2" maxlength="1000" style="height:auto;padding-top:8px" @required($ps->req('remarks'))>{{ $v('remarks') }}</textarea>
          @include('partials.ferr', ['name' => 'remarks'])
        </div>
        @endif
      </div>

      <div class="lbl">Items<span class="req">*</span></div>
      <div class="tablecard" style="overflow-x:auto">
        <table class="atable lines rtable">
          <thead><tr><th style="min-width:260px">Item no / Description</th><th class="r" style="min-width:120px">{{ $showStock ? 'Stock at source' : 'Stock at source now' }}</th><th style="min-width:70px">UOM</th><th class="r" style="min-width:120px">Quantity<span class="req">*</span></th><th style="min-width:160px">Remarks</th><th><span class="sr">Remove</span></th></tr></thead>
          <tbody id="stLines" data-items-url="{{ $itemsUrl }}" data-check="{{ $showStock ? '1' : '' }}">
            @foreach ($lines as $i => $l)
              @php($have = $l->item_id ? (float) ($stock[(int) $l->item_id] ?? 0) : null)
              <tr @if ($have !== null) data-stock="{{ $have }}" @endif>
                <td data-label="Item" class="wide">
                  <div class="picker" data-picker="field" data-url="{{ $itemsUrl }}" data-placeholder="Type an item no or description">
                    <select class="inp @error("lines.$i.item_id") err @enderror" id="st_item_{{ $i }}" name="lines[{{ $i }}][item_id]" aria-label="Item {{ $i + 1 }}">
                      <option value="">Select</option>
                      @if ($l->item_id)<option value="{{ $l->item_id }}" selected data-uom="{{ $l->uom }}" data-stock="{{ $have }}">{{ $l->label }}</option>@endif
                    </select>
                  </div>
                  @include('partials.ferr', ['name' => "lines.$i.item_id"])
                </td>
                <td class="r num" data-label="Stock at source" data-stock-cell>{{ $have !== null ? \App\Support\Money::qty($have) : '' }}</td>
                <td data-label="UOM" data-uom-cell>{{ $l->uom }}</td>
                <td data-label="Quantity"><input class="inp num @error("lines.$i.quantity") err @enderror" name="lines[{{ $i }}][quantity]" value="{{ $l->quantity }}" inputmode="decimal" data-qty aria-label="Quantity, item {{ $i + 1 }}" @if ($have !== null && $showStock) placeholder="Max {{ \App\Support\Money::qty($have) }}" @endif>
                  <span class="ferr" data-over hidden><svg class="ico"><use href="#i-alert"/></svg>Quantity is more than the stock at the source</span>
                  @include('partials.ferr', ['name' => "lines.$i.quantity"])</td>
                <td data-label="Remarks"><input class="inp @error("lines.$i.remarks") err @enderror" name="lines[{{ $i }}][remarks]" value="{{ $l->remarks }}" maxlength="255" aria-label="Remarks, item {{ $i + 1 }}">@include('partials.ferr', ['name' => "lines.$i.remarks"])</td>
                <td class="del">@if ($canSave)<button class="btn gh icon sm" type="button" data-del-line aria-label="Remove item {{ $i + 1 }}"><svg class="ico"><use href="#i-x"/></svg></button>@endif</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @include('partials.ferr', ['name' => 'lines'])
      @if ($canSave)<div><button class="btn add sm" type="button" data-add-line="#stLines"><svg class="ico"><use href="#i-plus"/></svg>Add item</button></div>@endif
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>{{ $ps->mandatory() }} Moves items between units (plants). Location from and to must differ, and a quantity cannot be more than the stock at the source; this is checked again when the transfer is approved. Stock is usable only after Approved: it then goes out of the source and into the destination. Cancelling an approved transfer returns the stock, while the destination still holds it.</span></div>
      </fieldset>
      <input type="hidden" name="_form" value="transfer">
      @include('crm.partials.audit', ['m' => $t])
      <div class="formfoot">
        @if ($canDelete)
          <button class="btn sec" type="submit" form="stDelForm" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Delete stock transfer</button>
        @elseif ($canCancel)
          <button class="btn sec" type="button" data-open="#stCancelLayer" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Cancel stock transfer</button>
        @endif
        <span class="sp"></span>
        @if ($canSave && $unit)<button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>{{ $isNew ? 'Save stock transfer' : 'Save changes' }}</button>@endif
      </div>
    </form>

    @if ($canDelete)
      <form id="stDelForm" method="POST" action="{{ route('purchase.stock-transfers.destroy', $t) }}" data-confirm="Delete stock transfer {{ $t->transfer_no }}? It has not moved any stock.">@csrf @method('DELETE')</form>
    @endif
  </div>
</div>

@if ($approvals->isNotEmpty())
  <div class="sectcard">
    <div class="sh"><h2>Approval history</h2></div>
    <div class="sb">
      @foreach ($approvals as $a)
        <div class="timeline"><div class="ev"><span></span><div><b>{{ $a->action }}</b>@if ($a->remarks) · {{ $a->remarks }}@endif<div class="cap muted">{{ $a->actor?->name ?? 'System' }} · {{ $a->acted_at->format(config('nursery.datetime_format')) }}</div></div></div></div>
      @endforeach
    </div>
  </div>
@endif
@include('crm.partials.history', ['history' => $history])

<template id="stLineTpl">
  <tr>
    <td data-label="Item" class="wide">
      <div class="picker" data-picker="field" data-url="__URL__" data-placeholder="Type an item no or description">
        <select class="inp" id="st_item___i__" name="lines[__i__][item_id]" aria-label="Item __n__"><option value="">Select</option></select>
      </div>
    </td>
    <td class="r num" data-label="Stock at source" data-stock-cell></td>
    <td data-label="UOM" data-uom-cell></td>
    <td data-label="Quantity"><input class="inp num" name="lines[__i__][quantity]" inputmode="decimal" data-qty aria-label="Quantity, item __n__"><span class="ferr" data-over hidden><svg class="ico"><use href="#i-alert"/></svg>Quantity is more than the stock at the source</span></td>
    <td data-label="Remarks"><input class="inp" name="lines[__i__][remarks]" maxlength="255" aria-label="Remarks, item __n__"></td>
    <td class="del"><button class="btn gh icon sm" type="button" data-del-line aria-label="Remove item __n__"><svg class="ico"><use href="#i-x"/></svg></button></td>
  </tr>
</template>

@if ($t->exists && $t->status === \App\Models\StockTransfer::PENDING && $u->can('stock-transfer-approval.edit'))
  <div class="overlay" id="stRejectLayer" hidden @if ($errors->has('remarks') && old('_action') === 'reject') data-autoopen @endif>
    <div class="scrim" data-x></div>
    <form class="modal" role="dialog" aria-modal="true" aria-labelledby="stRejT" method="POST" action="{{ route('purchase.stock-transfers.reject', $t) }}" data-saving>
      @csrf <input type="hidden" name="_action" value="reject">
      <div class="mh"><h3 id="stRejT">Reject {{ $t->transfer_no }}</h3></div>
      <div class="mb">
        <div class="field">
          <label for="st_reject_remarks">Reason<span class="req">*</span></label>
          <textarea class="inp" id="st_reject_remarks" name="remarks" rows="3" maxlength="500" style="height:auto;padding-top:8px" required></textarea>
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
  <div class="overlay" id="stCancelLayer" hidden @if ($errors->has('remarks') && old('_action') === 'cancel') data-autoopen @endif>
    <div class="scrim" data-x></div>
    <form class="modal" role="dialog" aria-modal="true" aria-labelledby="stCanT" method="POST" action="{{ route('purchase.stock-transfers.cancel', $t) }}" data-saving>
      @csrf <input type="hidden" name="_action" value="cancel">
      <div class="mh"><h3 id="stCanT">Cancel {{ $t->transfer_no }}</h3></div>
      <div class="mb">
        <div class="field">
          <label for="st_cancel_remarks">Reason<span class="req">*</span></label>
          <textarea class="inp" id="st_cancel_remarks" name="remarks" rows="3" maxlength="500" style="height:auto;padding-top:8px" required></textarea>
        </div>
        <div class="small muted">The number stays in the series and the transfer is kept as Cancelled.@if ($t->status === \App\Models\StockTransfer::APPROVED) The stock goes back from {{ $t->toUnit?->name }} to {{ $t->fromUnit?->name }}; this needs {{ $t->toUnit?->name }} to still hold every quantity it received.@endif</div>
      </div>
      <div class="mf" style="display:flex;gap:8px;justify-content:flex-end;padding:12px 20px 18px">
        <button class="btn gh" type="button" data-x>Keep transfer</button>
        <button class="btn pri" type="submit" style="background:var(--error-600)">Cancel transfer</button>
      </div>
    </form>
  </div>
@endif
@endsection

@push('scripts')
<script src="{{ asset('js/crm.js') }}?v={{ filemtime(public_path('js/crm.js')) }}"></script>
<script src="{{ asset('js/stock-transfer.js') }}?v={{ filemtime(public_path('js/stock-transfer.js')) }}"></script>
@endpush
