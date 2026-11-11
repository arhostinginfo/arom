<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Indexes for the filters and sorts that lists and reports run (database review DB-M3, DB-S1, DB-S2, rule P1).
// Only adds indexes: no column or data changes.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['action', 'id'], 'activity_logs_action_id_index');
            $table->index(['user_id', 'created_at'], 'activity_logs_user_created_index');
        });

        // Users list sorts by name, login email (already unique), status and last login.
        Schema::table('users', function (Blueprint $table) {
            $table->index('name', 'users_name_index');
            $table->index('last_login_at', 'users_last_login_at_index');
        });

        // Prospects report: unit and date added. Prospect picker: phone starts with.
        Schema::table('prospects', function (Blueprint $table) {
            $table->index(['unit_id', 'created_at'], 'prospects_unit_created_index');
            $table->index('phone_1', 'prospects_phone_1_index');
        });

        // Enquiries report and quotation lists: current revision, unit and enquiry date.
        Schema::table('quotations', function (Blueprint $table) {
            $table->index(['is_latest', 'unit_id', 'enquiry_receipt_date'], 'quotations_latest_unit_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', fn (Blueprint $table) => $table->dropIndex('quotations_latest_unit_date_index'));
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropIndex('prospects_unit_created_index');
            $table->dropIndex('prospects_phone_1_index');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_name_index');
            $table->dropIndex('users_last_login_at_index');
        });
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('activity_logs_action_id_index');
            $table->dropIndex('activity_logs_user_created_index');
        });
    }
};
