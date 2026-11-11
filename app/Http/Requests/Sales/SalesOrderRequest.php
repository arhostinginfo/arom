<?php

namespace App\Http\Requests\Sales;

use App\Models\Item;
use App\Models\SalesOrder;
use App\Services\PageSetupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add / edit a sales order. Always mandatory (S4): Party name, PO number, Start date, and at least one item with
 * quantity and delivery date; End date and Rev no / Rev date as set in Admin › Page setup. Errors go to the "order"
 * bag, so they show on the Sales order card of the Sales page and not on the Tax invoice card.
 */
class SalesOrderRequest extends FormRequest
{
    protected $errorBag = 'order';

    public const LINE_FIELDS = ['id', 'item_id', 'quantity', 'rate', 'delivery_date'];

    public function authorize(): bool
    {
        return $this->user()->can($this->route('order') ? 'sales-order.edit' : 'sales-order.add');
    }

    protected function prepareForValidation(): void
    {
        // Items come as one JSON field from the page (well below PHP's max_input_vars), or as lines[n][field].
        $lines = $this->has('lines_json') ? json_decode((string) $this->input('lines_json'), true) : $this->input('lines', []);
        $this->merge([
            'lines' => self::cleanLines(is_array($lines) ? $lines : []),
            'po_number' => trim((string) $this->input('po_number')),
            '_form' => 'order',
        ]);
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
            if (array_filter(array_diff_key($line, ['id' => 1]), fn ($v) => $v !== null && $v !== '')) {
                $out[] = $line;
            }
        }

        return $out;
    }

    public function rules(): array
    {
        $current = $this->route('order');

        return app(PageSetupService::class)->rules('sales-order', [
            'party_id' => ['required', 'integer', Rule::exists('parties', 'id')->whereNull('deleted_at')->whereIn('type', ['Customer', 'Both'])
                ->where(fn ($q) => $q->where('status', 'Active')->when($current?->party_id, fn ($w, $id) => $w->orWhere('id', $id)))],
            'po_number' => ['required', 'string', 'max:60'],
            'start_date' => ['required', 'date_format:d/m/Y'],
            'end_date' => ['nullable', 'date_format:d/m/Y'],
            'revision_no' => ['nullable', 'string', 'max:10'],
            'revision_date' => ['nullable', 'date_format:d/m/Y'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'lines.*.delivery_date' => ['required', 'date_format:d/m/Y'],
        ]);
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $d = fn ($f) => ($x = $this->input($f)) && preg_match('#^\d{2}/\d{2}/\d{4}$#', $x) ? \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $x)->startOfDay() : null;
            if (($s = $d('start_date')) && ($e = $d('end_date')) && $e->lt($s) && ! $v->errors()->has('end_date')) {
                $v->errors()->add('end_date', 'The end date must be on or after the start date.');
            }
            $party = (int) $this->input('party_id');
            $current = $this->route('order');
            // The same customer PO cannot be entered twice (code review: duplicate orders double the PO balance).
            if ($party && $this->input('po_number') !== '' && SalesOrder::where('party_id', $party)->where('po_number', $this->input('po_number'))
                ->when($current, fn ($q) => $q->whereKeyNot($current->id))->exists()) {
                $v->errors()->add('po_number', 'This PO number is already on another sales order of this party.');
            }
            // Each part must be an active Sales / Both item of this customer (or of no party), or already on this order.
            $onOrder = $current ? $current->lines()->pluck('item_id')->all() : [];
            $ids = array_filter(array_map('intval', array_column((array) $this->input('lines', []), 'item_id')));
            $ok = Item::whereIn('id', $ids)->where(fn ($q) => $q->where(fn ($s) => $s->forSale()->where(fn ($p) => $p->whereNull('party_id')->orWhere('party_id', $party)))
                ->orWhereIn('id', $onOrder))->pluck('id')->all();
            foreach ((array) $this->input('lines', []) as $i => $l) {
                if (! empty($l['item_id']) && ! in_array((int) $l['item_id'], $ok, true) && ! $v->errors()->has("lines.$i.item_id")) {
                    $v->errors()->add("lines.$i.item_id", 'Item '.($i + 1).' is not an active sales item of this customer.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'party_id.required' => 'Choose the party (customer).',
            'party_id.exists' => 'Choose an active customer.',
            'lines.required' => 'Add at least one item with quantity and delivery date.',
            'lines.*.item_id.required' => 'Choose the item.',
            'lines.*.quantity.required' => 'Enter the quantity.',
            'lines.*.quantity.gt' => 'The quantity must be more than 0.',
            'lines.*.delivery_date.required' => 'Enter the delivery date.',
            'lines.*.delivery_date.date_format' => 'Enter the delivery date as dd/mm/yyyy.',
            '*.date_format' => 'Enter the date as dd/mm/yyyy.',
        ];
    }

    public function attributes(): array
    {
        return ['party_id' => 'party name', 'po_number' => 'PO number', 'start_date' => 'start date', 'end_date' => 'end date', 'revision_no' => 'rev no', 'revision_date' => 'rev date'];
    }
}
