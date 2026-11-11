<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/*
| Purchase › Stock transfer (27/10/2026): moves items from one unit (plant) to another. Stock moves only when the
| transfer is Approved, through StockLedger (source_type 'stock_transfer'). Adds the two tables, the stock transfer
| approval rule (required, one level) and the Stock transfer approval permissions.
| Only adds tables and rows; no existing table, column or row is changed.
*/
return new class extends Migration
{
    /** Listed here, not read from App\Support\Access, so this migration does not change if that class does. */
    private const PERMISSIONS = ['stock-transfer-approval.view', 'stock-transfer-approval.edit'];

    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('financial_year_id')->constrained()->restrictOnDelete();
            // ST/26-27/0001, numbered per source unit and financial year (NumberSeries 'stock_transfer').
            $t->string('transfer_no', 30);
            $t->date('transfer_date');
            $t->foreignId('from_unit_id')->constrained('units')->restrictOnDelete();
            $t->foreignId('to_unit_id')->constrained('units')->restrictOnDelete();
            // The kind of inward document the stock came from: Purchase, Credit or Debit (PURCHASE tab, prototype).
            $t->string('source_type', 20)->nullable();
            $t->string('remarks', 1000)->nullable();
            $t->enum('status', ['Draft', 'Pending approval', 'Approved', 'Rejected', 'Cancelled'])->default('Draft');
            // Reason of the last rejection or of the cancellation.
            $t->string('status_reason', 500)->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('cancelled_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['from_unit_id', 'financial_year_id', 'transfer_no'], 'stock_transfers_number_unique');
            // Entry picker and list: the unit on the top bar is either side of the transfer.
            $t->index(['from_unit_id', 'financial_year_id', 'status'], 'stock_transfers_from_index');
            $t->index(['to_unit_id', 'financial_year_id', 'status'], 'stock_transfers_to_index');
            $t->index(['financial_year_id', 'transfer_date'], 'stock_transfers_date_index');
        });

        Schema::create('stock_transfer_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('line_no');
            $t->foreignId('item_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity', 15, 3);
            // Copied from the Item master when the line is saved, so the document keeps the UOM it was made in.
            $t->foreignId('uom_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('remarks', 255)->nullable();
            $t->timestamps();
            $t->index(['stock_transfer_id', 'line_no']);
            $t->index('item_id');
        });

        // Stock transfer approval (prototype Admin › Approval: required, one level) until Admin › Approval changes it.
        DB::table('approval_rules')->insertOrIgnore(['document_type' => 'stock_transfer', 'is_required' => true, 'approve_above_amount' => null, 'created_at' => now(), 'updated_at' => now()]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_transfers');
        DB::table('approval_rules')->where('document_type', 'stock_transfer')->delete();
        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->get()->each->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
