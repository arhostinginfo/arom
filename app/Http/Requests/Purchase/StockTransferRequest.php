<?php

namespace App\Http\Requests\Purchase;

use App\Models\Item;
use App\Models\StockTransfer;
use App\Services\PageSetupService;
use App\Support\WorkContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add / edit a stock transfer. Always mandatory: Transfer date, Location from, Location to and at least one item with
 * its quantity; Source type and Remarks as set in Admin › Page setup (page "stock-transfer"). Both locations must be
 * units the user may work in, and they must differ. The stock at the source is checked by the service.
 */
class StockTransferRequest extends FormRequest
{
    public const LINE_FIELDS = ['item_id', 'quantity', 'remarks'];

    public function authorize(): bool
    {
        return $this->user()->can($this->route('transfer') ? 'stock-transfer.edit' : 'stock-transfer.add');
    }

    protected function prepareForValidation(): void
    {
        // Items come as one JSON field from the page (well below PHP's max_input_vars), or as lines[n][field].
        $lines = $this->has('lines_json') ? json_decode((string) $this->input('lines_json'), true) : $this->input('lines', []);
        $this->merge(['lines' => self::cleanLines(is_array($lines) ? $lines : [])]);
    }

    /** Drop rows where nothing was typed; quantities may be typed with commas. */
    public static function cleanLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $line = array_map(fn ($v) => is_string($v) ? trim($v) : $v, array_intersect_key($line, array_flip(self::LINE_FIELDS)));
            if (isset($line['quantity']) && is_string($line['quantity'])) {
                $line['quantity'] = str_replace([',', ' '], '', $line['quantity']);
            }
            if (array_filter($line, fn ($v) => $v !== null && $v !== '')) {
                $out[] = $line;
            }
        }

        return $out;
    }

    public function rules(): array
    {
        $units = WorkContext::unitIds($this->user());

        return app(PageSetupService::class)->rules('stock-transfer', [
            'transfer_date' => ['required', 'date_format:d/m/Y'],
            'from_unit_id' => ['required', 'integer', Rule::in($units)],
            'to_unit_id' => ['required', 'integer', Rule::in($units), 'different:from_unit_id'],
            'source_type' => ['nullable', Rule::in(StockTransfer::SOURCE_TYPES)],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.item_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $date = $this->input('transfer_date');
            if ($date && ! $v->errors()->has('transfer_date') && preg_match('#^\d{2}/\d{2}/\d{4}$#', $date)) {
                $d = Carbon::createFromFormat('d/m/Y', $date)->startOfDay();
                $current = $this->route('transfer');
                $year = $current?->financialYear ?? WorkContext::financialYear();
                if ($d->gt(today())) {
                    $v->errors()->add('transfer_date', 'The transfer date cannot be in the future.');
                } elseif ($d->lt($year->starts_on) || $d->gt($year->ends_on)) {
                    $v->errors()->add('transfer_date', 'The transfer date must be in the financial year '.$year->label().'.');
                }
            }
            // Each item must be an active item of the Item master, or already on this transfer.
            $current = $this->route('transfer');
            $onTransfer = $current ? $current->lines()->pluck('item_id')->all() : [];
            $ids = array_filter(array_map('intval', array_column((array) $this->input('lines', []), 'item_id')));
            $ok = Item::whereIn('id', $ids)->where(fn ($q) => $q->where('status', 'Active')->orWhereIn('id', $onTransfer))->pluck('id')->all();
            foreach ((array) $this->input('lines', []) as $i => $l) {
                if (! empty($l['item_id']) && ! in_array((int) $l['item_id'], $ok, true) && ! $v->errors()->has("lines.$i.item_id")) {
                    $v->errors()->add("lines.$i.item_id", 'Item '.($i + 1).' is not an active item of the Item master.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'from_unit_id.required' => 'Choose the location the stock goes out of.',
            'from_unit_id.in' => 'Choose a unit you may work in.',
            'to_unit_id.required' => 'Choose the location the stock goes to.',
            'to_unit_id.in' => 'Choose a unit you may work in.',
            'to_unit_id.different' => 'Location from and to are the same.',
            'source_type.in' => 'Choose Purchase, Credit or Debit.',
            'lines.required' => 'Add at least one item with its quantity.',
            'lines.*.item_id.required' => 'Choose the item.',
            'lines.*.item_id.distinct' => 'This item is already on another row; put the whole quantity on one row.',
            'lines.*.quantity.required' => 'Enter the quantity.',
            'lines.*.quantity.numeric' => 'Enter the quantity as a number.',
            'lines.*.quantity.gt' => 'The quantity must be more than 0.',
            'transfer_date.date_format' => 'Enter the date as dd/mm/yyyy.',
        ];
    }

    public function attributes(): array
    {
        return ['transfer_date' => 'transfer date', 'from_unit_id' => 'location from', 'to_unit_id' => 'location to', 'source_type' => 'source type'];
    }
}
