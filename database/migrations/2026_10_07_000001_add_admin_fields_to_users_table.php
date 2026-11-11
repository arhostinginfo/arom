<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Users list fields from decision D19: Name, Login email, Mobile, Role, Units allowed, Status.
// Role lives in Spatie's model_has_roles, units in unit_user. Deleted users are soft deleted
// so documents and logs that point at them keep working (decision U7).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile', 10)->after('email')->unique();
            $table->enum('status', ['Active', 'Inactive'])->default('Active')->after('password')->index();
            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->foreignId('created_by')->nullable()->after('remember_token')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropSoftDeletes();
            $table->dropUnique(['mobile']);
            $table->dropIndex(['status']);
            $table->dropColumn(['mobile', 'status', 'last_login_at']);
        });
    }
};
