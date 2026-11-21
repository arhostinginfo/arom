{{-- An AMC employee's photo (private disk, through the app), or their initials. Params: $e (AmcEmployee or null), optional $lg. --}}
@if ($e && $e->photo_path)
  <img class="amcphoto{{ ! empty($lg) ? ' lg' : '' }}" src="{{ route('amc.employees.photo.show', $e) }}?v={{ $e->updated_at?->timestamp }}" alt="Photo of {{ $e->name }}" loading="lazy">
@else
  <span class="amcphoto ph{{ ! empty($lg) ? ' lg' : '' }}" aria-hidden="true">{{ $e ? mb_strtoupper(mb_substr($e->name, 0, 1)) : '–' }}</span>
@endif
