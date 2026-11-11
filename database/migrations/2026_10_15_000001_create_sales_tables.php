<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
| Sales module (workbook SALES tab, built 15/10/2026) and the masters it needs from the ADMIN tab:
| company, tax structures, HSN/SAC, UOM, item categories, transporters, items; sales orders and their lines,
| tax invoices / credit notes / debit notes and their lines, and the stock ledger (stock_movements) that invoices
| write to and Purchase (GRN) will write to later. The parties table (Party master) already exists.
| Also seeds the sales order approval rule and adds the Sales order approval permissions.
| Only adds tables and rows; no existing table, column or row is changed.
*/
return new class extends Migration
{
    /** Listed here, not read from App\Support\Access, so this migration does not change if that class does. */
    private const PERMISSIONS = ['sales-order-approval.view', 'sales-order-approval.edit'];

    public function up(): void
    {
        $audit = function (Blueprint $t) {
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        };

        // Company master: one row, printed on tax invoices; its GSTIN state decides CGST + SGST or IGST.
        Schema::create('companies', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->string('gstin', 15)->nullable();
            $t->string('pan', 10)->nullable();
            $t->text('address')->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('email', 191)->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('tax_structures', function (Blueprint $t) use ($audit) {
            $t->id();
            $t->string('name', 50)->unique();
            $t->decimal('cgst_rate', 5, 2)->default(0);
            $t->decimal('sgst_rate', 5, 2)->default(0);
            $t->decimal('igst_rate', 5, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $audit($t);
        });

        Schema::create('hsn_sac_codes', function (Blueprint $t) use ($audit) {
            $t->id();
            $t->string('code', 10);
            $t->enum('type', ['HSN', 'SAC'])->default('HSN');
            $t->string('description', 255)->nullable();
            $t->foreignId('tax_structure_id')->nullable()->constrained()->restrictOnDelete();
            $t->boolean('is_active')->default(true);
            $audit($t);
            $t->unique(['code', 'type']);
        });

        Schema::create('uoms', function (Blueprint $t) use ($audit) {
            $t->id();
            $t->string('code', 10)->unique();
            $t->string('description', 100)->nullable();
            $t->boolean('is_active')->default(true);
            $audit($t);
        });

        // "Category" of the ADMIN tab's masters (item categories), not the CRM service categories.
        Schema::create('item_categories', function (Blueprint $t) use ($audit) {
            $t->id();
            $t->string('name', 100)->unique();
            $t->enum('type', ['Sales', 'Purchase', 'Both'])->default('Both');
            $t->string('item_no_prefix', 10)->nullable();
            $t->boolean('is_active')->default(true);
            $audit($t);
        });

        Schema::create('transporters', function (Blueprint $t) use ($audit) {
            $t->id();
            $t->string('name', 150)->unique();
            $t->string('gstin', 15)->nullable();
            $t->boolean('is_active')->default(true);
            $audit($t);
        });

        Schema::create('items', function (Blueprint $t) use ($audit) {
            $t->id();
            $t->foreignId('party_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('item_category_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('description', 255);
            $t->string('item_no', 50)->unique();
            $t->string('internal_item_no', 50)->nullable();
            $t->enum('type', ['Sales', 'Purchase', 'Both']);
            $t->foreignId('hsn_sac_code_id')->constrained()->restrictOnDelete();
            $t->foreignId('uom_id')->constrained()->restrictOnDelete();
            $t->foreignId('tax_structure_id')->constrained()->restrictOnDelete();
            $t->decimal('price', 15, 4)->default(0);
            $t->foreignId('currency_id')->nullable()->constrained()->restrictOnDelete();
            $t->decimal('min_stock', 15, 3)->nullable();
            $t->decimal('max_stock', 15, 3)->nullable();
            $t->decimal('weight_per_piece', 15, 3)->nullable();
            $t->decimal('packaging_qty', 15, 3)->nullable();
            $t->enum('status', ['Active', 'Inactive'])->default('Active');
            $audit($t);
            $t->softDeletes();
            // A customer's sales items for the order and invoice pickers; the list and type-ahead by description.
            $t->index(['party_id', 'status', 'type', 'item_no'], 'items_party_status_index');
            $t->index(['deleted_at', 'description'], 'items_description_index');
            $t->index('internal_item_no', 'items_internal_no_index');
        });

        Schema::create('sales_orders', function (Blueprint $t) use ($audit) {
            $t->id();
            $t->foreignId('unit_id')->constrained()->restrictOnDelete();
            $t->foreignId('financial_year_id')->constrained()->restrictOnDelete();
            $t->string('so_no', 30);
            $t->foreignId('party_id')->constrained()->restrictOnDelete();
            $t->string('po_number', 60);
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->string('revision_no', 10)->nullable();
            $t->date('revision_date')->nullable();
            $t->enum('status', ['Draft', 'Pending approval', 'Approved', 'Rejected'])->default('Draft');
            $t->string('status_reason', 500)->nullable();
            $t->decimal('total_value', 15, 2)->default(0);
            $audit($t);
            $t->softDeletes();
            $t->unique(['unit_id', 'financial_year_id', 'so_no']);
            $t->index(['unit_id', 'financial_year_id', 'start_date'], 'sales_orders_unit_date_index');
            $t->index(['party_id', 'status', 'deleted_at'], 'sales_orders_party_status_index');
            $t->index(['unit_id', 'status'], 'sales_orders_unit_status_index');
            $t->index(['unit_id', 'po_number'], 'sales_orders_unit_po_index');
        });

        Schema::create('sales_order_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('line_no');
            $t->foreignId('item_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity', 15, 3);
            $t->decimal('rate', 15, 4);
            $t->decimal('amount', 15, 2);
            $t->date('delivery_date');
            // Quantity already billed on Sales invoices; kept with the line under a row lock (qty lock, D6).
            $t->decimal('invoiced_quantity', 15, 3)->default(0);
            $t->timestamps();
            $t->index(['sales_order_id', 'line_no']);
        });

        Schema::create('sales_invoices', function (Blueprint $t) use ($audit) {
            $t->id();
            $t->foreignId('unit_id')->constrained()->restrictOnDelete();
            $t->foreignId('financial_year_id')->constrained()->restrictOnDelete();
            $t->enum('invoice_type', ['Sales', 'Credit', 'Debit']);
            $t->string('invoice_no', 30);
            $t->date('invoice_date');
            $t->foreignId('party_id')->constrained()->restrictOnDelete();
            $t->foreignId('sales_order_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('currency_id')->nullable()->constrained()->restrictOnDelete();
            $t->text('shipping_address');
            $t->enum('mode_of_transport', ['Road', 'Rail', 'Air', 'Ship'])->nullable();
            $t->foreignId('transporter_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('vehicle_no', 20)->nullable();
            $t->decimal('distance_km', 8, 1)->nullable();
            $t->decimal('pf_charges', 15, 2)->default(0);
            // Supply type, fixed on save: Intra-state (CGST + SGST), Inter-state (IGST) or Export (under LUT, no GST).
            $t->enum('supply_type', ['Intra-state', 'Inter-state', 'Export']);
            $t->decimal('taxable_value', 15, 2)->default(0);
            $t->decimal('cgst_amount', 15, 2)->default(0);
            $t->decimal('sgst_amount', 15, 2)->default(0);
            $t->decimal('igst_amount', 15, 2)->default(0);
            $t->decimal('grand_total', 15, 2)->default(0);
            $t->enum('status', ['Active', 'Cancelled'])->default('Active');
            $t->string('cancel_reason', 500)->nullable();
            $audit($t);
            $t->unique(['unit_id', 'financial_year_id', 'invoice_type', 'invoice_no'], 'sales_invoices_number_unique');
            $t->index(['unit_id', 'financial_year_id', 'invoice_date'], 'sales_invoices_unit_date_index');
            $t->index(['party_id', 'invoice_date'], 'sales_invoices_party_date_index');
            $t->index(['sales_order_id', 'status'], 'sales_invoices_order_index');
        });

        Schema::create('sales_invoice_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_invoice_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('line_no');
            $t->foreignId('sales_order_line_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('item_id')->constrained()->restrictOnDelete();
            $t->string('hsn_code', 10)->nullable();
            $t->decimal('quantity', 15, 3);
            $t->decimal('rate', 15, 4);
            $t->decimal('taxable_value', 15, 2);
            $t->decimal('cgst_rate', 5, 2)->default(0);
            $t->decimal('sgst_rate', 5, 2)->default(0);
            $t->decimal('igst_rate', 5, 2)->default(0);
            $t->decimal('cgst_amount', 15, 2)->default(0);
            $t->decimal('sgst_amount', 15, 2)->default(0);
            $t->decimal('igst_amount', 15, 2)->default(0);
            $t->decimal('total', 15, 2);
            $t->timestamps();
            $t->index(['sales_invoice_id', 'line_no']);
            $t->index('sales_order_line_id');
            $t->index('item_id');
        });

        // Stock ledger: one row per stock in or out. Sales invoices write here now; GRNs and stock transfers
        // (Purchase module) will add their rows later. Stock reports read from here.
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('unit_id')->constrained()->restrictOnDelete();
            $t->foreignId('item_id')->constrained()->restrictOnDelete();
            $t->date('movement_date');
            $t->decimal('quantity_in', 15, 3)->default(0);
            $t->decimal('quantity_out', 15, 3)->default(0);
            $t->string('source_type', 40);
            $t->unsignedBigInteger('source_id');
            $t->timestamp('created_at')->nullable();
            $t->index(['unit_id', 'item_id', 'movement_date'], 'stock_movements_unit_item_date_index');
            $t->index(['source_type', 'source_id'], 'stock_movements_source_index');
        });

        // Starting lists: the GST rate slabs and common units. Admin › Masters can add, change or deactivate them.
        $now = now();
        foreach ([['GST 0%', 0, 0, 0], ['GST 5%', 2.5, 2.5, 5], ['GST 12%', 6, 6, 12], ['GST 18%', 9, 9, 18], ['GST 28%', 14, 14, 28]] as [$name, $c, $s, $i]) {
            DB::table('tax_structures')->insertOrIgnore(['name' => $name, 'cgst_rate' => $c, 'sgst_rate' => $s, 'igst_rate' => $i, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ([['NOS', 'Numbers'], ['KG', 'Kilogram'], ['MTR', 'Metre'], ['SET', 'Set'], ['LTR', 'Litre'], ['SQM', 'Square metre']] as [$code, $desc]) {
            DB::table('uoms')->insertOrIgnore(['code' => $code, 'description' => $desc, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Sales order approval rule (D3): required for every order until Admin › Approval changes it.
        DB::table('approval_rules')->insertOrIgnore(['document_type' => 'sales_order', 'is_required' => true, 'approve_above_amount' => null, 'created_at' => now(), 'updated_at' => now()]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (['stock_movements', 'sales_invoice_lines', 'sales_invoices', 'sales_order_lines', 'sales_orders', 'items', 'transporters', 'item_categories', 'uoms', 'hsn_sac_codes', 'tax_structures', 'companies'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::table('approval_rules')->where('document_type', 'sales_order')->delete();
        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->get()->each->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
