{{--
  Import / export as an icon button (Sat 18/10/2026): no text, each kind in its own colour, with a tooltip and an
  accessible name. Params: kind (import | excel | csv | pdf | print), label (the action, e.g. "Download CSV"), and one of
  href (a download link), open (a dialog to open) or submit (form button: ['name' => …, 'value' => …]).
--}}
@php($icon = ['import' => 'upload', 'excel' => 'sheet', 'csv' => 'download', 'pdf' => 'pdf', 'print' => 'print'][$kind])
@if (isset($open))
  <button class="btn iob iob-{{ $kind }}" type="button" data-open="{{ $open }}" aria-label="{{ $label }}" title="{{ $label }}"><svg class="ico" aria-hidden="true"><use href="#i-{{ $icon }}"/></svg></button>
@elseif (isset($submit))
  <button class="btn iob iob-{{ $kind }}" type="submit" name="{{ $submit['name'] }}" value="{{ $submit['value'] }}" aria-label="{{ $label }}" title="{{ $label }}"><svg class="ico" aria-hidden="true"><use href="#i-{{ $icon }}"/></svg></button>
@else
  <a class="btn iob iob-{{ $kind }}" href="{{ $href }}" @if ($kind !== 'print') download @endif aria-label="{{ $label }}" title="{{ $label }}"><svg class="ico" aria-hidden="true"><use href="#i-{{ $icon }}"/></svg></a>
@endif
