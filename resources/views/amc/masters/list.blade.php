{{-- AMC › Masters: Business segment, Planter, Plant, AMC type. Fields from config('amc.masters'); list paged on the server. --}}
@php
  $u = auth()->user();
  $p = $cfg['perm'];
  $canSave = $editing ? $u->can($p.'.edit') : $u->can($p.'.add');
  $base = 'amc.masters.'.$master;
@endphp
@extends('layouts.app', ['title' => $cfg['title']])

@section('content')
@include('amc.partials.assets')
<div class="pagehd">
  <h1>{{ $cfg['title'] }}</h1>
  <span class="badge b-neu">{{ \App\Support\Money::indian($rows->total()) }}</span>
  <span class="sp"></span>
</div>

@include('amc.partials.errors')

<div class="mastersplit">
  <div>
    <form class="filterbar" method="GET" action="{{ route($base.'.index') }}" role="search">
      <div class="field grow"><label for="q">Search</label><input class="inp" id="q" name="q" value="{{ $q }}" placeholder="Name starts with"></div>
      <div class="field"><label for="status">Active</label>
        <select class="inp" id="status" name="status"><option value="">All</option><option value="active" @selected($status === 'active')>Active</option><option value="inactive" @selected($status === 'inactive')>Inactive</option></select></div>
      <button class="btn sec" type="submit"><svg class="ico"><use href="#i-search"/></svg>Search</button>
      @if ($q !== '' || $status)<a class="btn gh" href="{{ route($base.'.index') }}">Clear</a>@endif
    </form>
    <div class="tablecard">
      <table class="atable rtable">
        <thead><tr>
          @include('partials.sorth', ['label' => 'Name', 'key' => 'name'])
          @foreach ($cfg['fields'] as $f => $field)@if ($f !== 'name')<th>{{ $field['label'] }}</th>@endif @endforeach
          <th>Active</th><th><span class="sr">Actions</span></th>
        </tr></thead>
        <tbody>
        @forelse ($rows as $r)
          <tr class="{{ $editing && $editing->id === $r->id ? 'sel' : '' }}">
            <td data-label="Name" class="wide"><b>{{ $r->name }}</b></td>
            @foreach ($cfg['fields'] as $f => $field)@if ($f !== 'name')<td data-label="{{ $field['label'] }}">{{ $r->{$f} }}</td>@endif @endforeach
            <td data-label="Active"><span class="badge {{ $r->is_active ? 'b-ok' : 'b-neu' }}">{{ $r->is_active ? 'Yes' : 'No' }}</span></td>
            <td class="r" style="white-space:nowrap">
              @can($p.'.edit')<a class="btn gh sm" href="{{ request()->fullUrlWithQuery(['edit' => $r->id]) }}#mform" aria-label="Edit {{ $r->label() }}"><svg class="ico"><use href="#i-pen"/></svg>Edit</a>@endcan
              @can($p.'.delete')
                <form method="POST" action="{{ route($base.'.destroy', $r->id) }}" data-confirm="Delete {{ $r->label() }}? An entry in use cannot be deleted." style="display:inline">@csrf @method('DELETE')
                  <button class="btn gh icon sm" type="submit" aria-label="Delete {{ $r->label() }}"><svg class="ico"><use href="#i-x"/></svg></button>
                </form>
              @endcan
            </td>
          </tr>
        @empty
          <tr><td colspan="{{ count($cfg['fields']) + 2 }}"><div class="empty">{{ $q !== '' || $status ? 'No entries match this search.' : 'No entries yet.' }}</div></td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    @include('partials.pager', ['p' => $rows])
  </div>

  @if ($canSave)
  <form class="sectcard" id="mform" method="POST" action="{{ $editing ? route($base.'.update', [$editing->id] + request()->only(['page', 'q', 'status', 'sort', 'dir', 'per_page'])) : route($base.'.store') }}" novalidate data-saving>
    @csrf
    @if ($editing) @method('PUT') @endif
    <div class="sh"><h2>{{ $editing ? 'Edit '.$editing->label() : 'Add '.$cfg['item'] }}</h2></div>
    <div class="sb">
      @foreach ($cfg['fields'] as $f => $field)
        <div class="field">
          <label for="{{ $f }}">{{ $field['label'] }}@if (empty($field['optional']))<span class="req">*</span>@endif</label>
          <input class="inp @error($f) err @enderror" id="{{ $f }}" name="{{ $f }}" value="{{ old($f, $editing?->{$f}) }}" maxlength="{{ $field['max'] }}" @if (empty($field['optional'])) required @endif>
          @include('partials.ferr', ['name' => $f])
        </div>
      @endforeach
      <label class="chk"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing ? $editing->is_active : true))> Active (shown in dropdowns)</label>
      <div class="ruleh"><svg class="ico"><use href="#i-info"/></svg><span>Fields marked * are mandatory. {{ $cfg['hint'] }} The name cannot repeat. @if ($editing && $editingUsed) In use on {{ $editingUsed }} record(s), so it cannot be deleted; make it inactive to hide it from dropdowns.@endif</span></div>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        @if ($editing)<a class="btn gh" href="{{ route($base.'.index') }}">Cancel</a>@endif
        <button class="btn {{ $editing ? 'pri' : 'add' }}" type="submit"><svg class="ico"><use href="#i-{{ $editing ? 'check' : 'plus' }}"/></svg>{{ $editing ? 'Save changes' : 'Add' }}</button>
      </div>
    </div>
  </form>
  @endif
</div>
@endsection
