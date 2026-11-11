@if ($history->isNotEmpty())
<div class="sectcard">
  <div class="sh"><h2>Recent changes</h2></div>
  <div class="sb">
    @foreach ($history as $h)
      <div class="timeline"><div class="ev"><span></span><div><b>{{ $h->actionLabel() }}</b> · {{ $h->description }}<div class="cap muted">{{ $h->user_name ?? 'System' }} · {{ $h->created_at->format(config('nursery.datetime_format')) }}@can('activity-log.view') · <a href="{{ route('admin.activity.show', $h) }}">Details</a>@endcan</div></div></div></div>
    @endforeach
  </div>
</div>
@endif
