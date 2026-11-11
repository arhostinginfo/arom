{{-- Plain-text email, so values are printed as typed ("Shah & Sons", not "Shah &amp; Sons"): there is no HTML to protect (code review S6). --}}
Dear {!! $quotation->contact_name ?: $quotation->customerName() !!},

{!! $note ?: 'Please find attached our quotation '.$quotation->quotation_no.' (Rev '.$quotation->revision_no.').' !!}

Quotation no: {!! $quotation->quotation_no !!} Rev {!! $quotation->revision_no !!}
Date: {!! $quotation->quotation_date?->format(config('nursery.date_format')) !!}
@if ($quotation->valid_until)
Valid until: {!! $quotation->valid_until->format(config('nursery.date_format')) !!}
@endif
Total: {!! $quotation->currency?->code !!} {!! \App\Support\Money::indian($quotation->grand_total, 2) !!}

Regards,
{!! $quotation->salesPerson?->name !!}
{!! $quotation->unit?->name !!}
