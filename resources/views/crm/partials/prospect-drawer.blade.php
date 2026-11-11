{{-- Add prospect drawer (CRM H9): saves a prospect without leaving the page and selects it in #prospect_id. --}}
<div class="overlay" id="prospectLayer" hidden>
  <div class="scrim" data-x></div>
  <form class="drawer" role="dialog" aria-modal="true" aria-labelledby="pdT" id="prospectQuick" action="{{ route('crm.prospects.quick') }}" novalidate>
    <div class="dh"><h3 id="pdT">Add prospect</h3><span style="flex:1"></span><button class="btn gh icon sm" type="button" data-x aria-label="Close"><svg class="ico"><use href="#i-x"/></svg></button></div>
    <div class="db">
      <div class="alert err" id="pdErr" hidden role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>Check these fields.</b><ul></ul></div></div>
      @include('crm.prospects.fields', ['p' => new \App\Models\Prospect(), 'px' => 'pd_'])
    </div>
    <div class="df"><button class="btn gh" type="button" data-x>Cancel</button><button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>Save prospect</button></div>
  </form>
</div>
