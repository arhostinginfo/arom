@php
  $u = auth()->user();
  $formRow = $editing;
  $canSave = $formRow ? $u->can('masters.edit') : $u->can('masters.add');
  // Admin > Page setup of this list: Order in dropdowns shown or hidden, optional or mandatory.
  $ps = app(\App\Services\PageSetupService::class)->form('master-'.$master);
@endphp
@extends('layouts.app', ['title' => $cfg['title'], 'crumbs' => [['Admin', route('admin.users.index')], ['Masters', route('admin.masters.index')], [$cfg['title'], null]]])

@section('content')
<div class="pagehd">
  <h1>{{ $cfg['title'] }}</h1>
  <span class="badge b-neu">{{ number_format($rows->total()) }}</span>
  <span class="sp"></span>
</div>

@if ($errors->any() && ! $errors->has('delete'))
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check the fields marked in red.</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif

<div class="mastersplit">
  <div>
    <form class="filterbar" method="GET" action="{{ route('admin.masters.show', $master) }}" role="search">
      <div class="field grow"><label for="q">Search</label><input class="inp" id="q" name="q" value="{{ $q }}" placeholder="Name starts with"></div>
      <button class="btn sec" type="submit"><svg class="ico"><use href="#i-search"/></svg>Search</button>
      @if ($q !== '')<a class="btn gh" href="{{ route('admin.masters.show', $master) }}">Clear</a>@endif
    </form>
    <div class="tablecard">
      <table class="atable rtable">
        <thead><tr>
          @include('partials.sorth', ['label' => 'Order', 'key' => 'order'])
          @include('partials.sorth', ['label' => 'Name', 'key' => 'name'])
          <th>Active</th><th><span class="sr">Actions</span></th>
        </tr></thead>
        <tbody>
        @forelse ($rows as $r)
          <tr class="{{ $editing && $editing->id === $r->id ? 'sel' : '' }}">
            <td data-label="Order" class="num">{{ $r->sort_order }}</td>
            <td data-label="Name" class="wide"><b>{{ $r->name }}</b></td>
            <td data-label="Active"><span class="badge {{ $r->is_active ? 'b-ok' : 'b-neu' }}">{{ $r->is_active ? 'Yes' : 'No' }}</span></td>
            <td class="r" style="white-space:nowrap">
              @can('masters.edit')<a class="btn gh sm" href="{{ request()->fullUrlWithQuery(['edit' => $r->id]) }}#mform"><svg class="ico"><use href="#i-pen"/></svg>Edit</a>@endcan
              @can('masters.delete')
                <form method="POST" action="{{ route('admin.masters.destroy', [$master, $r->id]) }}" data-confirm="Delete {{ $r->name }}? An entry in use cannot be deleted." style="display:inline">@csrf @method('DELETE')
                  <button class="btn gh icon sm" type="submit" aria-label="Delete {{ $r->name }}"><svg class="ico"><use href="#i-x"/></svg></button>
                </form>
              @endcan
            </td>
          </tr>
        @empty
          <tr><td colspan="4"><div class="empty">No entries match this search.</div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    @include('partials.pager', ['p' => $rows])
  </div>

  @if ($canSave)
  <form class="sectcard" id="mform" method="POST" action="{{ $formRow ? route('admin.masters.update', [$master, $formRow->id] + request()->only(['page', 'q', 'sort', 'dir', 'per_page'])) : route('admin.masters.store', $master) }}" novalidate data-saving>
    @csrf
    @if ($formRow) @method('PUT') @endif
    <div class="sh"><h2>{{ $formRow ? 'Edit '.$formRow->name : 'Add '.$cfg['item'] }}</h2></div>
    <div class="sb">
      <div class="field">
        <label for="name">Name<span class="req">*</span></label>
        <input class="inp @error('name') err @enderror" id="name" name="name" value="{{ old('name', $formRow?->name) }}" maxlength="100" required>
        @include('partials.ferr', ['name' => 'name'])
      </div>
      @if ($ps->show('sort_order'))
      <div class="field">
        <label for="sort_order">Order in dropdowns{{ $ps->star('sort_order') }}</label>
        <input class="inp num @error('sort_order') err @enderror" id="sort_order" @required($ps->req('sort_order')) name="sort_order" value="{{ old('sort_order', $formRow?->sort_order ?? $nextOrder) }}" inputmode="numeric">
        @include('partials.ferr', ['name' => 'sort_order'])
      </div>
      @endif
      <label class="chk"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $formRow ? $formRow->is_active : true))> Active (shown in dropdowns)</label>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        @if ($formRow)<a class="btn gh" href="{{ route('admin.masters.show', $master) }}">Cancel</a>@endif
        {{-- Fri 23/10/2026: Add (a new row) is green like every Add button; Save changes stays the primary colour. --}}
        <button class="btn {{ $formRow ? 'pri' : 'add' }}" type="submit"><svg class="ico"><use href="#i-{{ $formRow ? 'check' : 'plus' }}"/></svg>{{ $formRow ? 'Save changes' : 'Add' }}</button>
      </div>
    </div>
  </form>
  @endif
</div>
@endsection
