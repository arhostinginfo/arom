{{-- Admin › Masters: Tax master, HSN/SAC, UOM, Category (items), Transporter. Fields from config('nursery.sales_masters'). --}}
@php
  $u = auth()->user();
  $formRow = $editing;
  $canSave = $formRow ? $u->can('masters.edit') : $u->can('masters.add');
  // Admin › Page setup of this master: which optional fields show and which are mandatory.
  $ps = app(\App\Services\PageSetupService::class)->form('master-'.$master);
  $fields = array_filter($cfg['fields'], fn ($f, $k) => $ps->show($k), ARRAY_FILTER_USE_BOTH);
  $show = function ($row, $f, $field) use ($options) {
      $v = $row->{$f};
      if ($field['type'] === 'select') { return $options[$f][$v] ?? ($v ? $field['model']::find($v)?->label() : ''); }
      if ($field['type'] === 'percent') { return $v === null ? '' : rtrim(rtrim((string) $v, '0'), '.'); }
      return $v;
  };
@endphp
@extends('layouts.app', ['title' => $cfg['title'], 'crumbs' => [['Admin', route('admin.users.index')], ['Masters', route('admin.masters.index')], [$cfg['title'], null]]])

@section('content')
<div class="pagehd">
  <h1>{{ $cfg['title'] }}</h1>
  <span class="badge b-neu">{{ \App\Support\Money::indian($rows->total()) }}</span>
  <span class="sp"></span>
</div>

@if ($errors->any() && ! $errors->has('delete'))
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check the fields marked in red.</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif
@error('delete')
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><span>{{ $message }}</span></div>
@enderror

<div class="mastersplit">
  <div>
    <form class="filterbar" method="GET" action="{{ route('admin.sales-masters.show', $master) }}" role="search">
      <div class="field grow"><label for="q">Search</label><input class="inp" id="q" name="q" value="{{ $q }}" placeholder="{{ $cfg['fields'][$cfg['search']]['label'] }} starts with"></div>
      <button class="btn sec" type="submit"><svg class="ico"><use href="#i-search"/></svg>Search</button>
      @if ($q !== '')<a class="btn gh" href="{{ route('admin.sales-masters.show', $master) }}">Clear</a>@endif
    </form>
    <div class="tablecard">
      <table class="atable rtable">
        <thead><tr>
          @foreach ($fields as $f => $field)
            @if ($f === $cfg['search'])
              @include('partials.sorth', ['label' => $field['label'], 'key' => $f])
            @else
              <th class="{{ $field['type'] === 'percent' ? 'r' : '' }}">{{ $field['label'] }}</th>
            @endif
          @endforeach
          <th>Active</th><th><span class="sr">Actions</span></th>
        </tr></thead>
        <tbody>
        @forelse ($rows as $r)
          <tr class="{{ $editing && $editing->id === $r->id ? 'sel' : '' }}">
            @foreach ($fields as $f => $field)
              <td data-label="{{ $field['label'] }}" class="{{ $f === $cfg['search'] ? 'wide' : '' }} {{ $field['type'] === 'percent' ? 'r num' : '' }}">@if ($f === $cfg['search'])<b>{{ $show($r, $f, $field) }}</b>@else{{ $show($r, $f, $field) }}@endif</td>
            @endforeach
            <td data-label="Active"><span class="badge {{ $r->is_active ? 'b-ok' : 'b-neu' }}">{{ $r->is_active ? 'Yes' : 'No' }}</span></td>
            <td class="r" style="white-space:nowrap">
              @can('masters.edit')<a class="btn gh sm" href="{{ request()->fullUrlWithQuery(['edit' => $r->id]) }}#mform" aria-label="Edit {{ $r->label() }}"><svg class="ico"><use href="#i-pen"/></svg>Edit</a>@endcan
              @can('masters.delete')
                <form method="POST" action="{{ route('admin.sales-masters.destroy', [$master, $r->id]) }}" data-confirm="Delete {{ $r->label() }}? An entry in use cannot be deleted." style="display:inline">@csrf @method('DELETE')
                  <button class="btn gh icon sm" type="submit" aria-label="Delete {{ $r->label() }}"><svg class="ico"><use href="#i-x"/></svg></button>
                </form>
              @endcan
            </td>
          </tr>
        @empty
          <tr><td colspan="{{ count($fields) + 2 }}"><div class="empty">{{ $q !== '' ? 'No entries match this search.' : 'No entries yet.' }}</div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    @include('partials.pager', ['p' => $rows])
  </div>

  @if ($canSave)
  <form class="sectcard" id="mform" method="POST" action="{{ $formRow ? route('admin.sales-masters.update', [$master, $formRow->id] + request()->only(['page', 'q', 'sort', 'dir', 'per_page'])) : route('admin.sales-masters.store', $master) }}" novalidate data-saving>
    @csrf
    @if ($formRow) @method('PUT') @endif
    <div class="sh"><h2>{{ $formRow ? 'Edit '.$formRow->label() : 'Add '.$cfg['item'] }}</h2></div>
    <div class="sb">
      @foreach ($fields as $f => $field)
        @php($val = old($f, $formRow ? $show($formRow, $f, ['type' => $field['type'] === 'select' ? 'raw' : $field['type']]) : null))
        <div class="field">
          <label for="{{ $f }}">{{ $field['label'] }}{{ $ps->star($f) }}</label>
          @if ($field['type'] === 'enum')
            <select class="inp @error($f) err @enderror" id="{{ $f }}" name="{{ $f }}" @required($ps->req($f))>
              @foreach ($field['options'] as $o)<option @selected((string) $val === $o)>{{ $o }}</option>@endforeach
            </select>
          @elseif ($field['type'] === 'select')
            <select class="inp @error($f) err @enderror" id="{{ $f }}" name="{{ $f }}" @if ($ps->req($f)) aria-required="true" @endif>
              <option value="">Select</option>
              @foreach ($options[$f] as $id => $label)<option value="{{ $id }}" @selected((string) $val === (string) $id)>{{ $label }}</option>@endforeach
            </select>
          @else
            <input class="inp {{ $field['type'] === 'percent' ? 'num' : '' }} @error($f) err @enderror" id="{{ $f }}" name="{{ $f }}" value="{{ $val }}"
              @if ($field['type'] === 'percent') inputmode="decimal" @else maxlength="{{ $field['type'] === 'gstin' ? 15 : ($field['max'] ?? 255) }}" @endif
              @if (in_array($field['type'], ['code', 'gstin'], true)) style="text-transform:uppercase" @endif @required($ps->req($f))>
          @endif
          @include('partials.ferr', ['name' => $f])
        </div>
      @endforeach
      <label class="chk"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $formRow ? $formRow->is_active : true))> Active (shown in dropdowns)</label>
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>{{ $ps->mandatory() }}@if ($master === 'tax-structures') CGST and SGST are equal halves of IGST.@endif</span></div>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        @if ($formRow)<a class="btn gh" href="{{ route('admin.sales-masters.show', $master) }}">Cancel</a>@endif
        {{-- Fri 23/10/2026: Add (a new row) is green like every Add button; Save changes stays the primary colour. --}}
        <button class="btn {{ $formRow ? 'pri' : 'add' }}" type="submit"><svg class="ico"><use href="#i-{{ $formRow ? 'check' : 'plus' }}"/></svg>{{ $formRow ? 'Save changes' : 'Add' }}</button>
      </div>
    </div>
  </form>
  @endif
</div>
@endsection
