<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Picker speed (Sat 18/10/2026: server-fed dropdowns open slowly). The select-with-search asks for the first page
| ordered by name, then "starts with" pages. These indexes let MySQL read just those 20 rows in name order instead
| of sorting the whole table. Indexes only; no data changes.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', fn (Blueprint $t) => $t->index(['status', 'deleted_at', 'name'], 'parties_status_name_index'));
        Schema::table('prospects', fn (Blueprint $t) => $t->index(['deleted_at', 'name'], 'prospects_deleted_name_index'));
        Schema::table('cities', fn (Blueprint $t) => $t->index(['is_active', 'name'], 'cities_active_name_index'));
        Schema::table('employees', fn (Blueprint $t) => $t->index(['status', 'deleted_at', 'name'], 'employees_status_deleted_name_index'));
    }

    public function down(): void
    {
        Schema::table('parties', fn (Blueprint $t) => $t->dropIndex('parties_status_name_index'));
        Schema::table('prospects', fn (Blueprint $t) => $t->dropIndex('prospects_deleted_name_index'));
        Schema::table('cities', fn (Blueprint $t) => $t->dropIndex('cities_active_name_index'));
        Schema::table('employees', fn (Blueprint $t) => $t->dropIndex('employees_status_deleted_name_index'));
    }
};
