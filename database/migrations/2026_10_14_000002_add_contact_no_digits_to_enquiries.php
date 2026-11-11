<?php

use App\Models\Enquiry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Full test B3: a contact no is stored as typed ("+91 98220 00005"), so typing "9822000005" in the Enquiry
| picker found nothing. A digits-only copy without the 91 country code is kept for searching, with an index.
| Adds one column and fills it from contact_no; no existing value is changed.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->string('contact_no_digits', 20)->nullable()->after('contact_no');
            $table->index(['unit_id', 'contact_no_digits'], 'enquiries_unit_contact_digits_index');
        });

        DB::table('enquiries')->whereNotNull('contact_no')->orderBy('id')->select(['id', 'contact_no'])->chunkById(1000, function ($rows) {
            foreach ($rows as $row) {
                DB::table('enquiries')->where('id', $row->id)->update(['contact_no_digits' => Enquiry::phoneDigits($row->contact_no)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropIndex('enquiries_unit_contact_digits_index');
            $table->dropColumn('contact_no_digits');
        });
    }
};
