<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * (22/11/2026) AMC Client Master: the customer is picked from the Party master (an active Customer or Both party).
 * amc_clients.name stays, copied from the party, so the lists and searches work as before. A client already in the
 * master is linked to the party of the same name (any case) when there is one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amc_clients', function (Blueprint $t) {
            $t->foreignId('party_id')->nullable()->after('id')->constrained('parties')->nullOnDelete();
            $t->unique('party_id');
        });

        $used = [];
        foreach (DB::table('amc_clients')->orderBy('id')->get(['id', 'name']) as $c) {
            $party = DB::table('parties')->whereNull('deleted_at')->whereRaw('LOWER(name) = ?', [mb_strtolower($c->name)])
                ->whereNotIn('id', $used ?: [0])->orderByRaw("status = 'Active' desc")->orderBy('id')->value('id');
            if ($party) {
                DB::table('amc_clients')->where('id', $c->id)->update(['party_id' => $party]);
                $used[] = $party;
            }
        }
    }

    public function down(): void
    {
        Schema::table('amc_clients', function (Blueprint $t) {
            $t->dropUnique(['party_id']);
            $t->dropConstrainedForeignId('party_id');
        });
    }
};
