@php
  $isNew = ! $user->exists;
  // Admin users only by an Admin (U8); other users only by someone holding all their rights (U9).
  $adminLocked = $manageLocked;
  $canSave = ! $adminLocked && ($isNew ? auth()->user()->can('users.add') : auth()->user()->can('users.edit'));
  $canDelete = ! $isNew && ! $adminLocked && auth()->user()->can('users.delete') && ! $user->is(auth()->user());
  $currentRole = old('role', $user->roles->first()?->name ?? '');
  $roleObj = $roles->firstWhere('name', $currentRole);
  $inherited = $roleObj ? $roleObj->permissions->pluck('name')->all() : [];
  $direct = old('permissions', $user->exists ? $user->permissions->pluck('name')->all() : []);
  $selectedUnits = array_map('intval', old('units', $user->exists ? $user->units->pluck('id')->all() : $units->pluck('id')->take(1)->all()));
  $title = $isNew ? 'Add user' : $user->name;
@endphp
@extends('layouts.app', ['title' => $title, 'crumbs' => [['Admin', route('admin.users.index')], ['Users list', route('admin.users.index')], [$isNew ? 'Add user' : 'Edit user', null]]])

@section('content')
<div class="pagehd">
  <a class="btn gh icon sm" href="{{ route('admin.users.index') }}" aria-label="Back to users list"><svg class="ico"><use href="#i-left"/></svg></a>
  <h1>{{ $isNew ? 'Add user' : 'Edit user' }}</h1>
  @unless ($isNew)<span class="badge {{ $user->isActive() ? 'b-ok' : 'b-neu' }}">{{ $user->status->value }}</span>@endunless
  <span class="sp"></span>
 
</div>

@if ($errors->any() && ! $errors->has('delete'))
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check the fields marked in red.</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif
@if ($adminLocked)
  <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>{{ $user->isAdmin() ? 'Only an Admin can change or delete an Admin user.' : 'This user has rights you do not have, so you can view the user but not change, reset the password of or delete them.' }}</span></div>
@elseif (! $canSave)
  <div class="alert info"><svg class="ico"><use href="#i-lock"/></svg><span>You can view this user. Your role cannot change users.</span></div>
@endif

<form method="POST" action="{{ $isNew ? route('admin.users.store') : route('admin.users.update', $user) }}" novalidate id="userForm" data-dirty-guard data-saving>
  @csrf
  @unless ($isNew) @method('PUT') @endunless
  <fieldset @disabled(! $canSave) style="border:0;padding:0;margin:0;min-width:0">

  <div class="sectcard">
    <div class="sh"><h2>User details</h2><span class="sp"></span><span class="cap muted">Fields marked <span class="req">*</span> are required</span></div>
    <div class="sb"><div class="fgrid">
      <div class="field">
        <label for="name">Name<span class="req">*</span></label>
        <input class="inp @error('name') err @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="100" autocomplete="off" required>
        @include('partials.ferr', ['name' => 'name'])
      </div>
      <div class="field">
        <label for="email">Login email<span class="req">*</span></label>
        <input class="inp @error('email') err @enderror" id="email" name="email" type="email" inputmode="email" value="{{ old('email', $user->email) }}" maxlength="191" autocomplete="off" required>
        <span class="hint">The user signs in with this. It must be unique.</span>
        @include('partials.ferr', ['name' => 'email'])
      </div>
      <div class="field">
        <label for="mobile">Mobile<span class="req">*</span></label>
        <input class="inp @error('mobile') err @enderror" id="mobile" name="mobile" inputmode="tel" value="{{ old('mobile', $user->exists ? $user->mobileDisplay() : '') }}" placeholder="+91 98220 00001" required>
        <span class="hint">10 digit Indian mobile number</span>
        @include('partials.ferr', ['name' => 'mobile'])
      </div>
      <div class="field">
        {{-- Status as a drop-down instead of the segmented radio buttons (02/11/2026). --}}
        <label for="status">Status<span class="req">*</span></label>
        <select class="inp @error('status') err @enderror" id="status" name="status" aria-required="true">
          @foreach (['Active', 'Inactive'] as $s)
            <option value="{{ $s }}" @selected(old('status', $user->status?->value ?? 'Active') === $s)>{{ $s }}</option>
          @endforeach
        </select>
        <span class="hint">Inactive users cannot sign in.</span>
        @include('partials.ferr', ['name' => 'status'])
      </div>
    </div></div>
  </div>

  <div class="sectcard">
    <div class="sh"><h2>Password</h2><span class="sp"></span>@unless ($isNew)<span class="cap muted">Leave blank to keep the current password</span>@endunless</div>
    <div class="sb"><div class="fgrid">
      <div class="field">
        <label for="password">{{ $isNew ? 'Password' : 'New password' }}@if ($isNew)<span class="req">*</span>@endif</label>
        <input class="inp @error('password') err @enderror" id="password" name="password" type="password" autocomplete="new-password" @required($isNew)>
        <span class="hint">At least 8 characters with letters and numbers</span>
        @include('partials.ferr', ['name' => 'password'])
      </div>
      <div class="field">
        <label for="password_confirmation">Confirm password @if ($isNew)<span class="req">*</span>@endif</label>
        <input class="inp" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required($isNew)>
      </div>
    </div></div>
  </div>

  <div class="sectcard">
    <div class="sh"><h2>Role and units</h2><span class="sp"></span></div>
    <div class="sb"><div class="fgrid">
      <div class="field">
        <label for="role">Role<span class="req">*</span></label>
        <select class="inp @error('role') err @enderror" id="role" name="role" required>
          <option value="">Select</option>
          @foreach ($offeredRoles as $r)
            <option value="{{ $r->name }}" @selected($currentRole === $r->name)>{{ $r->name }}</option>
          @endforeach
        </select>
        <span class="hint">The role decides the menu, pages and reports. Manage roles in Access rights.</span>
        @include('partials.ferr', ['name' => 'role'])
      </div>
      <div class="field" style="grid-column:span 2">
        <span style="font-size:13px;font-weight:500">Units allowed<span class="req">*</span></span>
        <div class="checks">
          @foreach ($units as $unit)
            <label class="chk"><input type="checkbox" name="units[]" value="{{ $unit->id }}" @checked(in_array($unit->id, $selectedUnits, true))>{{ $unit->name }}</label>
          @endforeach
        </div>
        @include('partials.ferr', ['name' => 'units'])
      </div>
    </div></div>
  </div>

  <div class="sectcard">
    <div class="sh"><h2>Permissions for this user</h2><span class="sp"></span></div>
    <div class="sb">
      <div class="legend">
        <span><input type="checkbox" checked disabled aria-hidden="true"> Given by the role</span>
        <span><input type="checkbox" checked aria-hidden="true" tabindex="-1" onclick="return false"> Extra permission for this user only</span>
      </div>
      <p class="small muted" id="adminNote" @if ($currentRole !== \App\Support\Roles::ADMIN) hidden @endif>Admin has every permission, so there is nothing extra to give.</p>
      @include('partials.matrix', ['checked' => $direct, 'inherited' => $currentRole === \App\Support\Roles::ADMIN ? \App\Support\Access::all() : $inherited, 'locked' => false])
    </div>
  </div>

  </fieldset>

  <div class="formfoot">
    @if ($canDelete)
      <button class="btn sec" type="submit" form="delForm" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Delete user</button>
    @endif
    <span class="sp"></span>
    <a class="btn gh" href="{{ route('admin.users.index') }}">{{ $canSave ? 'Cancel' : 'Back' }}</a>
    @if ($canSave)<button class="btn pri" type="submit" form="userForm"><svg class="ico"><use href="#i-check"/></svg>{{ $isNew ? 'Create user' : 'Save user' }}</button>@endif
  </div>
</form>

@if ($canDelete)
  <form id="delForm" method="POST" action="{{ route('admin.users.destroy', $user) }}" data-confirm="Delete {{ $user->name }}? They will no longer be able to sign in. The activity log keeps their history.">@csrf @method('DELETE')</form>
@endif

@unless ($isNew)
<div class="sectcard">
  <div class="sh"><h2>Recent changes</h2><span class="sp"></span>
    <span class="cap muted">Added by {{ $user->creator?->name ?? 'setup' }} on {{ $user->created_at?->format(config('nursery.date_format')) }} · Updated by {{ $user->updater?->name ?? 'setup' }} on {{ $user->updated_at?->format(config('nursery.date_format')) }}</span>
  </div>
  <div class="sb">
    @forelse ($history as $h)
      <div class="timeline"><div class="ev"><span></span><div><b>{{ $h->actionLabel() }}</b> · {{ $h->description }}<div class="cap muted">{{ $h->user_name ?? 'System' }} · {{ $h->created_at->format(config('nursery.datetime_format')) }}@can('activity-log.view') · <a href="{{ route('admin.activity.show', $h) }}">Details</a>@endcan</div></div></div></div>
    @empty
      <p class="small muted">No changes logged yet.</p>
    @endforelse
  </div>
</div>
@endunless
@endsection

@push('scripts')
<script>
(function(){
  /* When the role changes, show which permissions it already gives (ticked and locked). */
  var rolePerms=@json($roles->mapWithKeys(fn ($r) => [$r->name => $r->name === \App\Support\Roles::ADMIN ? \App\Support\Access::all() : $r->permissions->pluck('name')->values()]));
  var sel=document.getElementById('role'),note=document.getElementById('adminNote');
  sel.addEventListener('change',function(){
    var give=rolePerms[sel.value]||[];
    note.hidden=sel.value!==@json(\App\Support\Roles::ADMIN);
    document.querySelectorAll('[data-matrix] input[name="permissions[]"]').forEach(function(c){
      var inRole=give.indexOf(c.value)>-1;
      if(inRole){c.checked=true;c.disabled=true;c.dataset.rolePerm='1'}
      else if(c.dataset.rolePerm){c.checked=false;c.disabled=false;delete c.dataset.rolePerm}
    });
  });
})();
</script>
@endpush
