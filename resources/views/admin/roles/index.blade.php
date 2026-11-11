@extends('layouts.app', ['title' => 'Access rights', 'crumbs' => [['Admin', route('admin.users.index')], ['Access rights', null]]])

@section('content')
<div class="pagehd">
  <h1>Access rights</h1>
  <span class="badge b-neu">{{ $roles->total() }} {{ \Illuminate\Support\Str::plural('role', $roles->total()) }}</span>
  <span class="sp"></span>
 
  @can('access-rights.add')
    <a class="btn add" href="{{ route('admin.roles.create') }}"><svg class="ico"><use href="#i-plus"/></svg>Create role</a>
  @endcan
</div>
<div class="ruleh" style="margin-bottom:14px"><svg class="ico"><use href="#i-info"/></svg><span>Each role decides which pages and reports its users see and what they may do there. Users see only their role's reports; Admin sees all (OTHER POINTS A17).</span></div>

<div class="rolecards">
  @foreach ($roles as $r)
    @php($share = $r->name === \App\Support\Roles::ADMIN ? 100 : (int) round($r->permissions_count / max(1, $total) * 100))
    <a class="rolecard" href="{{ route('admin.roles.edit', $r) }}">
      <h3><svg class="ico" style="color:var(--primary-600)"><use href="#i-shield"/></svg>{{ $r->name }}@if ($r->name === \App\Support\Roles::ADMIN)<span class="badge b-pri">All access</span>@endif</h3>
      <div class="stats">
        <span><b>{{ $r->users_count }}</b>{{ \Illuminate\Support\Str::plural('user', $r->users_count) }}</span>
        <span><b>{{ $r->name === \App\Support\Roles::ADMIN ? $total : $r->permissions_count }}</b>of {{ $total }} permissions</span>
      </div>
      <div class="meter" aria-hidden="true"><i style="width:{{ $share }}%"></i></div>
      <span class="cap muted">Updated {{ $r->updated_at?->format(config('nursery.datetime_format')) }}</span>
    </a>
  @endforeach
</div>
@include('partials.pager', ['p' => $roles])
@endsection
