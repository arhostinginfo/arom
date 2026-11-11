<?php

namespace App\Http\Requests\Purchase;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\TaxStructure;
use App\Models\Uom;
use App\Services\PageSetupService;
use App\Support\WorkContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add / edit a purchase order. Always mandatory: Party name (an active Supplier or Both party), PO date and at least one
 * item with its quantity; the other headings (delivery date, payment terms, currency, transporter, addresses, remarks)
 * as set in Admin › Page setup ("purchase-order"). Items must be active Purchase / Both items of the supplier or of no
 * party (S-4). UOM and Tax come from their masters; left blank they are the item's.
 */
class PurchaseOrderRequest extends FormRequest
{
    public const LINE_FIELDS = ['id', 'item_id', 'description', 'quantity', 'uom_id', 'rate', 'discount_percent', 'tax_structure_id', 'delivery_date'];

    public function authorize(): bool
    {
        return $this->user()->can($this->route('order') ? 'purchase-order.edit' : 'purchase-order.add');
    }

    protected function prepareForValidation(): void
    {
        // Items come as one JSON field from the page (well below PHP's max_input_vars), or as lines[n][field].
        $lines = $this->has('lines_json') ? json_decode((string) $this->input('lines_json'), true) : $this->input('lines', []);
        $merge = ['lines' => self::cleanLines(is_array($lines) ? $lines : [])];
        foreach (['payment_terms', 'billing_address', 'shipping_address', 'remarks'] as $f) {
            if ($this->has($f)) {
                $merge[$f] = trim((string) $this->input($f));
            }
        }
        $this->merge($merge);
    }

    /** Drop rows where nothing was typed; numbers may be typed with commas. */
    public static function cleanLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $line = array_map(fn ($v) => is_string($v) ? trim($v) : $v, array_intersect_key($line, array_flip(self::LINE_FIELDS)));
            foreach (['quantity', 'rate', 'discount_percent'] as $n) {
                if (isset($line[$n]) && is_string($line[$n])) {
                    $line[$n] = str_replace([',', ' '], '', $line[$n]);
                }
            }
            // A row with only its UOM / tax drop-downs filled in (from the item) and nothing typed is empty.
            if (array_filter(array_intersect_key($line, array_flip(['item_id', 'description', 'quantity', 'rate', 'discount_percent', 'delivery_date'])), fn ($v) => $v !== null && $v !== '')) {
                $out[] = $line;
            }
        }

        return $out;
    }

    public function rules(): array
    {
        $current = $this->route('order');

        return app(PageSetupService::class)->rules('purchase-order', [
            'party_id' => ['required', 'integer', Rule::exists('parties', 'id')->whereNull('deleted_at')->whereIn('type', ['Supplier', 'Both'])
                ->where(fn ($q) => $q->where('status', 'Active')->when($current?->party_id, fn ($w, $id) => $w->orWhere('id', $id)))],
            'po_date' => ['required', 'date_format:d/m/Y'],
            'delivery_date' => ['nullable', 'date_format:d/m/Y'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'currency_id' => ['nullable', 'integer', Rule::exists('currencies', 'id')],
            'transporter_id' => ['nullable', 'integer', Rule::exists('transporters', 'id')
                ->where(fn ($q) => $q->where('is_active', true)->when($current?->transporter_id, fn ($w, $id) => $w->orWhere('id', $id)))],
            'billing_address' => ['nullable', 'string', 'max:1000'],
            'shipping_address' => ['nullable', 'string', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'lines.*.uom_id' => ['nullable', 'integer'],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'lines.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_structure_id' => ['nullable', 'integer'],
            'lines.*.delivery_date' => ['nullable', 'date_format:d/m/Y'],
        ]);
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $current = $this->route('order');
            $d = fn ($x) => is_string($x) && preg_match('#^\d{2}/\d{2}/\d{4}$#', $x) ? Carbon::createFromFormat('d/m/Y', $x)->startOfDay() : null;

            // PO date: not after today, and in the financial year on the top bar (or the PO's own year for an edit).
            if (($date = $d($this->input('po_date'))) && ! $v->errors()->has('po_date')) {
                $year = $current?->financialYear ?? WorkContext::financialYear();
                if ($date->gt(today())) {
                    $v->errors()->add('po_date', 'The PO date cannot be after today.');
                } elseif ($date->lt($year->starts_on) || $date->gt($year->ends_on)) {
                    $v->errors()->add('po_date', $current
                        ? $current->po_no.' is numbered in '.$year->label().', so its date must be between '.$year->starts_on->format('d/m/Y').' and '.$year->ends_on->format('d/m/Y').'.'
                        : 'The PO date must be in the financial year '.$year->label().' (top bar).');
                }
                if (($del = $d($this->input('delivery_date'))) && $del->lt($date) && ! $v->errors()->has('delivery_date')) {
                    $v->errors()->add('delivery_date', 'The delivery date must be on or after the PO date.');
                }
            }

            // Items: active Purchase / Both items of this supplier or of no party (S-4), or already on this PO.
            // An item already on the PO (since made inactive, say) is kept only while the supplier is unchanged, so a
            // Draft cannot switch supplier and keep the old supplier's items (review fix, S-4).
            $party = (int) $this->input('party_id');
            $lines = (array) $this->input('lines', []);
            $onPo = $current ? $current->lines()->get(['item_id', 'uom_id', 'tax_structure_id']) : collect();
            $keepItems = $current && $party === (int) $current->party_id ? $onPo->pluck('item_id')->all() : [];
            $ids = array_filter(array_map('intval', array_column($lines, 'item_id')));
            $ok = Item::whereIn('id', $ids)->where(fn ($q) => $q->where(fn ($s) => $s->forPurchase()->where(fn ($p) => $p->whereNull('party_id')->orWhere('party_id', $party)))
                ->orWhereIn('id', $keepItems))->pluck('id')->all();

            // Currency is the supplier's (Party master): GST, Import and the approval limit follow the supplier, so a
            // different currency picked on the page is refused (blank = the supplier's).
            if ($party && $this->filled('currency_id') && ! $v->errors()->has('currency_id')
                && ($supplier = \App\Models\Party::with('currency:id,code')->find($party))) {
                $own = $supplier->currency_id ?? \App\Models\Currency::defaultId();
                if ((int) $this->input('currency_id') !== (int) $own) {
                    $v->errors()->add('currency_id', 'The PO currency must be the supplier\'s currency ('.($supplier->currency?->code ?? 'INR').', Party master).');
                }
            }
            $uoms = Uom::forPicker()->orWhereIn('id', $onPo->pluck('uom_id')->all())->pluck('id')->all();
            $taxes = TaxStructure::forPicker()->orWhereIn('id', $onPo->pluck('tax_structure_id')->all())->pluck('id')->all();
            $poDate = $d($this->input('po_date'));
            foreach ($lines as $i => $l) {
                $n = $i + 1;
                if (! empty($l['item_id']) && ! in_array((int) $l['item_id'], $ok, true) && ! $v->errors()->has("lines.$i.item_id")) {
                    $v->errors()->add("lines.$i.item_id", "Item $n is not an active purchase item of this supplier.");
                }
                if (! empty($l['uom_id']) && ! in_array((int) $l['uom_id'], $uoms, true) && ! $v->errors()->has("lines.$i.uom_id")) {
                    $v->errors()->add("lines.$i.uom_id", "Choose an active UOM for item $n.");
                }
                if (! empty($l['tax_structure_id']) && ! in_array((int) $l['tax_structure_id'], $taxes, true) && ! $v->errors()->has("lines.$i.tax_structure_id")) {
                    $v->errors()->add("lines.$i.tax_structure_id", "Choose an active tax for item $n.");
                }
                if ($poDate && ($ld = $d($l['delivery_date'] ?? null)) && $ld->lt($poDate) && ! $v->errors()->has("lines.$i.delivery_date")) {
                    $v->errors()->add("lines.$i.delivery_date", "The delivery date of item $n must be on or after the PO date.");
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'party_id.required' => 'Choose the party (supplier).',
            'party_id.exists' => 'Choose an active supplier.',
            'lines.required' => 'Add at least one item with its quantity.',
            'lines.*.item_id.required' => 'Choose the item.',
            'lines.*.quantity.required' => 'Enter the quantity.',
            'lines.*.quantity.gt' => 'The quantity must be more than 0.',
            'lines.*.discount_percent.max' => 'The discount cannot be more than 100%.',
            'lines.*.delivery_date.date_format' => 'Enter the delivery date as dd/mm/yyyy.',
            '*.date_format' => 'Enter the date as dd/mm/yyyy.',
        ];
    }

    public function attributes(): array
    {
        return ['party_id' => 'party name', 'po_date' => 'PO date', 'delivery_date' => 'delivery date', 'payment_terms' => 'payment terms', 'currency_id' => 'currency',
            'transporter_id' => 'transporter', 'billing_address' => 'billing address', 'shipping_address' => 'shipping address'];
    }
}
