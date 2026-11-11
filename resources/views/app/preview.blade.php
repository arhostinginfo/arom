@extends('layouts.app', ['title' => $title, 'preview' => ['page' => $page, 'arg' => $arg]])

@section('content')
  <noscript><div class="alert info">This sample screen needs JavaScript.</div></noscript>
@endsection

@push('scripts')
@php
  $u = auth()->user();
  $nurseryCfg = [
    'route' => $page,
    'arg' => $arg,
    'base' => url('/app'),
    'links' => array_filter([
      'users' => $u->can('users.view') ? route('admin.users.index') : null,
      'access' => $u->can('access-rights.view') ? route('admin.roles.index') : null,
    ]),
    'role' => $u->isAdmin() ? 'Admin' : $u->roleName(),
    'userName' => $u->name,
    'reportGroups' => \App\Support\Menu::reportGroups($u),
    'mods' => collect(\App\Support\Menu::build($u, request(), ['page' => $page, 'arg' => $arg]))->flatten(1)->pluck('key')->all(),
  ];
@endphp
<script>
window.NURSERY_PREVIEW = true;
window.NURSERY = @json($nurseryCfg);
</script>
<script src="{{ asset('js/preview.js') }}?v={{ filemtime(public_path('js/preview.js')) }}"></script>
@endpush
