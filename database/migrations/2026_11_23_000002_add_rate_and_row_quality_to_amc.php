<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * (23/11/2026) The Plant master gets a Rate (₹), which Return & Replace rows take. Quality moves from the entry to each
 * row: existing rows take their entry's quality; the entry's own column stays (now optional) for history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amc_plants', function (Blueprint $t) {
            $t->decimal('rate', 15, 2)->nullable()->after('size');
        });
        Schema::table('amc_return_items', function (Blueprint $t) {
            $t->enum('quality', ['Reusable', 'Needs Treatment', 'Repairable', 'Dead'])->nullable()->after('rate');
        });
        Schema::table('amc_returns', function (Blueprint $t) {
            $t->enum('quality', ['Reusable', 'Needs Treatment', 'Repairable', 'Dead'])->nullable()->change();
        });
        foreach (DB::table('amc_returns')->whereNotNull('quality')->get(['id', 'quality']) as $r) {
            DB::table('amc_return_items')->where('amc_return_id', $r->id)->whereNull('quality')->update(['quality' => $r->quality]);
        }
    }

    public function down(): void
    {
        Schema::table('amc_return_items', fn (Blueprint $t) => $t->dropColumn('quality'));
        Schema::table('amc_plants', fn (Blueprint $t) => $t->dropColumn('rate'));
    }
};
