<?php

namespace App\Services;

use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * The one way stock moves (architecture review S7): every document writes its stock_movements rows through here, inside
 * its own transaction, and stock_balances (stock on hand per unit and item) changes in the same statement order. The
 * Sales invoice uses source_type 'sales_invoice', stock transfer 'stock_transfer' and GRN 'grn'; the stock reports read every source.
 */
class StockLedger
{
    /** One movement: $in comes into stock, $out goes out. */
    public static function move(int $unitId, int $itemId, string $date, float $in, float $out, string $sourceType, int $sourceId): void
    {
        StockMovement::create([
            'unit_id' => $unitId, 'item_id' => $itemId, 'movement_date' => $date,
            'quantity_in' => $in, 'quantity_out' => $out, 'source_type' => $sourceType, 'source_id' => $sourceId,
        ]);
        self::bump($unitId, $itemId, round($in - $out, 3));
    }

    /** Remove a document's movements and take them off the balances (an invoice edited or cancelled). */
    public static function remove(string $sourceType, int $sourceId): void
    {
        $rows = StockMovement::where('source_type', $sourceType)->where('source_id', $sourceId)
            ->groupBy('unit_id', 'item_id')->orderBy('unit_id')->orderBy('item_id')
            ->selectRaw('unit_id, item_id, SUM(quantity_in - quantity_out) AS qty')->get();
        foreach ($rows as $r) {
            self::bump((int) $r->unit_id, (int) $r->item_id, -round((float) $r->qty, 3));
        }
        StockMovement::where('source_type', $sourceType)->where('source_id', $sourceId)->delete();
    }

    /** Stock on hand now, per item id, for the given units. */
    public static function onHand(array $unitIds, array $itemIds): array
    {
        return DB::table('stock_balances')->whereIn('unit_id', $unitIds)->whereIn('item_id', $itemIds)
            ->groupBy('item_id')->selectRaw('item_id, SUM(quantity) AS qty')->pluck('qty', 'item_id')->map(fn ($q) => (float) $q)->all();
    }

    /** Add $qty to the balance row in one statement (insert it the first time), so two saves never lose an update. */
    private static function bump(int $unitId, int $itemId, float $qty): void
    {
        if ($qty == 0.0) {
            return;
        }
        $now = now()->toDateTimeString();
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('INSERT INTO stock_balances (unit_id, item_id, quantity, updated_at) VALUES (?, ?, ?, ?)
                ON CONFLICT(unit_id, item_id) DO UPDATE SET quantity = quantity + excluded.quantity, updated_at = excluded.updated_at', [$unitId, $itemId, $qty, $now]);
        } else {
            DB::statement('INSERT INTO stock_balances (unit_id, item_id, quantity, updated_at) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), updated_at = VALUES(updated_at)', [$unitId, $itemId, $qty, $now]);
        }
    }
}
