{{--
  Quotation status and approval bar (CRM changes): Draft → Internal Approval → Approved → Sent → Customer
  Viewed/Feedback → Negotiation → Accepted / Rejected / Expired / Hold. Shows only the buttons this user
  may press at this status.
--}}
@php
  $u = auth()->user();
  $st = $quotation->status;
  $limit = $rule->approve_above_amount !== null ? \App\Support\Money::indian($rule->approve_above_amount, 2) : null;
@endphp
<div class="sectcard apcard">
  <div class="sb">
    <div class="apbar">
      <span class="cap muted">Status</span>
      <span class="badge {{ $st->badgeClass() }}">{{ $st->value }}</span>
      @if ($quotation->status_reason)<span class="cap" style="color:var(--error-700)">{{ $quotation->status_reason }}</span>@endif
      @if ($quotation->sent_at)<span class="cap muted">Sent on {{ $quotation->sent_at->format(config('nursery.datetime_format')) }}</span>@endif
      <span class="sp"></span>

      @if ($st === \App\Enums\QuotationStatus::Draft && $u->can('quotation.edit'))
        {{-- Sat 18/10/2026: no fixed "approval needed above" line; the button asks first, with the amount and the limit. --}}
        <form method="POST" action="{{ route('crm.quotations.submit', $quotation) }}" data-saving @if ($ask = \App\Support\ApprovalNotice::message($rule, (float) $quotation->grand_total, $quotation->currency?->code, 'quotation')) data-confirm-title="Submit {{ $quotation->activityLabel() }} for approval" data-confirm="{{ $ask }} Save any unsaved changes first." @endif>@csrf
          <button class="btn pri sm" type="submit"><svg class="ico"><use href="#i-check"/></svg>Submit for approval</button>
        </form>
      @endif

      @if ($st === \App\Enums\QuotationStatus::InternalApproval)
        @can('quotation-approval.edit')
          <button class="btn sec sm" type="button" data-open="#rejectLayer" style="color:var(--error-700)"><svg class="ico"><use href="#i-x"/></svg>Reject</button>
          <form method="POST" action="{{ route('crm.quotations.approve', $quotation) }}" data-saving>@csrf
            <button class="btn pri sm" type="submit"><svg class="ico"><use href="#i-check"/></svg>Approve</button>
          </form>
        @else
          <span class="cap muted">Waiting for an approver.</span>
        @endcan
      @endif

      @if (($st === \App\Enums\QuotationStatus::Approved || $st->isWithCustomer()) && $u->can('quotation.edit'))
        <button class="btn add sm" type="button" data-open="#emailLayer"><svg class="ico"><use href="#i-msg"/></svg>Email quotation</button>
        @if ($st === \App\Enums\QuotationStatus::Approved)
          <form method="POST" action="{{ route('crm.quotations.sent', $quotation) }}" data-confirm="Mark {{ $quotation->activityLabel() }} as sent (by WhatsApp, courier or by hand)?">@csrf
            <button class="btn add sm" type="submit"><svg class="ico"><use href="#i-check-c"/></svg>Mark as sent</button>
          </form>
        @endif
      @endif

      @if ($st === \App\Enums\QuotationStatus::Accepted && $u->can('customer-po.add') && $quotation->enquiry_id)
        <a class="btn add sm" href="{{ route('crm.customer-pos.create', ['quotation' => $quotation->id]) }}"><svg class="ico"><use href="#i-plus"/></svg>Enter PO / agreement</a>
      @endif
    </div>

    @if ($st->isWithCustomer() && $u->can('quotation.edit'))
      <form class="custstat" method="POST" action="{{ route('crm.quotations.customer-status', $quotation) }}" novalidate>
        @csrf
        <div class="field">
          <label for="cs_status">Customer status</label>
          <select class="inp @error('status') err @enderror" id="cs_status" name="status" data-reason-for="Rejected,Expired,Hold">
            @foreach ($customerStatuses as $s)<option @selected(old('status', $st->value) === $s)>{{ $s }}</option>@endforeach
          </select>
        </div>
        <div class="field grow" id="csReason">
          <label for="status_reason">Reason <span class="cap muted">(needed for Rejected, Expired and Hold)</span></label>
          <input class="inp @error('status_reason') err @enderror" id="status_reason" name="status_reason" value="{{ old('status_reason', $st->needsReason() ? $quotation->status_reason : '') }}" maxlength="255">
          @include('partials.ferr', ['name' => 'status_reason'])
        </div>
        <button class="btn sec" type="submit"><svg class="ico"><use href="#i-check"/></svg>Update status</button>
      </form>
    @endif
  </div>
</div>

@if ($st === \App\Enums\QuotationStatus::InternalApproval && $u->can('quotation-approval.edit'))
<div class="overlay" id="rejectLayer" hidden @if ($errors->has('remarks')) data-autoopen @endif>
  <div class="scrim" data-x></div>
  <form class="modal" role="dialog" aria-modal="true" aria-labelledby="rjT" method="POST" action="{{ route('crm.quotations.reject', $quotation) }}" novalidate data-saving>
    @csrf
    <div class="mh"><h3 id="rjT">Reject {{ $quotation->activityLabel() }}</h3></div>
    <div class="mb">
      <div class="field">
        <label for="rj_remarks">Reason<span class="req">*</span></label>
        <textarea class="inp @error('remarks') err @enderror" id="rj_remarks" name="remarks" rows="3" maxlength="500" required style="height:auto;padding-top:8px" @error('remarks') aria-invalid="true" aria-describedby="remarks-err" @enderror>{{ old('remarks') }}</textarea>
        @include('partials.ferr', ['name' => 'remarks'])
        <span class="hint">The quotation goes back to Draft with this reason, so it can be corrected and submitted again.</span>
      </div>
    </div>
    <div class="mf" style="display:flex;gap:8px;justify-content:flex-end;padding:12px 20px 18px">
      <button class="btn gh" type="button" data-x>Cancel</button>
      <button class="btn pri" type="submit" style="background:var(--error-600)">Reject</button>
    </div>
  </form>
</div>
@endif

@if (($st === \App\Enums\QuotationStatus::Approved || $st->isWithCustomer()) && $u->can('quotation.edit'))
<div class="overlay" id="emailLayer" hidden @if ($errors->hasAny(['to', 'cc', 'message'])) data-autoopen @endif>
  <div class="scrim" data-x></div>
  <form class="modal" role="dialog" aria-modal="true" aria-labelledby="emT" method="POST" action="{{ route('crm.quotations.email', $quotation) }}" novalidate data-saving>
    @csrf
    <div class="mh"><h3 id="emT">Email {{ $quotation->activityLabel() }}</h3></div>
    <div class="mb">
      <div class="field">
        <label for="em_to">To<span class="req">*</span></label>
        <input class="inp @error('to') err @enderror" id="em_to" name="to" type="email" inputmode="email" value="{{ old('to', $mailTo) }}" maxlength="191" required @error('to') aria-invalid="true" aria-describedby="to-err" @enderror>
        @include('partials.ferr', ['name' => 'to'])
      </div>
      <div class="field">
        <label for="em_cc">CC</label>
        <input class="inp @error('cc') err @enderror" id="em_cc" name="cc" value="{{ old('cc') }}" maxlength="500" placeholder="Separate several with commas" @error('cc') aria-invalid="true" aria-describedby="cc-err" @enderror>
        @include('partials.ferr', ['name' => 'cc'])
      </div>
      <div class="field">
        <label for="em_msg">Message</label>
        <textarea class="inp" id="em_msg" name="message" rows="3" maxlength="2000" style="height:auto;padding-top:8px">{{ old('message') }}</textarea>
      </div>
      <div class="small muted">{{ \App\Support\Modules::on('boq') ? 'The quotation PDF and every BOQ document are attached.' : 'The quotation PDF is attached.' }} An approved quotation becomes Sent.</div>
    </div>
    <div class="mf" style="display:flex;gap:8px;justify-content:flex-end;padding:12px 20px 18px">
      <button class="btn gh" type="button" data-x>Cancel</button>
      <button class="btn pri" type="submit"><svg class="ico"><use href="#i-msg"/></svg>Send email</button>
    </div>
  </form>
</div>
@endif
