<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** (22/11/2026) AMC Client Master: the client's gardener, picked from the active Gardeners of the AMC Employee master. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amc_clients', function (Blueprint $t) {
            $t->foreignId('gardener_id')->nullable()->after('amc_vertical_id')->constrained('amc_employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('amc_clients', function (Blueprint $t) {
            $t->dropConstrainedForeignId('gardener_id');
        });
    }
};
