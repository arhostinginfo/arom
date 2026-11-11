<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Company logo on the quotation PDF (23/10/2026): where the logo uploaded in Admin > Masters > Company is kept on the
| documents disk. One nullable column is only added: nothing existing changes, and no logo means the PDF as before.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('logo_path', 255)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
