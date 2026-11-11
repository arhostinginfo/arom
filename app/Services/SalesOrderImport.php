<?php

namespace App\Services;

use App\Http\Requests\Sales\SalesOrderRequest;
use App\Models\FinancialYear;
use App\Models\Item;
use App\Models\Party;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Sales order import (OTHER POINTS row 6). One row per item; rows with the same Group become one order, whose header
 * is read from the group's first row. The whole file is checked first: if any row is wrong nothing is saved and the
 * rows to fix are listed (C4). The numbers are reserved in one short locked step (C12). Each order starts as a Draft.
 */
class SalesOrderImport
{
    public const COLUMNS = [
        'group' => 'Group',
        'party' => 'Party name',
        'po_number' => 'PO number',
        'start_date' => 'Start date',
        'end_date' => 'End date',
        'revision_no' => 'Rev no',
        'revision_date' => 'Rev date',
        'item_no' => 'Item no',
        'quantity' => 'Quantity',
        'rate' => 'Rate',
        'delivery_date' => 'Delivery date',
    ];

    public function __construct(private readonly NumberSeries $numbers, private readonly SalesOrderService $orders)
    {
    }

    public function run(array $rows, User $actor, Unit $unit, FinancialYear $year): int
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = ($row['group'] ?? '') !== '' ? 'g'.$row['group'] : 'row-'.$row['_row'];
            $groups[$key][] = $row;
        }
        $inFile = fn (string $col) => array_values(array_unique(array_filter(array_map(fn ($r) => mb_strtolower(trim($r[$col] ?? '')), $rows))));
        $parties = Party::whereIn(DB::raw('LOWER(name)'), $inFile('party name'))->customers()->with('currency:id,code')->get(['id', 'name', 'currency_id'])->keyBy(fn ($p) => mb_strtolower($p->name));
        $items = Item::whereIn('item_no', array_map('strtoupper', $inFile('item no')))->forSale()->with('currency:id,code')->get(['id', 'item_no', 'party_id', 'currency_id'])->keyBy('item_no');

        $valid = [];
        $errors = [];
        $poSeen = [];
        foreach ($groups as $key => $groupRows) {
            $first = $groupRows[0];
            $where = count($groupRows) > 1 ? 'Rows '.$first['_row'].' to '.end($groupRows)['_row'] : 'Row '.$first['_row'];
            $cell = fn (array $r, string $col) => ($r[$col] ?? '') !== '' ? trim($r[$col]) : null;
            $local = [];
            $party = $parties[mb_strtolower((string) $cell($first, 'party name'))] ?? null;
            if ($cell($first, 'party name') && ! $party) {
                $local[] = 'party "'.$cell($first, 'party name').'" is not an active customer in the Party master';
            }
            $lines = [];
            foreach ($groupRows as $r) {
                $no = strtoupper((string) $cell($r, 'item no'));
                $item = $items[$no] ?? null;
                if ($no !== '' && (! $item || ($party && $item->party_id && $item->party_id !== $party->id))) {
                    $local[] = 'item "'.$no.'" (row '.$r['_row'].') is not an active sales item of this customer';
                } elseif ($item && $party && $cell($r, 'rate') === null && ($item->currency?->code ?? 'INR') !== ($party->currency?->code ?? 'INR')) {
                    // A blank rate takes the Item master price only in the party's currency (code review M3).
                    $local[] = 'item "'.$no.'" (row '.$r['_row'].') is priced in '.($item->currency?->code ?? 'INR').'; enter the rate in '.($party->currency?->code ?? 'INR');
                }
                $lines[] = ['item_id' => $item?->id, 'quantity' => $cell($r, 'quantity'), 'rate' => $cell($r, 'rate'), 'delivery_date' => $cell($r, 'delivery date')];
            }
            $data = [
                'party_id' => $party?->id,
                'po_number' => $cell($first, 'po number'),
                'start_date' => $cell($first, 'start date'),
                'end_date' => $cell($first, 'end date'),
                'revision_no' => $cell($first, 'rev no'),
                'revision_date' => $cell($first, 'rev date'),
                'lines' => SalesOrderRequest::cleanLines($lines),
            ];
            $po = $party ? $party->id.'|'.$data['po_number'] : null;
            if ($po && $data['po_number'] && (isset($poSeen[$po]) || SalesOrder::where('party_id', $party->id)->where('po_number', $data['po_number'])->exists())) {
                $local[] = 'PO number '.$data['po_number'].' is already on a sales order of this party';
            }
            $rules = (new SalesOrderRequest())->rules();
            $rules['party_id'] = ['required', 'integer'];
            $v = Validator::make($data, $rules, (new SalesOrderRequest())->messages(), (new SalesOrderRequest())->attributes());
            $messages = $local;
            if ($v->fails()) {
                foreach ($v->errors()->messages() as $field => $list) {
                    if ($local && ($field === 'party_id' || str_ends_with($field, 'item_id'))) {
                        continue;
                    }
                    array_push($messages, ...$list);
                }
            }
            if ($messages) {
                foreach ($messages as $m) {
                    $errors[] = $where.' (group '.(str_starts_with($key, 'row-') ? 'blank' : substr($key, 1)).'): '.$m;
                }

                continue;
            }
            $poSeen[$po] = true;
            $valid[] = $v->validated();
        }
        if ($errors) {
            throw ValidationException::withMessages(['file' => array_slice($errors, 0, 30)]);
        }

        $numbers = DB::transaction(fn () => $this->numbers->reserve('sales_order', $unit, $year, count($valid)), 3);

        return DB::transaction(function () use ($valid, $actor, $unit, $year, $numbers) {
            foreach (array_values($valid) as $i => $data) {
                $this->orders->create($data, $actor, $unit, $year, $numbers[$i]);
            }
            ActivityLogger::log('sales_order.imported', null, 'Imported '.count($valid).' sales order(s) from a CSV file', ['added' => $numbers]);

            return count($valid);
        }, 3);
    }
}
