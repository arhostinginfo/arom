{{-- Follow-up & activity management (CRM changes): record a Call, Email or WhatsApp with the next action and target date.
  Details and the next action show and are mandatory as set in Admin > Page setup > Enquiry follow-up (22/10/2026). --}}
@php($df = config('nursery.date_format'))
@php($fps = app(\App\Services\PageSetupService::class)->form('enquiry-followup'))
<div class="sectcard" id="followups">
  <div class="sh"><h2>Follow-ups</h2><span class="sp"></span>
    @if ($enquiry->next_action)
      <span class="cap {{ $enquiry->isDue() ? 'duetxt' : 'muted' }}">Next: {{ $enquiry->next_action }} · {{ $enquiry->next_action_date?->format($df) }}@if ($enquiry->dueLabel()) @include('crm.partials.duetag', ['label' => $enquiry->dueLabel()])@endif</span>
    @endif
  </div>
  <div class="sb">
    @if ($errors->followup->any())
      <div class="alert err" role="alert"><svg class="ico"><use href="#i-alert"/></svg><div><b>The follow-up was not saved.</b><ul>@foreach ($errors->followup->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
    @endif
    @if ($canEdit)
      <form method="POST" action="{{ route('crm.enquiries.activities.store', $enquiry) }}" novalidate data-saving id="followupForm">
        @csrf
        <div class="fgrid">
          <div class="field">
            <label for="activity_type">Follow-up by<span class="req">*</span></label>
            <select class="inp {{ $errors->followup->has('activity_type') ? 'err' : '' }}" id="activity_type" name="activity_type" required>
              @foreach (\App\Models\Activity::TYPES as $t)<option value="{{ $t }}" @selected(old('activity_type', 'Call') === $t)>{{ $t === 'Email' ? 'E-mail' : $t }}</option>@endforeach
            </select>
            @include('crm.partials.ferrbag', ['bag' => 'followup', 'name' => 'activity_type'])
          </div>
          <div class="field">
            <label for="activity_date">Date<span class="req">*</span></label>
            <input class="inp num {{ $errors->followup->has('activity_date') ? 'err' : '' }}" id="activity_date" name="activity_date" value="{{ old('activity_date', today()->format($df)) }}" placeholder="dd/mm/yyyy" inputmode="numeric" required>
            @include('crm.partials.ferrbag', ['bag' => 'followup', 'name' => 'activity_date'])
          </div>
          @if ($fps->show('details'))
          <div class="field" style="grid-column:1/-1">
            <label for="details">Follow-up details{{ $fps->star('details') }}</label>
            <textarea class="inp {{ $errors->followup->has('details') ? 'err' : '' }}" id="details" name="details" rows="2" maxlength="2000" style="height:auto;padding-top:8px" @required($fps->req('details'))>{{ old('details') }}</textarea>
            @include('crm.partials.ferrbag', ['bag' => 'followup', 'name' => 'details'])
          </div>
          @endif
          @if ($fps->show('next_action'))
          <div class="field">
            <label for="f_next_action">Next action{{ $fps->star('next_action') }}</label>
            <input class="inp {{ $errors->followup->has('followup_action') ? 'err' : '' }}" id="f_next_action" name="followup_action" value="{{ old('followup_action') }}" maxlength="255" @required($fps->req('next_action'))>
            @include('crm.partials.ferrbag', ['bag' => 'followup', 'name' => 'followup_action'])
          </div>
          <div class="field">
            <label for="target_date">Target date{{ $fps->star('next_action') }}</label>
            <input class="inp num {{ $errors->followup->has('target_date') ? 'err' : '' }}" id="target_date" name="target_date" value="{{ old('target_date') }}" placeholder="dd/mm/yyyy" inputmode="numeric" @required($fps->req('next_action'))>
            @include('crm.partials.ferrbag', ['bag' => 'followup', 'name' => 'target_date'])
          </div>
          @endif
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:10px">
          @if ($fps->show('next_action'))<span class="cap muted">A next action replaces the enquiry's next action. When its date comes, it shows in red.</span>@endif
          <span class="sp" style="flex:1"></span>
          <button class="btn pri" type="submit"><svg class="ico"><use href="#i-check"/></svg>Save follow-up</button>
        </div>
      </form>
    @endif

    @if ($activities && $activities->count())
      <div class="tablecard" style="margin-top:14px">
        <table class="atable rtable">
          <thead><tr><th>Date</th><th>By</th>@if ($fps->show('details'))<th>Details</th>@endif @if ($fps->show('next_action'))<th>Next action</th><th>Target date</th>@endif<th>Recorded by</th></tr></thead>
          <tbody>
          @foreach ($activities as $a)
            <tr>
              <td data-label="Date" class="num">{{ $a->activity_date->format($df) }}</td>
              <td data-label="By"><span class="badge b-neu">{{ $a->activity_type === 'Email' ? 'E-mail' : $a->activity_type }}</span></td>
              @if ($fps->show('details'))<td data-label="Details" class="wide">{{ $a->details }}</td>@endif
              @if ($fps->show('next_action'))
              <td data-label="Next action">{{ $a->next_action }}</td>
              <td data-label="Target date" class="num">{{ $a->target_date?->format($df) }}</td>
              @endif
              <td data-label="Recorded by">{{ $a->creator?->name }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      @include('partials.pager', ['p' => $activities])
    @elseif (! $canEdit)
      <div class="empty">No follow-ups recorded yet.</div>
    @endif
  </div>
</div>
