<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CRM > Quotation (workbook CRM tab). Each revision is its own row (decision D24): Revise copies
// the current revision with revision_no + 1 and previous_revision_id, and earlier revisions are read-only.
// is_latest marks the current revision so lists and the Enquiries report read one row per quotation.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_year_id')->constrained()->restrictOnDelete();
            $table->string('enquiry_no', 30);
            $table->string('quotation_no', 30);
            $table->unsignedSmallInteger('revision_no')->default(0);
            $table->foreignId('previous_revision_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->boolean('is_latest')->default(true);
            $table->foreignId('party_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('prospect_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('source_of_enquiry', 100)->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('project_name', 150)->nullable();
            $table->foreignId('sales_person_id')->constrained('users')->restrictOnDelete();
            $table->date('enquiry_receipt_date')->index();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->enum('followup_status', ['New', 'Contacted', 'Quotation sent', 'Negotiation', 'Won', 'Lost'])->default('New')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['unit_id', 'financial_year_id', 'quotation_no', 'revision_no'], 'quotations_number_revision_unique');
            $table->index(['unit_id', 'financial_year_id', 'is_latest'], 'quotations_unit_year_latest_index');
        });

        // Item no is typed text until the Item master is built; that module adds item_id (decision C1).
        Schema::create('quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->string('item_no', 50)->nullable();
            $table->string('description', 255);
            $table->decimal('annual_volume', 15, 3)->nullable();
            $table->decimal('rate', 15, 4)->nullable();
            $table->date('sample_target_date')->nullable();
            $table->date('production_target_date')->nullable();
            $table->text('technical_details')->nullable();
            $table->timestamps();
            $table->unique(['quotation_id', 'line_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_lines');
        Schema::dropIfExists('quotations');
    }
};
