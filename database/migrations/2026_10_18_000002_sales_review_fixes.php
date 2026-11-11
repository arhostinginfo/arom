<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Sales review fixes (18/10/2026), additive only:
| - sales_order_lines.credited_quantity: what active credit notes gave back on a PO line, so a credit note can never
|   return more than was invoiced minus earlier credit notes (code review M1, database review SL-M1).
| - stock_balances: stock on hand per unit and item, changed in the same transaction as every stock_movements row, so
|   the Stock report and the minimum stock check read one row per item (architecture M2, database SL-M3).
| - Indexes for the invoice reports by unit, type and date (SL-S6) and the "PO number cannot repeat" check (SL-S2).
| Both new columns and the table are filled from the rows already saved.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $t) {
            $t->decimal('credited_quantity', 15, 3)->default(0)->after('invoiced_quantity');
        });
        Schema::create('stock_balances', function (Blueprint $t) {
            $t->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $t->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $t->decimal('quantity', 15, 3)->default(0);
            $t->timestamp('updated_at')->nullable();
            $t->primary(['unit_id', 'item_id']);
            $t->index('item_id', 'stock_balances_item_index');
        });
        Schema::table('sales_invoices', function (Blueprint $t) {
            $t->index(['unit_id', 'invoice_type', 'invoice_date'], 'sales_invoices_unit_type_date_index');
        });
        Schema::table('sales_orders', function (Blueprint $t) {
            $t->index(['party_id', 'po_number'], 'sales_orders_party_po_index');
        });

        // Fill from what is saved: active credit notes against PO lines, and the stock ledger.
        $credited = DB::table('sales_invoice_lines as l')->join('sales_invoices as i', 'i.id', '=', 'l.sales_invoice_id')
            ->where('i.invoice_type', 'Credit')->where('i.status', 'Active')->whereNotNull('l.sales_order_line_id')
            ->groupBy('l.sales_order_line_id')->selectRaw('l.sales_order_line_id AS id, SUM(l.quantity) AS qty')->get();
        foreach ($credited as $c) {
            DB::table('sales_order_lines')->where('id', $c->id)->update(['credited_quantity' => $c->qty]);
        }
        DB::table('stock_movements')->groupBy('unit_id', 'item_id')->orderBy('unit_id')->orderBy('item_id')
            ->selectRaw('unit_id, item_id, SUM(quantity_in - quantity_out) AS qty')->get()
            ->chunk(500)->each(fn ($rows) => DB::table('stock_balances')->insert($rows->map(fn ($r) => [
                'unit_id' => $r->unit_id, 'item_id' => $r->item_id, 'quantity' => $r->qty, 'updated_at' => now(),
            ])->all()));
    }

    public function down(): void
    {
        Schema::table('sales_orders', fn (Blueprint $t) => $t->dropIndex('sales_orders_party_po_index'));
        Schema::table('sales_invoices', fn (Blueprint $t) => $t->dropIndex('sales_invoices_unit_type_date_index'));
        Schema::dropIfExists('stock_balances');
        Schema::table('sales_order_lines', fn (Blueprint $t) => $t->dropColumn('credited_quantity'));
    }
};
