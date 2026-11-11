<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| CRM changes (Sat, 13/10/2026): Source master, Category master (the Enquiry's Service type),
| Industry type and City dropdowns, Employee master, Page setup for the Enquiry fields, and the
| quotation approval rule (amount criteria). New tables only; no existing table or row is changed.
*/
return new class extends Migration
{
    /** Simple lists picked from a dropdown: one table each, name unique. */
    private const LISTS = [
        'lead_sources' => ['Website', 'Google', 'Instagram', 'Facebook', 'LinkedIn', 'WhatsApp', 'Phone Call', 'Email', 'Existing Client', 'Referral', 'BNI', 'Event/Exhibition', 'Architect', 'Interior Designer', 'Facility Management Company', 'Builder', 'Direct Visit', 'Other'],
        'service_categories' => ['Plant Rental', 'Plant AMC', 'Landscape Design', 'Landscape Development', 'Garden Maintenance', 'Vertical Garden / Green Wall', 'Terrace Garden', 'GD DECO', 'Plant Sale', 'Planters', 'Corporate Plantation', 'Other'],
        'industry_types' => ['Corporate', 'Mall', 'Hotel', 'Hospital', 'Builder', 'Other'],
        'cities' => ['Pune', 'Mumbai', 'Thane', 'Navi Mumbai', 'Nashik', 'Nagpur', 'Aurangabad', 'Kolhapur', 'Bengaluru', 'Hyderabad', 'Chennai', 'Delhi', 'Gurugram', 'Noida', 'Ahmedabad', 'Kolkata', 'Goa'],
    ];

    public function up(): void
    {
        foreach (array_keys(self::LISTS) as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['is_active', 'sort_order', 'name']);
            });
        }

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100)->index();
            $table->string('designation', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('email', 191)->nullable();
            $table->date('joining_date')->nullable();
            $table->text('address')->nullable();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'name']);
        });

        // Admin > Page setup: which fields of a page show and which are mandatory.
        Schema::create('page_settings', function (Blueprint $table) {
            $table->id();
            $table->string('page', 40);
            $table->string('field', 60);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_required')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['page', 'field']);
        });

        // Admin > Approval: one row per document type. Above the amount, the document needs approval.
        Schema::create('approval_rules', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 30)->unique();
            $table->boolean('is_required')->default(true);
            $table->decimal('approve_above_amount', 15, 2)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        foreach (self::LISTS as $table => $names) {
            DB::table($table)->insert(array_map(fn ($name, $i) => [
                'name' => $name, 'sort_order' => $i + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ], $names, array_keys($names)));
        }
        DB::table('approval_rules')->insert([
            'document_type' => 'quotation', 'is_required' => true, 'approve_above_amount' => 100000,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_rules');
        Schema::dropIfExists('page_settings');
        Schema::dropIfExists('employees');
        foreach (array_reverse(array_keys(self::LISTS)) as $name) {
            Schema::dropIfExists($name);
        }
    }
};
