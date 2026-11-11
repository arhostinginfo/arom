{{-- Admin › Masters › Item master: one item as a form. Which fields show and which are mandatory: Admin › Page setup. --}}
@php
  $isNew = ! $item->exists;
  $u = auth()->user();
  $canSave = $isNew ? $u->can('masters.add') : $u->can('masters.edit');
  $v = fn ($f) => old($f, $item->{$f});
  $n = fn ($f) => old($f, $item->{$f} !== null ? rtrim(rtrim((string) $item->{$f}, '0'), '.') : '');
  $ps = app(\App\Services\PageSetupService::class)->form('item');
  $title = $isNew ? 'Add item' : $item->item_no;
  $numbers = ['price' => ['Price', 'decimal'], 'min_stock' => ['Min stock', 'stock_levels'], 'max_stock' => ['Max stock', 'stock_levels'], 'weight_per_piece' => ['Weight / piece', 'weight_per_piece'], 'packaging_qty' => ['Packaging qty', 'packaging_qty']];
@endphp
@extends('layouts.app', ['title' => $title, 'crumbs' => [['Admin', route('admin.users.index')], ['Masters', route('admin.masters.index')], ['Item master', route('admin.items.index')], [$isNew ? 'Add' : $item->item_no, null]]])

@section('content')
<div class="pagehd">
  <h1>{{ $isNew ? 'Add item' : $item->item_no.' · '.$item->description }}</h1>
  @unless ($isNew)<span class="badge {{ $item->status === 'Active' ? 'b-ok' : 'b-neu' }}">{{ $item->status }}</span>@endunless
  <span class="sp"></span>
  <a class="btn gh sm" href="{{ route('admin.items.index') }}"><svg class="ico"><use href="#i-list"/></svg>Item list</a>
</div>

@if ($errors->any() && ! $errors->has('delete'))
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check the fields marked in red.</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif
@error('delete')<div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><span>{{ $message }}</span></div>@enderror

<form method="POST" action="{{ $isNew ? route('admin.items.store') : route('admin.items.update', $item) }}" novalidate data-dirty-guard data-saving>
  @csrf
  @unless ($isNew) @method('PUT') @endunless
  <div class="sectcard">
    <div class="sh"><h2>Item details</h2></div>
    <div class="sb">
      <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0;display:grid;gap:14px">
      <div class="fgrid">
        @if ($ps->show('party_id'))
        <div class="field">
          <label for="party_id">Party name{{ $ps->star('party_id') }}</label>
          <div class="picker" data-picker="field" data-url="{{ route('sales.parties.lookup', ['all' => 1]) }}" data-placeholder="Type a party name">
            <select class="inp @error('party_id') err @enderror" id="party_id" name="party_id" @if ($ps->req('party_id')) aria-required="true" @endif>
              <option value="">None</option>
              @foreach ($parties as $id => $name)<option value="{{ $id }}" @selected((string) $v('party_id') === (string) $id)>{{ $name }}</option>@endforeach
            </select>
          </div>
          <span class="hint txt">The customer's part (or the supplier's). Tax invoices list each customer's parts.</span>
          @include('partials.ferr', ['name' => 'party_id'])
        </div>
        @endif
        @if ($ps->show('item_category_id'))
        <div class="field">
          <label for="item_category_id">Category{{ $ps->star('item_category_id') }}</label>
          <select class="inp @error('item_category_id') err @enderror" id="item_category_id" name="item_category_id" @if ($ps->req('item_category_id')) aria-required="true" @endif>
            <option value="">Select</option>
            @foreach ($categories as $c)<option value="{{ $c->id }}" @selected((string) $v('item_category_id') === (string) $c->id)>{{ $c->name }}{{ $c->item_no_prefix ? ' ('.$c->item_no_prefix.')' : '' }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'item_category_id'])
        </div>
        @endif
        <div class="field" style="grid-column:1/-1">
          <label for="description">Item description<span class="req">*</span></label>
          <input class="inp @error('description') err @enderror" id="description" name="description" value="{{ $v('description') }}" maxlength="255" required>
          @include('partials.ferr', ['name' => 'description'])
        </div>
        <div class="field">
          <label for="item_no">Item no</label>
          <input class="inp @error('item_no') err @enderror" id="item_no" name="item_no" value="{{ $v('item_no') }}" maxlength="50" style="text-transform:uppercase" placeholder="{{ $isNew ? 'Blank = automatic' : '' }}">
          <span class="hint txt">Leave blank for the next number of the category's prefix, e.g. PRS-0001.</span>
          @include('partials.ferr', ['name' => 'item_no'])
        </div>
        @if ($ps->show('internal_item_no'))
        <div class="field">
          <label for="internal_item_no">Internal item no (IPN){{ $ps->star('internal_item_no') }}</label>
          <input class="inp @error('internal_item_no') err @enderror" id="internal_item_no" name="internal_item_no" value="{{ $v('internal_item_no') }}" maxlength="50" style="text-transform:uppercase" @required($ps->req('internal_item_no'))>
          @include('partials.ferr', ['name' => 'internal_item_no'])
        </div>
        @endif
        <div class="field">
          {{-- Type as a drop-down instead of the segmented radio buttons (02/11/2026). --}}
          <label for="type">Type<span class="req">*</span></label>
          <select class="inp @error('type') err @enderror" id="type" name="type" aria-required="true">
            @foreach (\App\Models\Item::TYPES as $t)<option value="{{ $t }}" @selected($v('type') === $t)>{{ $t }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'type'])
        </div>
        <div class="field">
          <label for="hsn_sac_code_id">HSN/SAC<span class="req">*</span></label>
          <div class="picker" data-picker="field" data-url="{{ route('admin.items.hsn-lookup') }}" data-placeholder="Type an HSN or SAC code">
            <select class="inp @error('hsn_sac_code_id') err @enderror" id="hsn_sac_code_id" name="hsn_sac_code_id" aria-required="true">
              <option value="">Select</option>
              @foreach ($hsns as $id => $label)<option value="{{ $id }}" @selected((string) $v('hsn_sac_code_id') === (string) $id)>{{ $label }}</option>@endforeach
            </select>
          </div>
          @include('partials.ferr', ['name' => 'hsn_sac_code_id'])
        </div>
        <div class="field">
          <label for="uom_id">UOM<span class="req">*</span></label>
          <select class="inp @error('uom_id') err @enderror" id="uom_id" name="uom_id" aria-required="true">
            <option value="">Select</option>
            @foreach ($uoms as $o)<option value="{{ $o->id }}" @selected((string) $v('uom_id') === (string) $o->id)>{{ $o->code }}{{ $o->description ? ' · '.$o->description : '' }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'uom_id'])
        </div>
        <div class="field">
          <label for="tax_structure_id">Tax structure<span class="req">*</span></label>
          <select class="inp @error('tax_structure_id') err @enderror" id="tax_structure_id" name="tax_structure_id" aria-required="true">
            <option value="">Select</option>
            @foreach ($taxes as $o)<option value="{{ $o->id }}" @selected((string) $v('tax_structure_id') === (string) $o->id)>{{ $o->name }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'tax_structure_id'])
        </div>
        @foreach ($numbers as $f => [$label, $ps_key])
          @php($key = in_array($f, ['min_stock', 'max_stock'], true) ? 'stock_levels' : $f)
          @continue(! $ps->show($key))
          <div class="field">
            <label for="{{ $f }}">{{ $label }}{{ $ps->star($key) }}</label>
            <input class="inp num @error($f) err @enderror" id="{{ $f }}" name="{{ $f }}" value="{{ $n($f) }}" inputmode="decimal" @required($ps->req($key))>
            @include('partials.ferr', ['name' => $f])
          </div>
        @endforeach
        @if ($ps->show('currency_id'))
        <div class="field">
          <label for="currency_id">Currency{{ $ps->star('currency_id') }}</label>
          <select class="inp @error('currency_id') err @enderror" id="currency_id" name="currency_id" @if ($ps->req('currency_id')) aria-required="true" @endif>
            <option value="">Select</option>
            @foreach ($currencies as $c)<option value="{{ $c->id }}" @selected((string) $v('currency_id') === (string) $c->id)>{{ $c->code }} · {{ $c->country }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'currency_id'])
        </div>
        @endif
        <div class="field">
          {{-- Status as a drop-down (02/11/2026). --}}
          <label for="status">Status<span class="req">*</span></label>
          <select class="inp @error('status') err @enderror" id="status" name="status" aria-required="true">
            @foreach (['Active', 'Inactive'] as $s)<option value="{{ $s }}" @selected($v('status') === $s)>{{ $s }}</option>@endforeach
          </select>
          @include('partials.ferr', ['name' => 'status'])
        </div>
      </div>
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>{{ $ps->mandatory() }} Price is the default rate on sales orders and invoices without a PO. A customer's item cannot be a Purchase item; a supplier's cannot be a Sales item.</span></div>
      </fieldset>
      @include('crm.partials.audit', ['m' => $item])
    </div>
  </div>
  <div class="formfoot">
    @if (! $isNew && $u->can('masters.delete'))
      <button class="btn sec" type="submit" form="delForm" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Delete item</button>
    @endif
    <span class="sp"></span>
    @if ($canSave)<button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>{{ $isNew ? 'Save item' : 'Save changes' }}</button>@endif
  </div>
</form>
@if (! $isNew && $u->can('masters.delete'))
  <form id="delForm" method="POST" action="{{ route('admin.items.destroy', $item) }}" data-confirm="Delete item {{ $item->item_no }}? An item on a sales order or invoice cannot be deleted; make it Inactive instead.">@csrf @method('DELETE')</form>
@endif
@include('crm.partials.history', ['history' => $history])
@endsection
