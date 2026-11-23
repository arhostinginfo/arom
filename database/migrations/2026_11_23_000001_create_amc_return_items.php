<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * (23/11/2026) Return & Replace holds several rows: each entry of the chosen Location clicked on the page becomes a row
 * with its planter, planter qty, plant, plant size, plant qty and rate. Every existing entry becomes one row, so nothing
 * is lost; the old single-row columns on amc_returns stay (now optional) for those entries' history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amc_return_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('amc_return_id')->constrained('amc_returns')->cascadeOnDelete();
            $t->unsignedSmallInteger('line_no');
            // The entry of the AMC Client Inventory; Location 2 and the plant size are copied so the row keeps them.
            $t->foreignId('amc_client_line_id')->nullable()->constrained('amc_client_lines')->nullOnDelete();
            $t->string('location_2', 150)->nullable();
            $t->foreignId('amc_planter_id')->nullable()->constrained('amc_planters')->restrictOnDelete();
            $t->unsignedInteger('planter_qty')->nullable();
            $t->foreignId('amc_plant_id')->nullable()->constrained('amc_plants')->restrictOnDelete();
            $t->string('plant_size', 50)->nullable();
            $t->unsignedInteger('plant_qty')->nullable();
            $t->decimal('rate', 15, 2)->nullable();
            $t->timestamps();
            $t->index(['amc_return_id', 'line_no']);
        });

        Schema::table('amc_returns', function (Blueprint $t) {
            $t->unsignedBigInteger('amc_plant_id')->nullable()->change();
            $t->unsignedInteger('qty')->nullable()->change();
        });

        $now = now();
        foreach (DB::table('amc_returns')->orderBy('id')->get() as $r) {
            DB::table('amc_return_items')->insert([
                'amc_return_id' => $r->id, 'line_no' => 1, 'amc_client_line_id' => $r->amc_client_line_id, 'location_2' => $r->location_2,
                'amc_plant_id' => $r->amc_plant_id, 'plant_size' => $r->plant_size, 'plant_qty' => $r->qty, 'rate' => $r->rate,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('amc_return_items');
    }
};
