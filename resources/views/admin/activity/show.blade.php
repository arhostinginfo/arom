@extends('layouts.app', ['title' => $log->actionLabel(), 'crumbs' => [['Admin', route('admin.users.index')], ['Activity log', route('admin.activity.index')], [$log->actionLabel(), null]]])

@php
  $c = $log->changes ?? [];
  $fields = $c['fields'] ?? null;
  $lists = array_intersect_key($c, array_flip(['added', 'removed']));
  $pairs = array_filter(array_diff_key($c, array_flip(['fields', 'added', 'removed'])), fn ($v) => is_array($v) && array_key_exists('old', $v));
  $subject = $log->subject_type ? $log->subject : null;
  $subjectUrl = match (true) {
      $subject instanceof \App\Models\User && ! $subject->trashed() => route('admin.users.edit', $subject),
      $subject instanceof \Spatie\Permission\Models\Role => route('admin.roles.edit', $subject),
      default => null,
  };
@endphp

@section('content')
<div class="pagehd">
  <a class="btn gh icon sm" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('admin.activity.index') }}" aria-label="Back"><svg class="ico"><use href="#i-left"/></svg></a>
  <h1>{{ $log->actionLabel() }}</h1>
  <span class="badge {{ $log->badgeClass() }}">{{ $log->action }}</span>
</div>

<div class="sectcard">
  <div class="sh"><h2>What happened</h2></div>
  <div class="sb">
    <dl class="kv">
      <dt>Description</dt><dd>{{ $log->description }}</dd>
      <dt>Date and time</dt><dd class="num">{{ $log->created_at->format('d/m/Y H:i:s') }}</dd>
      <dt>Done by</dt><dd>{{ $log->user_name ?? 'Not signed in' }}</dd>
      <dt>Record</dt><dd>@if ($log->subject_type){{ ucfirst($log->subject_type) }} · @if ($subjectUrl)<a href="{{ $subjectUrl }}">{{ $log->subject_label }}</a>@else{{ $log->subject_label }} <span class="cap muted">(no longer exists)</span>@endif @else — @endif</dd>
      <dt>IP address</dt><dd class="num">{{ $log->ip_address ?? '—' }}</dd>
      <dt>Browser</dt><dd class="cap muted">{{ $log->user_agent ?? '—' }}</dd>
    </dl>
  </div>
</div>

@if ($pairs)
<div class="sectcard">
  <div class="sh"><h2>Changes</h2></div>
  <div class="sb" style="padding:0">
    <table class="chg"><thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead><tbody>
    @foreach ($pairs as $field => $v)
      <tr><td>{{ ucfirst($field) }}</td><td class="old">{{ $v['old'] ?? '—' }}</td><td class="new">{{ $v['new'] ?? '—' }}</td></tr>
    @endforeach
    </tbody></table>
  </div>
</div>
@endif

@if ($fields)
<div class="sectcard">
  <div class="sh"><h2>Values</h2></div>
  <div class="sb"><dl class="kv">@foreach ($fields as $k => $v)<dt>{{ $k }}</dt><dd>{{ $v === '' || $v === null ? '—' : $v }}</dd>@endforeach</dl></div>
</div>
@endif

@foreach ($lists as $kind => $items)
<div class="sectcard">
  <div class="sh"><h2>{{ $kind === 'added' ? 'Permissions given' : 'Permissions taken away' }}</h2><span class="badge {{ $kind === 'added' ? 'b-ok' : 'b-err' }}">{{ count($items) }}</span></div>
  <div class="sb"><div class="pilllist">@foreach ($items as $i)<span class="badge {{ $kind === 'added' ? 'b-ok' : 'b-err' }}">{{ $i }}</span>@endforeach</div></div>
</div>
@endforeach
@endsection
