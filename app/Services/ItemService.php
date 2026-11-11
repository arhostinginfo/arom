<?php

namespace App\Services;

use App\Http\Requests\Admin\ItemRequest;
use App\Models\Currency;
use App\Models\HsnSacCode;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Party;
use App\Models\TaxStructure;
use App\Models\Uom;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Item master (ADMIN tab A15:P16, built with the Sales module 15/10/2026). A blank item no gets the next automatic
 * number of the category's prefix (D20), e.g. PRS-0001; the category row is locked while the number is taken, so
 * two saves at once never get the same number. Each change writes its activity log row in the same transaction.
 */
class ItemService
{
    /** CSV columns for import and download, in the order of the workbook. */
    public const COLUMNS = [
        'party' => 'Party name',
        'category' => 'Category',
        'description' => 'Item description',
        'item_no' => 'Item no',
        'internal_item_no' => 'Internal item no',
        'type' => 'Type',
        'hsn' => 'HSN/SAC',
        'uom' => 'UOM',
        'tax' => 'Tax structure',
        'price' => 'Price',
        'currency' => 'Currency',
        'min_stock' => 'Min stock',
        'max_stock' => 'Max stock',
        'weight_per_piece' => 'Weight / piece',
        'packaging_qty' => 'Packaging qty',
        'status' => 'Status',
    ];

    /** Records that point at an item (customer PO items added 29/10/2026). */
    public const USED = ['sales_order_lines.item_id', 'sales_invoice_lines.item_id', 'stock_movements.item_id', 'purchase_order_lines.item_id', 'grn_lines.item_id',
        'customer_po_lines.item_id'];

    public function save(?Item $item, array $data, User $actor): Item
    {
        return DB::transaction(function () use ($item, $data, $actor) {
            $isNew = ! $item;
            $item = $isNew ? new Item() : Item::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($item);
            $fields = array_intersect_key($data, array_flip(Item::FIELDS));
            if (array_key_exists('item_no', $fields) && $fields['item_no'] === null) {
                unset($fields['item_no']);
            }
            $item->fill($fields);
            $item->price ??= 0;
            if (! $item->item_no) {
                $item->item_no = $this->nextNumber((int) $item->item_category_id);
            }
            if ($isNew) {
                $item->created_by = $actor->id;
            }
            $item->updated_by = $actor->id;
            $item->save();
            if ($isNew) {
                ActivityLogger::log('item.created', $item, 'Added '.$item->activityLabel().' '.$item->description, ['fields' => $this->snapshot($item)]);
            } elseif ($changes = ActivityLogger::diff($before, $this->snapshot($item))) {
                ActivityLogger::log('item.updated', $item, 'Updated '.$item->activityLabel(), $changes);
            }

            return $item;
        });
    }

    public function delete(Item $item, User $actor): void
    {
        DB::transaction(function () use ($item) {
            $item = Item::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $count = 0;
            foreach (self::USED as $ref) {
                [$table, $column] = explode('.', $ref);
                $count += DB::table($table)->where($column, $item->id)->count();
            }
            if ($count) {
                throw ValidationException::withMessages(['delete' => $item->item_no.' is used on '.$count.' record(s), so it cannot be deleted. Make it Inactive instead.']);
            }
            ActivityLogger::log('item.deleted', $item, 'Deleted '.$item->activityLabel(), ['fields' => $this->snapshot($item)]);
            $item->delete();
        });
    }

    /** Next PREFIX-0001 of a category (D20), taken under a lock on the category row. */
    public function nextNumber(int $categoryId): string
    {
        $prefix = ItemCategory::whereKey($categoryId)->lockForUpdate()->value('item_no_prefix');
        if (! $prefix) {
            throw ValidationException::withMessages(['item_no' => 'Enter the item no, or choose a category with an item no prefix for an automatic number.']);
        }
        $last = Item::withTrashed()->where('item_no', 'like', addcslashes($prefix, '%_\\').'-%')->pluck('item_no')
            ->map(fn ($no) => (int) preg_replace('/\D/', '', substr($no, strlen($prefix) + 1)))->max() ?? 0;

        return sprintf('%s-%04d', $prefix, $last + 1);
    }

    /** Import adds every row as a new item; the whole file is checked first and nothing is saved on any error (C4). */
    public function import(array $rows, User $actor): int
    {
        $lookup = fn ($model, string $column) => $model::query()->pluck('id', $column)->mapWithKeys(fn ($id, $k) => [mb_strtolower((string) $k) => $id]);
        $parties = $lookup(Party::class, 'name');
        $categories = $lookup(ItemCategory::class, 'name');
        $hsns = $lookup(HsnSacCode::class, 'code');
        $uoms = $lookup(Uom::class, 'code');
        $taxes = $lookup(TaxStructure::class, 'name');
        $currencies = $lookup(Currency::class, 'code');

        $valid = [];
        $errors = [];
        $seen = [];
        foreach ($rows as $row) {
            $cell = fn (string $f) => ($row[mb_strtolower(self::COLUMNS[$f])] ?? '') !== '' ? trim($row[mb_strtolower(self::COLUMNS[$f])]) : null;
            $local = [];
            $find = function (string $f, $list, string $what) use ($cell, &$local) {
                $v = $cell($f);
                if ($v === null) {
                    return null;
                }
                $id = $list[mb_strtolower($v)] ?? null;
                if (! $id) {
                    $local[] = $what.' "'.$v.'" is not in its master';
                }

                return $id;
            };
            $data = [
                'party_id' => $find('party', $parties, 'party'),
                'item_category_id' => $find('category', $categories, 'category'),
                'hsn_sac_code_id' => $find('hsn', $hsns, 'HSN/SAC'),
                'uom_id' => $find('uom', $uoms, 'UOM'),
                'tax_structure_id' => $find('tax', $taxes, 'tax structure'),
                'currency_id' => $find('currency', $currencies, 'currency'),
                'type' => $cell('type') ? ucfirst(strtolower($cell('type'))) : null,
                'status' => $cell('status') ? ucfirst(strtolower($cell('status'))) : 'Active',
            ];
            foreach (['description', 'item_no', 'internal_item_no', 'price', 'min_stock', 'max_stock', 'weight_per_piece', 'packaging_qty'] as $f) {
                $data[$f] = $cell($f);
            }
            $data = array_merge($data, ItemRequest::normalise($data));
            if ($data['item_no'] && isset($seen[$data['item_no']])) {
                $local[] = 'item no '.$data['item_no'].' is also on row '.$seen[$data['item_no']];
            }
            $v = Validator::make($data, ItemRequest::fieldRules(null), ItemRequest::fieldMessages(), ItemRequest::fieldAttributes());
            $v->after(fn ($v) => ItemRequest::check($v, $data));
            if ($v->fails() || $local) {
                foreach (array_merge($local, $v->errors()->all()) as $msg) {
                    $errors[] = 'Row '.$row['_row'].': '.$msg;
                }

                continue;
            }
            if ($data['item_no']) {
                $seen[$data['item_no']] = $row['_row'];
            }
            $valid[] = $v->validated();
        }
        if ($errors) {
            throw ValidationException::withMessages(['file' => array_slice($errors, 0, 30)]);
        }

        return DB::transaction(function () use ($valid, $actor) {
            $numbers = [];
            foreach ($valid as $data) {
                $item = new Item(array_intersect_key($data, array_flip(Item::FIELDS)));
                $item->price ??= 0;
                $item->item_no ??= $this->nextNumber((int) $item->item_category_id);
                $item->created_by = $actor->id;
                $item->updated_by = $actor->id;
                $item->save();
                $numbers[] = $item->item_no;
            }
            ActivityLogger::log('item.imported', null, 'Imported '.count($valid).' item(s) from a CSV file', ['added' => $numbers]);

            return count($valid);
        });
    }

    /** Values shown in the log: what the user sees on the form. */
    public function snapshot(Item $i): array
    {
        $name = fn ($model, $id, $col = 'name') => $id ? $model::find($id)?->{$col} : null;

        return [
            'Party name' => $name(Party::class, $i->party_id),
            'Category' => $name(ItemCategory::class, $i->item_category_id),
            'Item description' => $i->description,
            'Item no' => $i->item_no,
            'Internal item no' => $i->internal_item_no,
            'Type' => $i->type,
            'HSN/SAC' => $name(HsnSacCode::class, $i->hsn_sac_code_id, 'code'),
            'UOM' => $name(Uom::class, $i->uom_id, 'code'),
            'Tax structure' => $name(TaxStructure::class, $i->tax_structure_id),
            'Price' => $i->price,
            'Currency' => $name(Currency::class, $i->currency_id, 'code'),
            'Min stock' => $i->min_stock,
            'Max stock' => $i->max_stock,
            'Weight / piece' => $i->weight_per_piece,
            'Packaging qty' => $i->packaging_qty,
            'Status' => $i->status,
        ];
    }
}
