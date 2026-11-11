@php
  $isNew = ! $role->exists;
  $canSave = $isNew ? auth()->user()->can('access-rights.add') : auth()->user()->can('access-rights.edit');
  $title = $isNew ? 'Create role' : 'Role · '.$role->name;
@endphp
@extends('layouts.app', ['title' => $title, 'crumbs' => [['Admin', route('admin.users.index')], ['Access rights', route('admin.roles.index')], [$isNew ? 'Create role' : $role->name, null]]])

@section('content')
<div class="pagehd">
  <a class="btn gh icon sm" href="{{ route('admin.roles.index') }}" aria-label="Back to access rights"><svg class="ico"><use href="#i-left"/></svg></a>
  <h1>{{ $isNew ? 'Create role' : $role->name }}</h1>
  @unless ($isNew)<span class="badge b-neu">{{ $userCount }} {{ \Illuminate\Support\Str::plural('user', $userCount) }}</span>@endunless
  <span class="sp"></span>
 
</div>

@if ($errors->any() && ! $errors->has('delete'))
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check the fields marked in red.</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif
@if ($locked)
  <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>Admin always has every permission and cannot be renamed or deleted, so there is always someone who can manage users.</span></div>
@elseif ($fixedName && $canSave)
  <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>Sales keeps its name and cannot be deleted, because quotations pick their sales person from it. You can still change its permissions.</span></div>
@endif
@if (! $locked && ! $canSave)
  <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>You can view this role. Your role cannot change access rights.</span></div>
@endif

<form method="POST" action="{{ $isNew ? route('admin.roles.store') : route('admin.roles.update', $role) }}" id="roleForm" data-dirty-guard data-saving novalidate>
  @csrf
  @unless ($isNew) @method('PUT') @endunless
  <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0">
  <div class="sectcard">
    <div class="sh"><h2>Role</h2></div>
    <div class="sb"><div class="fgrid">
      <div class="field">
        <label for="name">Role name<span class="req">*</span></label>
        <input class="inp @error('name') err @enderror" id="name" name="name" value="{{ old('name', $role->name) }}" maxlength="50" @readonly($fixedName) required>
        <span class="hint">For example Sales, Purchase head, Quality</span>
        @include('partials.ferr', ['name' => 'name'])
      </div>
    </div></div>
  </div>

  <div class="sectcard">
    <div class="sh"><h2>Permissions</h2><span class="sp"></span><span class="cap muted">Add, Edit or Delete also gives View</span></div>
    <div class="sb">
      @include('partials.matrix', ['checked' => old('permissions', $granted), 'inherited' => [], 'locked' => $locked || ! $canSave])
      @if (! $isNew && $userCount && ! $locked)<p class="small muted">Changes apply to the {{ $userCount }} {{ \Illuminate\Support\Str::plural('user', $userCount) }} with this role at their next click.</p>@endif
    </div>
  </div>
  </fieldset>

  <div class="formfoot">
    @if (! $isNew && ! $fixedName && auth()->user()->can('access-rights.delete'))
      <button class="btn sec" type="submit" form="delForm" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Delete role</button>
    @endif
    <span class="sp"></span>
    <a class="btn gh" href="{{ route('admin.roles.index') }}">{{ $canSave && ! $locked ? 'Cancel' : 'Back' }}</a>
    @if ($canSave && ! $locked)<button class="btn pri" type="submit" form="roleForm"><svg class="ico"><use href="#i-check"/></svg>{{ $isNew ? 'Create role' : 'Save role' }}</button>@endif
  </div>
</form>

@if (! $isNew && ! $fixedName && auth()->user()->can('access-rights.delete'))
  <form id="delForm" method="POST" action="{{ route('admin.roles.destroy', $role) }}" data-confirm="Delete the role {{ $role->name }}?">@csrf @method('DELETE')</form>
@endif

@unless ($isNew)
<div class="sectcard">
  <div class="sh"><h2>Recent changes</h2></div>
  <div class="sb">
    @forelse ($history as $h)
      <div class="timeline"><div class="ev"><span></span><div><b>{{ $h->actionLabel() }}</b> · {{ $h->description }}<div class="cap muted">{{ $h->user_name ?? 'System' }} · {{ $h->created_at->format(config('nursery.datetime_format')) }}@can('activity-log.view') · <a href="{{ route('admin.activity.show', $h) }}">Details</a>@endcan</div></div></div></div>
    @empty
      <p class="small muted">No changes logged yet. The default roles were created at setup.</p>
    @endforelse
  </div>
</div>
@endunless
@endsection
