<?php

namespace App\Http\Requests\Sales;

use App\Models\SalesInvoice;
use App\Services\PageSetupService;
use App\Support\WorkContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add / edit a tax invoice, credit note or debit note. Always mandatory (S4): Party name, Invoice type, Invoice date,
 * Shipping address and at least one item with a quantity; PO no, transport and P&F as set in Admin › Page setup.
 * The invoice date must not be after today, and must be in the financial year on the top bar for a new invoice, or
 * in the invoice's own year (the year of its number) for an edit (database review SL-M2). The PO, quantity and rate rules
 * are checked by SalesInvoiceService under row locks. Errors go to the "invoice" bag of the Sales page.
 */
class SalesInvoiceRequest extends FormRequest
{
    protected $errorBag = 'invoice';

    public const LINE_FIELDS = ['sales_order_line_id', 'item_id', 'quantity', 'rate'];

    public function authorize(): bool
    {
        return $this->user()->can($this->route('invoice') ? 'tax-invoice.edit' : 'tax-invoice.add');
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->has('lines_json') ? json_decode((string) $this->input('lines_json'), true) : $this->input('lines', []);
        $clean = [];
        foreach (is_array($lines) ? $lines : [] as $l) {
            if (! is_array($l)) {
                continue;
            }
            $l = array_map(fn ($v) => is_string($v) ? trim(str_replace([','], '', $v)) : $v, array_intersect_key($l, array_flip(self::LINE_FIELDS)));
            // (03/11/2026, E2E test) Against a PO the page lists every part of the order with an empty quantity and no
            // remove button, so a part left empty is not billed (or credited) this time; before, it blocked the save.
            if (($l['sales_order_line_id'] ?? '') !== '' && ($l['quantity'] ?? '') === '') {
                continue;
            }
            if (($l['item_id'] ?? '') !== '' || ($l['quantity'] ?? '') !== '' || ($l['sales_order_line_id'] ?? '') !== '') {
                $clean[] = $l;
            }
        }
        $num = fn ($f) => $this->has($f) ? (str_replace([',', ' '], '', (string) $this->input($f)) ?: null) : null;
        $merge = ['lines' => $clean, '_form' => 'invoice', 'vehicle_no' => strtoupper(trim((string) $this->input('vehicle_no'))) ?: null];
        foreach (['pf_charges', 'distance_km'] as $f) {
            if ($this->has($f)) {
                $merge[$f] = $num($f);
            }
        }
        // The type of a saved invoice never changes (its number is in that type's series).
        if ($inv = $this->route('invoice')) {
            $merge['invoice_type'] = $inv->invoice_type;
        }
        // (02/11/2026) Client sheet: the invoice date is filled in automatically. Only an Admin can change it (a past
        // date in the year, rule S-6 below); for anyone else a new invoice is dated today and a saved one keeps its date,
        // whatever was sent.
        if (! $this->user()?->isAdmin()) {
            $merge['invoice_date'] = $inv?->invoice_date?->format('d/m/Y') ?? today()->format('d/m/Y');
        }
        $this->merge($merge);
    }

    public function rules(): array
    {
        $current = $this->route('invoice');

        return app(PageSetupService::class)->rules('tax-invoice', [
            'invoice_type' => ['required', Rule::in(SalesInvoice::TYPES)],
            'invoice_date' => ['required', 'date_format:d/m/Y'],
            'party_id' => ['required', 'integer', Rule::exists('parties', 'id')->whereNull('deleted_at')->whereIn('type', ['Customer', 'Both'])
                ->where(fn ($q) => $q->where('status', 'Active')->when($current?->party_id, fn ($w, $id) => $w->orWhere('id', $id)))],
            'sales_order_id' => ['nullable', 'integer'],
            'shipping_address' => ['required', 'string', 'max:1000'],
            'mode_of_transport' => ['nullable', Rule::in(SalesInvoice::MODES)],
            'transporter_id' => ['nullable', 'integer', Rule::exists('transporters', 'id')->where(fn ($q) => $q->where('is_active', true)
                ->when($current?->transporter_id, fn ($w, $id) => $w->orWhere('id', $id)))],
            'vehicle_no' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9 -]+$/'],
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'pf_charges' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.sales_order_line_id' => ['nullable', 'integer'],
            'lines.*.item_id' => ['required_without:lines.*.sales_order_line_id', 'nullable', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
        ]);
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $date = $this->input('invoice_date');
            if ($date && ! $v->errors()->has('invoice_date') && preg_match('#^\d{2}/\d{2}/\d{4}$#', $date)) {
                $d = Carbon::createFromFormat('d/m/Y', $date)->startOfDay();
                $current = $this->route('invoice');
                $year = $current?->financialYear ?? WorkContext::financialYear();
                if ($d->gt(today())) {
                    $v->errors()->add('invoice_date', 'The invoice date cannot be in the future.');
                } elseif ($d->lt($year->starts_on) || $d->gt($year->ends_on)) {
                    $v->errors()->add('invoice_date', $current
                        ? $current->invoice_no.' is numbered in '.$year->label().', so its date must be between '.$year->starts_on->format('d/m/Y').' and '.$year->ends_on->format('d/m/Y').'.'
                        : 'The invoice date must be in the financial year '.$year->label().' (top bar).');
                }
            }
            // Without a PO, every line needs its part; with one, the lines come from the PO.
            foreach ((array) $this->input('lines', []) as $i => $l) {
                if (! $this->input('sales_order_id') && empty($l['item_id']) && ! $v->errors()->has("lines.$i.item_id")) {
                    $v->errors()->add("lines.$i.item_id", 'Item '.($i + 1).': choose the item.');
                }
            }
        }];
    }

    /** Fields the service reads; the lines are kept as sent (validated above). */
    public function payload(): array
    {
        $data = $this->validated();
        // PO no hidden in Admin › Page setup: an existing invoice keeps its PO.
        $data['sales_order_id'] = array_key_exists('sales_order_id', $data) ? ($data['sales_order_id'] ?: null) : $this->route('invoice')?->sales_order_id;
        $data['pf_charges'] = $data['pf_charges'] ?? ($this->route('invoice')?->pf_charges ?? 0);

        return $data;
    }

    public function messages(): array
    {
        return [
            'party_id.required' => 'Choose the party (customer).',
            'party_id.exists' => 'Choose an active customer.',
            'invoice_type.in' => 'Choose Sales, Credit or Debit.',
            'lines.required' => 'Add at least one item with a quantity.',
            'lines.*.quantity.required' => 'Item :position: enter the quantity.',
            'lines.*.quantity.numeric' => 'Item :position: enter the quantity as a number.',
            'lines.*.quantity.gt' => 'Item :position: the quantity must be more than 0.',
            'lines.*.rate.numeric' => 'Item :position: enter the rate as a number.',
            'lines.*.item_id.required_without' => 'Item :position: choose the item.',
            'vehicle_no.regex' => 'Use letters, numbers, spaces and "-" only, for example MH 14 AB 1234.',
            '*.date_format' => 'Enter the date as dd/mm/yyyy.',
        ];
    }

    public function attributes(): array
    {
        return ['party_id' => 'party name', 'invoice_type' => 'invoice type', 'invoice_date' => 'invoice date', 'sales_order_id' => 'PO no',
            'shipping_address' => 'shipping address', 'mode_of_transport' => 'mode of transport', 'transporter_id' => 'transporter',
            'vehicle_no' => 'vehicle no', 'distance_km' => 'distance', 'pf_charges' => 'P&F charges'];
    }
}
