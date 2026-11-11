<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/*
| Purchase › GRN entry (27/10/2026): the supplier invoice received (Goods Received Note) and its item lines.
| A GRN is saved as "Pending match" (no number) until the lines total (with GST) equals the supplier invoice amount
| within the tolerance; then it is "Matched" and gets its GRN no (GRN/26-27/0001, NumberSeries 'grn') and GRN date,
| and the PO lines' received quantities go up through PurchaseOrderService::receive(). Stock comes in through
| StockLedger (source_type 'grn') only when Quality approval is Approved, and only the accepted quantity.
| Also adds the GRN quality approval permissions. Only adds tables and rows; no existing table, column or row is changed.
*/
return new class extends Migration
{
    /** Listed here, not read from App\Support\Access, so this migration does not change if that class does. */
    private const PERMISSIONS = ['grn-quality.view', 'grn-quality.edit'];

    /** Already created by the seeder from config('nursery.pages'); made sure of here for older databases. */
    private const PAGE_PERMISSIONS = ['grn.view', 'grn.add', 'grn.edit', 'grn.delete'];

    public function up(): void
    {
        Schema::create('grns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('unit_id')->constrained()->restrictOnDelete();
            $t->foreignId('financial_year_id')->constrained()->restrictOnDelete();
            // Given only when the amount matches (NumberSeries 'grn'); NULL while "Pending match".
            $t->string('grn_no', 30)->nullable();
            $t->date('grn_date')->nullable();
            // Purchase (receives against PO lines, brings stock in), Credit or Debit (supplier notes: no PO, no stock).
            $t->string('invoice_type', 10)->default('Purchase');
            $t->foreignId('party_id')->constrained()->restrictOnDelete();
            $t->foreignId('currency_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('invoice_no', 40);
            // invoice_no while the GRN is live; NULL once cancelled or deleted, so the same invoice can be entered again.
            $t->string('active_invoice_no', 40)->nullable();
            $t->date('invoice_date');
            $t->decimal('invoice_amount', 15, 2);
            $t->foreignId('transporter_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('vehicle_no', 20)->nullable();
            $t->string('eway_bill_no', 20)->nullable();
            $t->date('eway_bill_date')->nullable();
            // Optional: material bought against a customer's order (prototype "Customer name", "SO no").
            $t->foreignId('customer_id')->nullable()->constrained('parties')->restrictOnDelete();
            $t->foreignId('sales_order_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('remarks', 1000)->nullable();
            // Intra-state (CGST + SGST), Inter-state (IGST) or Import (no GST), by the supplier as on the PO.
            $t->string('supply_type', 20);
            $t->decimal('taxable_value', 15, 2)->default(0);
            $t->decimal('cgst_amount', 15, 2)->default(0);
            $t->decimal('sgst_amount', 15, 2)->default(0);
            $t->decimal('igst_amount', 15, 2)->default(0);
            $t->decimal('grand_total', 15, 2)->default(0);
            // Pending match / Matched.
            $t->string('match_status', 20)->default('Pending match');
            $t->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('matched_at')->nullable();
            // Pending / Approved / Rejected. Stock comes in only on Approved.
            $t->string('quality_status', 20)->default('Pending');
            $t->foreignId('quality_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('quality_at')->nullable();
            $t->string('quality_remarks', 500)->nullable();
            // True while this GRN's accepted quantities are on the stock ledger.
            $t->boolean('stock_posted')->default(false);
            // Draft (reserved), Saved, Cancelled.
            $t->string('status', 20)->default('Saved');
            $t->string('status_reason', 500)->nullable();
            $t->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('cancelled_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['unit_id', 'financial_year_id', 'grn_no'], 'grns_number_unique');
            // One live GRN per supplier invoice number and financial year.
            $t->unique(['party_id', 'financial_year_id', 'active_invoice_no'], 'grns_supplier_invoice_unique');
            $t->index(['unit_id', 'financial_year_id', 'match_status'], 'grns_unit_match_index');
            $t->index(['unit_id', 'financial_year_id', 'quality_status'], 'grns_unit_quality_index');
            $t->index(['unit_id', 'financial_year_id', 'invoice_date'], 'grns_unit_date_index');
            $t->index(['sales_order_id']);
        });

        Schema::create('grn_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('grn_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('line_no');
            $t->foreignId('purchase_order_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('purchase_order_line_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('item_id')->constrained()->restrictOnDelete();
            $t->string('description', 255)->nullable();
            $t->string('hsn_code', 10)->nullable();
            // Invoice quantity; accepted + rejected are set at the quality decision.
            $t->decimal('quantity', 15, 3);
            $t->decimal('accepted_quantity', 15, 3)->default(0);
            $t->decimal('rejected_quantity', 15, 3)->default(0);
            $t->foreignId('uom_id')->nullable()->constrained()->restrictOnDelete();
            $t->decimal('rate', 15, 4);
            $t->decimal('taxable_value', 15, 2);
            $t->foreignId('tax_structure_id')->nullable()->constrained()->restrictOnDelete();
            $t->decimal('cgst_rate', 5, 2)->default(0);
            $t->decimal('sgst_rate', 5, 2)->default(0);
            $t->decimal('igst_rate', 5, 2)->default(0);
            $t->decimal('cgst_amount', 15, 2)->default(0);
            $t->decimal('sgst_amount', 15, 2)->default(0);
            $t->decimal('igst_amount', 15, 2)->default(0);
            $t->decimal('total', 15, 2);
            $t->timestamps();
            $t->index(['grn_id', 'line_no']);
            $t->index('purchase_order_line_id');
            $t->index('item_id');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (array_merge(self::PAGE_PERMISSIONS, self::PERMISSIONS) as $name) {
            Permission::findOrCreate($name, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('grn_lines');
        Schema::dropIfExists('grns');
        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->get()->each->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
