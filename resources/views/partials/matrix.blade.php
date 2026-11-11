{{-- Access rights grid: one row per page from the workbook, View / Add / Edit / Delete (decision D19).
     $checked: granted here. $inherited: given by the role (shown ticked, locked). $locked: whole grid read-only. --}}
@php($actions = config('nursery.actions'))
<div class="tablecard" style="max-height:62vh;overflow:auto">
<table class="pmatrix" data-matrix>
  <thead><tr><th>Page</th>@foreach ($actions as $a => $label)<th class="c">{{ $label }}</th>@endforeach</tr></thead>
  <tbody>
  {{-- Pages of an optional part that is switched off are left out; their rights are kept (Access::keepHidden). --}}
  @foreach (\App\Support\Access::pages() as $group => $pages)
    <tr class="grp"><td colspan="{{ count($actions) + 1 }}">{{ $group }}@unless ($locked)<button type="button" class="btn lnk sm" data-all="{{ \Illuminate\Support\Str::slug($group) }}">Tick all</button>@endunless</td></tr>
    @foreach ($pages as $key => $page)
      <tr data-row="{{ $key }}" data-group="{{ \Illuminate\Support\Str::slug($group) }}">
        <td>{{ \App\Support\Access::pageLabel($page) }}@isset($page['note'])<span class="cap muted" style="display:block">{{ $page['note'] }}</span>@endisset</td>
        @foreach ($actions as $a => $label)
          @php($perm = "$key.$a")
          <td class="c">
          @if (in_array($a, $page['actions'], true))
            @php($fromRole = in_array($perm, $inherited, true))
            <input type="checkbox" name="permissions[]" value="{{ $perm }}" data-act="{{ $a }}" aria-label="{{ \App\Support\Access::pageLabel($page) }} {{ $label }}"
              @checked($locked || $fromRole || in_array($perm, $checked, true)) @disabled($locked || $fromRole) @if ($fromRole) data-role-perm @endif>
          @else
            <span class="na" aria-label="Not used">–</span>
          @endif
          </td>
        @endforeach
      </tr>
    @endforeach
  @endforeach
  </tbody>
</table>
</div>
@once
@push('scripts')
<script>
(function(){
  /* Add, Edit and Delete need View; removing View removes the rest of the row. */
  document.querySelectorAll('[data-matrix]').forEach(function(m){
    m.addEventListener('change',function(e){
      var c=e.target;if(c.type!=='checkbox')return;
      var row=c.closest('tr'),view=row.querySelector('[data-act=view]');
      if(c.dataset.act!=='view'&&c.checked&&view&&!view.disabled)view.checked=true;
      if(c.dataset.act==='view'&&!c.checked)row.querySelectorAll('input:not([data-act=view])').forEach(function(x){if(!x.disabled)x.checked=false});
    });
    m.querySelectorAll('[data-all]').forEach(function(b){b.addEventListener('click',function(){
      var boxes=m.querySelectorAll('tr[data-group="'+b.dataset.all+'"] input:not(:disabled)'),all=[].every.call(boxes,function(x){return x.checked});
      boxes.forEach(function(x){x.checked=!all});b.textContent=all?'Tick all':'Clear all';
    })});
  });
})();
</script>
@endpush
@endonce
