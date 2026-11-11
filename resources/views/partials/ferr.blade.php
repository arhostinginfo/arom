@error($name, $bag ?? 'default')<span class="ferr" id="{{ ($bag ?? '') ? $bag.'-' : '' }}{{ $name }}-err"><svg class="ico"><use href="#i-alert"/></svg>{{ $message }}</span>@enderror
