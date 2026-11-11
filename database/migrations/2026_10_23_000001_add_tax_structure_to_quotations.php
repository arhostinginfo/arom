<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Quotation UOM / Tax from the masters (23/10/2026): the quotation's GST / tax and each item's tax now come from the
| Tax master. Columns are only added (nullable); the old GST / tax text (tax_terms), Unit text and Tax % stay as they are.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('tax_structure_id')->nullable()->after('tax_terms')->constrained()->nullOnDelete();
        });
        Schema::table('quotation_lines', function (Blueprint $table) {
            $table->foreignId('tax_structure_id')->nullable()->after('discount_percent')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotation_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_structure_id');
        });
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_structure_id');
        });
    }
};
