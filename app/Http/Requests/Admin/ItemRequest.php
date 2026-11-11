<?php

namespace App\Http\Requests\Admin;

use App\Models\Item;
use App\Models\Party;
use App\Services\PageSetupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add / edit an item (Item master). Always mandatory (S4): Item description, Item no (or blank for an automatic
 * number from the category's prefix, D20), Type, HSN/SAC, UOM, Tax structure, Status; the rest as set in Admin ›
 * Page setup. A customer's item cannot be a Purchase item and a supplier's item cannot be a Sales item (ADMIN A23).
 * The same rules check each row of an item import.
 */
class ItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('item') ? 'masters.edit' : 'masters.add');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(self::normalise($this->all()));
    }

    public function rules(): array
    {
        return self::fieldRules($this->route('item'));
    }

    public function after(): array
    {
        return [fn (Validator $v) => self::check($v, $this->all() + ['_existing' => (bool) $this->route('item')])];
    }

    public function messages(): array
    {
        return self::fieldMessages();
    }

    public function attributes(): array
    {
        return self::fieldAttributes();
    }

    public static function normalise(array $data): array
    {
        $out = [];
        foreach (['description', 'item_no', 'internal_item_no'] as $f) {
            if (array_key_exists($f, $data)) {
                $v = trim((string) $data[$f]);
                $out[$f] = $f === 'description' ? $v : (strtoupper($v) ?: null);
            }
        }
        foreach (['price', 'min_stock', 'max_stock', 'weight_per_piece', 'packaging_qty'] as $f) {
            if (array_key_exists($f, $data)) {
                $out[$f] = str_replace([',', ' '], '', (string) $data[$f]);
                if ($out[$f] === '') {
                    $out[$f] = null;
                }
            }
        }

        return $out;
    }

    public static function fieldRules(?Item $item): array
    {
        // An inactive master row is refused, except the one already saved on this item.
        $active = fn (string $table, string $column) => Rule::exists($table, 'id')->where(function ($q) use ($item, $column) {
            $q->where('is_active', true);
            if ($item?->{$column}) {
                $q->orWhere('id', $item->{$column});
            }
        });

        return app(PageSetupService::class)->rules('item', [
            'party_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->whereNull('deleted_at')],
            'item_category_id' => ['nullable', 'integer', $active('item_categories', 'item_category_id')],
            'description' => ['required', 'string', 'max:255'],
            // Blank: an automatic number from the category's prefix (checked in check()).
            'item_no' => ['nullable', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9.\/_-]*$/', Rule::unique('items', 'item_no')->ignore($item?->id)],
            'internal_item_no' => ['nullable', 'string', 'max:50'],
            'type' => ['required', Rule::in(Item::TYPES)],
            'hsn_sac_code_id' => ['required', 'integer', $active('hsn_sac_codes', 'hsn_sac_code_id')],
            'uom_id' => ['required', 'integer', $active('uoms', 'uom_id')],
            'tax_structure_id' => ['required', 'integer', $active('tax_structures', 'tax_structure_id')],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'currency_id' => ['nullable', 'integer', Rule::exists('currencies', 'id')],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'max_stock' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'weight_per_piece' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'packaging_qty' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);
    }

    public static function check(Validator $v, array $data): void
    {
        if (($data['item_no'] ?? null) === null && ! $v->errors()->has('item_no')) {
            $prefix = ! empty($data['item_category_id']) ? \App\Models\ItemCategory::whereKey((int) $data['item_category_id'])->value('item_no_prefix') : null;
            if (! empty($data['_existing'])) {
                return;
            }
            if (! $prefix) {
                $v->errors()->add('item_no', 'Enter the item no, or choose a category with an item no prefix for an automatic number.');
            }
        }
        $partyType = ! empty($data['party_id']) ? Party::whereKey((int) $data['party_id'])->value('type') : null;
        if ($partyType === 'Customer' && ($data['type'] ?? null) === 'Purchase') {
            $v->errors()->add('type', 'This party is a customer, so the item cannot be a Purchase item.');
        }
        if ($partyType === 'Supplier' && ($data['type'] ?? null) === 'Sales') {
            $v->errors()->add('type', 'This party is a supplier, so the item cannot be a Sales item.');
        }
        if (isset($data['min_stock'], $data['max_stock']) && is_numeric($data['min_stock']) && is_numeric($data['max_stock'])
            && (float) $data['max_stock'] < (float) $data['min_stock']) {
            $v->errors()->add('max_stock', 'Max stock cannot be lower than Min stock.');
        }
    }

    public static function fieldMessages(): array
    {
        return [
            'item_no.unique' => 'Another item has this item no.',
            'item_no.regex' => 'Use capital letters, numbers, ".", "/", "_" and "-" only.',
            'hsn_sac_code_id.required' => 'Choose the HSN/SAC code.',
            'uom_id.required' => 'Choose the UOM.',
            'tax_structure_id.required' => 'Choose the tax structure.',
        ];
    }

    public static function fieldAttributes(): array
    {
        return ['party_id' => 'party', 'item_category_id' => 'category', 'item_no' => 'item no', 'internal_item_no' => 'internal item no',
            'hsn_sac_code_id' => 'HSN/SAC', 'uom_id' => 'UOM', 'tax_structure_id' => 'tax structure', 'currency_id' => 'currency',
            'min_stock' => 'min stock', 'max_stock' => 'max stock', 'weight_per_piece' => 'weight / piece', 'packaging_qty' => 'packaging qty'];
    }
}
