<?php

namespace App\Http\Requests\Purchase;

use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SalesOrder;
use App\Services\PageSetupService;
use App\Support\Money;
use App\Support\WorkContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add / edit a GRN. Always mandatory (prototype MANDATORY.grn): Party name (an active Supplier or Both party), Invoice
 * type, Invoice no, Invoice date, Invoice amount, and at least one item line with its quantity. Transporter, vehicle,
 * e-way bill, customer, SO no and remarks as set in Admin › Page setup (page "grn").
 * Purchase invoice lines are received against a PO line of an Approved / Partly received PO of this supplier in the
 * GRN's unit (unless config nursery.grn.lines_without_po), with the quantity not above what is pending.
 * Supplier credit / debit notes have no PO lines: the supplier's purchase items only.
 */
class GrnRequest extends FormRequest
{
    public const LINE_FIELDS = ['purchase_order_id', 'purchase_order_line_id', 'item_id', 'quantity', 'rate'];

    public function authorize(): bool
    {
        // The GRN must be of a unit the user may work in, checked before validation so another unit's POs are not described.
        $grn = $this->route('grn');
        if ($grn && ! in_array($grn->unit_id, WorkContext::unitIds($this->user()), true)) {
            return false;
        }

        return $this->user()->can($grn ? 'grn.edit' : 'grn.add');
    }

    protected function prepareForValidation(): void
    {
        // Items come as one JSON field from the page (well below PHP's max_input_vars), or as lines[n][field].
        $lines = $this->has('lines_json') ? json_decode((string) $this->input('lines_json'), true) : $this->input('lines', []);
        $merge = ['lines' => self::cleanLines(is_array($lines) ? $lines : [])];
        foreach (['invoice_no', 'vehicle_no', 'eway_bill_no', 'remarks'] as $f) {
            if ($this->has($f)) {
                $merge[$f] = trim((string) $this->input($f));
            }
        }
        if ($this->has('invoice_amount')) {
            $merge['invoice_amount'] = str_replace([',', ' ', '₹'], '', (string) $this->input('invoice_amount'));
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
            foreach (['quantity', 'rate'] as $n) {
                if (isset($line[$n]) && is_string($line[$n])) {
                    $line[$n] = str_replace([',', ' '], '', $line[$n]);
                }
            }
            if (array_filter(array_intersect_key($line, array_flip(['purchase_order_line_id', 'item_id', 'quantity', 'rate'])), fn ($v) => $v !== null && $v !== '')) {
                $out[] = $line;
            }
        }

        return $out;
    }

    public function rules(): array
    {
        $current = $this->route('grn');

        return app(PageSetupService::class)->rules('grn', [
            'intent' => ['nullable', Rule::in(['match', 'pending'])],
            'invoice_type' => ['required', Rule::in(Grn::TYPES)],
            'party_id' => ['required', 'integer', Rule::exists('parties', 'id')->whereNull('deleted_at')->whereIn('type', ['Supplier', 'Both'])
                ->where(fn ($q) => $q->where('status', 'Active')->when($current?->party_id, fn ($w, $id) => $w->orWhere('id', $id)))],
            'invoice_no' => ['required', 'string', 'max:40'],
            'invoice_date' => ['required', 'date_format:d/m/Y'],
            'invoice_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'transporter_id' => ['nullable', 'integer', Rule::exists('transporters', 'id')
                ->where(fn ($q) => $q->where('is_active', true)->when($current?->transporter_id, fn ($w, $id) => $w->orWhere('id', $id)))],
            'vehicle_no' => ['nullable', 'string', 'max:20'],
            'eway_bill_no' => ['nullable', 'string', 'max:20', 'regex:/^[0-9A-Za-z]+$/'],
            'eway_bill_date' => ['nullable', 'date_format:d/m/Y'],
            'customer_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->whereNull('deleted_at')->whereIn('type', ['Customer', 'Both'])
                ->where(fn ($q) => $q->where('status', 'Active')->when($current?->customer_id, fn ($w, $id) => $w->orWhere('id', $id)))],
            'sales_order_id' => ['nullable', 'integer'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.purchase_order_id' => ['nullable', 'integer'],
            'lines.*.purchase_order_line_id' => ['nullable', 'integer', 'distinct'],
            'lines.*.item_id' => ['nullable', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
        ]);
    }

    public function after(): array
    {
        return [function (Validator $v) {
            /** @var Grn|null $current */
            $current = $this->route('grn');
            $d = fn ($x) => is_string($x) && preg_match('#^\d{2}/\d{2}/\d{4}$#', $x) ? Carbon::createFromFormat('d/m/Y', $x)->startOfDay() : null;
            $invDate = $d($this->input('invoice_date'));
            if ($invDate && ! $v->errors()->has('invoice_date') && $invDate->gt(today())) {
                $v->errors()->add('invoice_date', 'The invoice date cannot be after today.');
            }
            // Not after the end of the GRN's financial year, so the GRN date (on or after the invoice date) stays in that year.
            // An earlier date is allowed: a March invoice received in April is entered in the new year.
            $year = $current?->financialYear ?? WorkContext::financialYear();
            if ($invDate && ! $v->errors()->has('invoice_date') && $invDate->gt($year->ends_on)) {
                $v->errors()->add('invoice_date', 'The invoice date must be on or before '.$year->ends_on->format('d/m/Y').', the end of the financial year '.$year->label().'.');
            }
            if (($ew = $d($this->input('eway_bill_date'))) && ! $v->errors()->has('eway_bill_date') && $ew->gt(today())) {
                $v->errors()->add('eway_bill_date', 'The e-way bill date cannot be after today.');
            }

            // SO no: a sales order of the customer chosen.
            $so = (int) $this->input('sales_order_id');
            if ($so && ! $v->errors()->has('sales_order_id')) {
                $customer = (int) $this->input('customer_id');
                if (! $customer) {
                    $v->errors()->add('sales_order_id', 'Choose the customer first, then the SO no.');
                } elseif (! SalesOrder::whereKey($so)->where('party_id', $customer)->exists()) {
                    $v->errors()->add('sales_order_id', 'Choose a sales order of this customer.');
                }
            }

            $this->checkLines($v, $current);
        }];
    }

    /** PO lines of this supplier and unit with the quantity pending; or, without a PO line, the supplier's purchase items. */
    private function checkLines(Validator $v, ?Grn $current): void
    {
        $lines = (array) $this->input('lines', []);
        if (! $lines || $v->errors()->has('party_id') || $v->errors()->has('invoice_type')) {
            return;
        }
        $party = (int) $this->input('party_id');
        $unitId = $current?->unit_id ?? WorkContext::unit()?->id;
        $type = (string) $this->input('invoice_type');
        $withoutPo = (bool) config('nursery.grn.lines_without_po', false);
        $ids = array_filter(array_map('intval', array_column($lines, 'purchase_order_line_id')));
        $poLines = PurchaseOrderLine::with('order', 'item:id,item_no')->whereIn('id', $ids)->get()->keyBy('id');
        // Quantity this GRN already holds received on each PO line (a matched GRN being changed).
        $held = [];
        $onGrn = collect();
        if ($current) {
            $onGrn = GrnLine::where('grn_id', $current->id)->get(['purchase_order_line_id', 'item_id', 'quantity']);
            if ($current->isMatched() && $current->isPurchase() && $current->status === Grn::SAVED) {
                foreach ($onGrn as $l) {
                    if ($l->purchase_order_line_id) {
                        $held[$l->purchase_order_line_id] = ($held[$l->purchase_order_line_id] ?? 0) + (float) $l->quantity;
                    }
                }
            }
        }
        $itemIds = array_filter(array_map('intval', array_column($lines, 'item_id')));
        $okItems = Item::whereIn('id', $itemIds)->where(fn ($q) => $q->where(fn ($s) => $s->forPurchase()->where(fn ($p) => $p->whereNull('party_id')->orWhere('party_id', $party)))
            ->orWhereIn('id', $onGrn->pluck('item_id')->all()))->pluck('id')->all();

        foreach ($lines as $i => $l) {
            $n = $i + 1;
            $plId = (int) ($l['purchase_order_line_id'] ?? 0);
            if ($type !== 'Purchase') {
                if ($plId) {
                    $v->errors()->add("lines.$i.purchase_order_line_id", "Line $n: a supplier ".strtolower($type).' note is not received against a PO. Remove the PO no.');
                } elseif (empty($l['item_id'])) {
                    $v->errors()->add("lines.$i.item_id", "Choose the item of line $n.");
                } elseif (! in_array((int) $l['item_id'], $okItems, true)) {
                    $v->errors()->add("lines.$i.item_id", "Line $n is not an active purchase item of this supplier.");
                }

                continue;
            }
            if (! $plId) {
                if (! $withoutPo) {
                    $v->errors()->add("lines.$i.purchase_order_line_id", "Choose the PO no and its item for line $n.");
                } elseif (empty($l['item_id'])) {
                    $v->errors()->add("lines.$i.item_id", "Choose the item of line $n.");
                } elseif (! in_array((int) $l['item_id'], $okItems, true)) {
                    $v->errors()->add("lines.$i.item_id", "Line $n is not an active purchase item of this supplier.");
                }

                continue;
            }
            $pl = $poLines->get($plId);
            $po = $pl?->order;
            if (! $pl || ! $po || $po->party_id !== $party || $po->unit_id !== $unitId) {
                $v->errors()->add("lines.$i.purchase_order_line_id", "Line $n: choose a PO of this supplier in this unit.");

                continue;
            }
            if (! isset($held[$plId]) && ! in_array($po->status, PurchaseOrder::RECEIVABLE, true)) {
                $v->errors()->add("lines.$i.purchase_order_line_id", "Line $n: {$po->po_no} is {$po->status}; goods can be received only against an Approved PO.");

                continue;
            }
            $max = round($pl->pending() + ($held[$plId] ?? 0), 3);
            if (is_numeric($l['quantity'] ?? null) && (float) $l['quantity'] > $max + 0.0005 && ! $v->errors()->has("lines.$i.quantity")) {
                $v->errors()->add("lines.$i.quantity", "Line $n ({$pl->item?->item_no}): quantity cannot exceed the PO quantity pending, ".Money::qty($max).' on '.$po->po_no.'.');
            }
        }
    }

    public function messages(): array
    {
        return [
            'party_id.required' => 'Choose the party (supplier).',
            'party_id.exists' => 'Choose an active supplier. Customers are not listed: a customer party cannot be used on purchase.',
            'invoice_type.required' => 'Choose the invoice type.',
            'invoice_no.required' => 'Enter the supplier invoice no.',
            'invoice_date.required' => 'Enter the supplier invoice date.',
            'invoice_amount.required' => 'Enter the supplier invoice amount including GST.',
            'invoice_amount.gt' => 'The invoice amount must be more than 0.',
            'eway_bill_no.regex' => 'The e-way bill no has only letters and digits.',
            'customer_id.exists' => 'Choose an active customer.',
            'lines.required' => 'Add at least one item with its invoice quantity.',
            'lines.*.purchase_order_line_id.distinct' => 'This PO item is already on another line; put the whole quantity on one line.',
            'lines.*.quantity.required' => 'Enter the invoice quantity.',
            'lines.*.quantity.numeric' => 'Enter the quantity as a number.',
            'lines.*.quantity.gt' => 'The quantity must be more than 0.',
            '*.date_format' => 'Enter the date as dd/mm/yyyy.',
        ];
    }

    public function attributes(): array
    {
        return ['party_id' => 'party name', 'invoice_type' => 'invoice type', 'invoice_no' => 'invoice no', 'invoice_date' => 'invoice date', 'invoice_amount' => 'invoice amount',
            'transporter_id' => 'transporter', 'vehicle_no' => 'vehicle no', 'eway_bill_no' => 'e-way bill no', 'eway_bill_date' => 'e-way bill date',
            'customer_id' => 'customer name', 'sales_order_id' => 'SO no'];
    }
}
