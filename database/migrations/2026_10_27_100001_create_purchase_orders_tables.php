<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/*
| Purchase › Purchase order (PURCHASE tab, built 27/10/2026): the supplier PO and its lines.
| Each line keeps received_quantity, which GRN entry (built later) raises under a row lock, so the pending quantity of a
| line is quantity - received_quantity (see docs/purchase/purchase-order-report.md, "GRN contract").
| Also seeds the purchase order approval rule (required, above Rs 1,00,000 as in the prototype) and adds the
| Purchase order approval permissions. Only adds tables and rows; no existing table, column or row is changed.
*/
return new class extends Migration
{
    /** Listed here, not read from App\Support\Access, so this migration does not change if that class does. */
    private const PERMISSIONS = ['purchase-order-approval.view', 'purchase-order-approval.edit'];

    /** Already created by the seeder from config('nursery.pages'); made sure of here for older databases. */
    private const PAGE_PERMISSIONS = ['purchase-order.view', 'purchase-order.add', 'purchase-order.edit', 'purchase-order.delete'];

    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('unit_id')->constrained()->restrictOnDelete();
            $t->foreignId('financial_year_id')->constrained()->restrictOnDelete();
            $t->string('po_no', 30);
            $t->date('po_date');
            $t->foreignId('party_id')->constrained()->restrictOnDelete();
            $t->foreignId('currency_id')->nullable()->constrained()->restrictOnDelete();
            $t->date('delivery_date')->nullable();
            $t->string('payment_terms', 255)->nullable();
            $t->foreignId('transporter_id')->nullable()->constrained()->restrictOnDelete();
            $t->text('billing_address')->nullable();
            $t->text('shipping_address')->nullable();
            $t->string('remarks', 1000)->nullable();
            // Fixed on every save: Intra-state (CGST + SGST), Inter-state (IGST) or Import (foreign supplier, no GST on the PO).
            $t->string('supply_type', 20);
            $t->decimal('taxable_value', 15, 2)->default(0);
            $t->decimal('cgst_amount', 15, 2)->default(0);
            $t->decimal('sgst_amount', 15, 2)->default(0);
            $t->decimal('igst_amount', 15, 2)->default(0);
            $t->decimal('grand_total', 15, 2)->default(0);
            // Draft, Pending approval, Approved, Rejected; Partly received, Received (set by GRN); Closed, Cancelled.
            $t->string('status', 30)->default('Draft');
            $t->string('status_reason', 500)->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['unit_id', 'financial_year_id', 'po_no']);
            $t->index(['unit_id', 'financial_year_id', 'po_date'], 'purchase_orders_unit_date_index');
            $t->index(['unit_id', 'status'], 'purchase_orders_unit_status_index');
            $t->index(['party_id', 'status', 'deleted_at'], 'purchase_orders_party_status_index');
        });

        Schema::create('purchase_order_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('line_no');
            $t->foreignId('item_id')->constrained()->restrictOnDelete();
            $t->string('description', 255)->nullable();
            $t->string('hsn_code', 10)->nullable();
            $t->decimal('quantity', 15, 3);
            $t->foreignId('uom_id')->constrained()->restrictOnDelete();
            $t->decimal('rate', 15, 4);
            $t->decimal('discount_percent', 5, 2)->default(0);
            $t->decimal('taxable_value', 15, 2);
            $t->foreignId('tax_structure_id')->constrained()->restrictOnDelete();
            $t->decimal('cgst_rate', 5, 2)->default(0);
            $t->decimal('sgst_rate', 5, 2)->default(0);
            $t->decimal('igst_rate', 5, 2)->default(0);
            $t->decimal('cgst_amount', 15, 2)->default(0);
            $t->decimal('sgst_amount', 15, 2)->default(0);
            $t->decimal('igst_amount', 15, 2)->default(0);
            $t->decimal('total', 15, 2);
            $t->date('delivery_date')->nullable();
            // Quantity received on GRNs against this line (GRN contract); pending = quantity - received_quantity.
            $t->decimal('received_quantity', 15, 3)->default(0);
            $t->timestamps();
            $t->index(['purchase_order_id', 'line_no']);
            $t->index('item_id');
        });

        // Purchase order approval rule (D3, prototype "Purchase PO"): required, approved at once at or below Rs 1,00,000.
        DB::table('approval_rules')->insertOrIgnore(['document_type' => 'purchase_order', 'is_required' => true, 'approve_above_amount' => 100000, 'created_at' => now(), 'updated_at' => now()]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (array_merge(self::PAGE_PERMISSIONS, self::PERMISSIONS) as $name) {
            Permission::findOrCreate($name, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        DB::table('approval_rules')->where('document_type', 'purchase_order')->delete();
        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->get()->each->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
