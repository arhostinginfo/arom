{{-- Form errors at the top of an AMC page; a refused delete shows on its own. --}}
@if ($errors->any() && ! $errors->has('delete'))
  <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>{{ $errors->has('file') ? 'The file was not imported. Nothing was saved.' : 'Check the fields marked in red.' }}</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif
@error('delete')<div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><span>{{ $message }}</span></div>@enderror
