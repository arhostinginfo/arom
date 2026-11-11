<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| CRM changes (Sat, 13/10/2026): New Enquiry (the lead), Site visit / requirement gathering and
| Follow-up activities. Indexes follow the lists, alerts and dashboard that read them.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_year_id')->constrained()->restrictOnDelete();
            $table->string('enquiry_no', 30);
            $table->date('enquiry_date');
            $table->foreignId('lead_source_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('sub_source', 150)->nullable();
            $table->string('competitor_details', 255)->nullable();
            $table->enum('prospect_type', ['New', 'Existing Client', 'Referral'])->nullable();
            $table->foreignId('party_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('prospect_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('industry', 100)->nullable();
            $table->foreignId('industry_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('address')->nullable();
            $table->string('website', 191)->nullable();
            $table->string('contact_name', 100)->nullable();
            $table->string('contact_no', 20)->nullable();
            $table->foreignId('service_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('approx_requirement', 255)->nullable();
            $table->decimal('estimated_value', 15, 2)->nullable();
            $table->date('expected_closing_date')->nullable();
            $table->enum('lead_rating', ['Hot', 'Warm', 'Cold'])->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('employees')->restrictOnDelete();
            $table->string('next_action', 255)->nullable();
            $table->date('next_action_date')->nullable();
            $table->text('remarks')->nullable();
            $table->enum('status', ['Open', 'PO received', 'Lost'])->default('Open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['unit_id', 'financial_year_id', 'enquiry_no'], 'enquiries_number_unique');
            $table->index(['unit_id', 'enquiry_date'], 'enquiries_unit_date_index');
            $table->index(['unit_id', 'status', 'next_action_date'], 'enquiries_unit_due_index');
            $table->index(['unit_id', 'status', 'lead_rating'], 'enquiries_unit_rating_index');
            $table->index(['unit_id', 'status', 'expected_closing_date'], 'enquiries_unit_closing_index');
            $table->index(['assigned_to', 'status', 'next_action_date'], 'enquiries_assignee_due_index');
        });

        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('enquiry_id')->constrained()->restrictOnDelete();
            $table->dateTime('visit_at');
            $table->string('poc', 150)->nullable();
            $table->foreignId('technical_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->text('site_address')->nullable();
            $table->string('area_measurements', 255)->nullable();
            $table->string('floors', 100)->nullable();
            $table->string('indoor_outdoor_area', 255)->nullable();
            $table->text('existing_plants')->nullable();
            $table->text('existing_planters')->nullable();
            $table->string('lighting_condition', 150)->nullable();
            $table->string('water_availability', 150)->nullable();
            $table->string('access_restrictions', 255)->nullable();
            $table->text('customer_requirement')->nullable();
            $table->text('remarks')->nullable();
            $table->string('next_action', 255)->nullable();
            $table->enum('status', ['Planned', 'Done'])->default('Planned');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['unit_id', 'status', 'visit_at'], 'site_visits_unit_due_index');
        });

        // Follow-up history. The enquiry keeps the current next action and its date.
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->enum('activity_type', ['Call', 'Email', 'WhatsApp']);
            $table->date('activity_date');
            $table->text('details');
            $table->string('next_action', 255)->nullable();
            $table->date('target_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['enquiry_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('site_visits');
        Schema::dropIfExists('enquiries');
    }
};
