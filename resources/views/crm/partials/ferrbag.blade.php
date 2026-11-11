@if ($errors->{$bag}->has($name))<span class="ferr" id="{{ $name }}-err"><svg class="ico"><use href="#i-alert"/></svg>{{ $errors->{$bag}->first($name) }}</span>@endif
