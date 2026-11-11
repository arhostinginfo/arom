{{-- Added by / on and Updated by / on (OTHER POINTS A24). --}}
@if ($m->exists)
  <div class="audit">
    <span>Added by <b>{{ $m->creator?->name ?? 'setup' }}</b> on <b class="num">{{ $m->created_at?->format(config('nursery.datetime_format')) }}</b></span>
    <span>Updated by <b>{{ $m->updater?->name ?? 'setup' }}</b> on <b class="num">{{ $m->updated_at?->format(config('nursery.datetime_format')) }}</b></span>
  </div>
@else
  <div class="audit"><span>Added by and added on are filled when the entry is saved.</span></div>
@endif
