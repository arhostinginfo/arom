<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| CRM changes (Sat, 13/10/2026): quotation header and line fields, quotation status, internal
| cost details and the approval history. Columns are only added; existing columns and rows are kept.
| Existing quotations get a status from their old Followup status, in the new column only.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('enquiry_id')->nullable()->after('financial_year_id')->constrained()->restrictOnDelete();
            $table->date('quotation_date')->nullable()->after('revision_no');
            $table->date('valid_until')->nullable()->after('quotation_date');
            $table->string('contact_name', 150)->nullable()->after('prospect_id');
            $table->string('site', 255)->nullable()->after('contact_name');
            $table->foreignId('prepared_by')->nullable()->after('sales_person_id')->constrained('users')->nullOnDelete();
            $table->string('payment_terms', 255)->nullable();
            $table->string('delivery_terms', 255)->nullable();
            $table->string('tax_terms', 150)->nullable();
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->enum('status', ['Draft', 'Internal Approval', 'Approved', 'Sent', 'Customer Viewed/Feedback', 'Negotiation', 'Accepted', 'Rejected', 'Expired', 'Hold'])->default('Draft');
            $table->string('status_reason', 255)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->index(['unit_id', 'is_latest', 'status'], 'quotations_unit_latest_status_index');
        });

        Schema::table('quotation_lines', function (Blueprint $table) {
            $table->decimal('qty', 15, 3)->nullable()->after('description');
            $table->string('uom', 20)->nullable()->after('qty');
            $table->decimal('discount_percent', 5, 2)->default(0)->after('rate');
            $table->decimal('tax_percent', 5, 2)->default(0)->after('discount_percent');
            $table->decimal('amount', 15, 2)->default(0)->after('tax_percent');
        });

        // Internal cost details of one revision (not printed on the quotation).
        Schema::create('quotation_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->unique()->constrained()->cascadeOnDelete();
            foreach (['material_cost', 'plant_production_cost', 'planter_cost', 'labour_cost', 'transportation_cost', 'installation_cost', 'maintenance_cost', 'other_cost', 'total_cost'] as $col) {
                $table->decimal($col, 15, 2)->default(0);
            }
            $table->decimal('margin_percent', 7, 2)->default(0);
            $table->decimal('margin_amount', 15, 2)->default(0);
            $table->decimal('proposed_selling_price', 15, 2)->default(0);
            $table->timestamps();
        });

        // Submit / approve / reject history of a document (quotation now; sales and purchase documents later).
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type', 40);
            $table->unsignedBigInteger('approvable_id');
            $table->enum('action', ['Submitted', 'Approved', 'Rejected', 'Approved automatically']);
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acted_at');
            $table->index(['approvable_type', 'approvable_id', 'id'], 'approvals_subject_index');
        });

        $map = ['New' => 'Draft', 'Contacted' => 'Draft', 'Quotation sent' => 'Sent', 'Negotiation' => 'Negotiation', 'Won' => 'Accepted', 'Lost' => 'Rejected'];
        foreach ($map as $old => $new) {
            DB::table('quotations')->where('followup_status', $old)->update(['status' => $new]);
        }
        DB::table('quotations')->whereNull('quotation_date')->update(['quotation_date' => DB::raw('enquiry_receipt_date')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('quotation_costs');
        Schema::table('quotation_lines', function (Blueprint $table) {
            $table->dropColumn(['qty', 'uom', 'discount_percent', 'tax_percent', 'amount']);
        });
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex('quotations_unit_latest_status_index');
            $table->dropConstrainedForeignId('enquiry_id');
            $table->dropConstrainedForeignId('prepared_by');
            $table->dropColumn(['quotation_date', 'valid_until', 'contact_name', 'site', 'payment_terms', 'delivery_terms', 'tax_terms', 'discount_amount', 'notes', 'subtotal', 'grand_total', 'status', 'status_reason', 'sent_at']);
        });
    }
};
