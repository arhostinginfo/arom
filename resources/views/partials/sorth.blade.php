{{-- Sortable column header: sorts on the server (rule P1). Params: label, key, sort, dir. --}}
@php($on = $sort === $key)
@php($next = $on && $dir === 'asc' ? 'desc' : 'asc')
<th @isset($cls) class="{{ $cls }}" @endisset aria-sort="{{ $on ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none' }}"><a class="sorth" href="{{ request()->fullUrlWithQuery(['sort' => $key, 'dir' => $next, 'page' => null]) }}">{{ $label }}<svg class="ico" aria-hidden="true" style="width:12px;{{ $on ? '' : 'opacity:.35;' }}{{ $on && $dir === 'asc' ? 'transform:rotate(180deg)' : '' }}"><use href="#i-down"/></svg></a></th>
